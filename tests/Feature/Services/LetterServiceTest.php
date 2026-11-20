<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\Enums\LetterStatus;
use GraystackIT\Pingen\Events\LetterCreated;
use GraystackIT\Pingen\Events\LetterSubmitted;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Exceptions\FileUploadExpiredException;
use GraystackIT\Pingen\Exceptions\LetterRejectedException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Exceptions\RateLimitExceededException;
use GraystackIT\Pingen\Models\PingenLetter;
use GraystackIT\Pingen\PingenService;
use GraystackIT\Pingen\Requests\FileUpload\RequestFileUploadRequest;
use GraystackIT\Pingen\Requests\FileUpload\UploadFileRequest;
use GraystackIT\Pingen\Requests\Letters\CalculatePriceRequest;
use GraystackIT\Pingen\Requests\Letters\CancelLetterRequest;
use GraystackIT\Pingen\Requests\Letters\CreateLetterRequest;
use GraystackIT\Pingen\Requests\Letters\SendLetterRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
    ]);
});

/**
 * A service whose connectors answer from the given mock.
 */
function serviceWithMock(MockClient $mock): PingenService
{
    $service = PingenService::fromConfig();

    $service->connector()->withMockClient($mock);
    $service->uploads();
    $service->letters();

    // The upload connector is built inside the service, so reach it the same way.
    (function () use ($mock): void {
        $property = new ReflectionProperty($this, 'uploads');
        /** @var \GraystackIT\Pingen\Services\FileUploadService $uploadService */
        $uploadService = $property->getValue($this);

        $uploadsProperty = new ReflectionProperty($uploadService, 'uploads');
        $uploadsProperty->getValue($uploadService)->withMockClient($mock);
    })->call($service);

    return $service;
}

it('uploads the file and creates the letter in three calls', function (): void {
    Event::fake([LetterCreated::class, LetterSubmitted::class]);

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(letterResponse(), 200),
    ]);

    $letter = serviceWithMock($mock)->send(new LetterDraft(
        contents: 'PDF-BYTES',
        fileName: 'invoice.pdf',
    ));

    expect($letter->id)->toBe('11111111-2222-4333-8444-555555555555')
        ->and($letter->status)->toBe(LetterStatus::Draft);

    // The create call must quote the signature the slot handed out, otherwise
    // Pingen has no way to tell our file from anything else at that URL.
    $sent = $mock->getLastPendingRequest();
    $body = json_decode((string) $sent->body(), true);

    expect($body['data']['attributes']['file_url'])->toBe('https://storage.example.test/upload/abc123')
        ->and($body['data']['attributes']['file_url_signature'])->toBe('signature-abc123');

    Event::assertDispatched(LetterCreated::class);
    Event::assertNotDispatched(LetterSubmitted::class);
});

it('announces a submission when the letter is auto-sent', function (): void {
    Event::fake([LetterCreated::class, LetterSubmitted::class]);

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(letterResponse(['status' => 'sending']), 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft(
        contents: 'PDF-BYTES',
        fileName: 'invoice.pdf',
        autoSend: true,
    ));

    Event::assertDispatched(LetterSubmitted::class);
});

it('refuses to upload into a slot that has expired', function (): void {
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(
            fileUploadResponse(now()->subMinute()->toIso8601String()),
            200,
        ),
        UploadFileRequest::class => MockResponse::make('', 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));
})->throws(FileUploadExpiredException::class);

it('reports a refused PDF as a rejection, not a generic failure', function (): void {
    // The caller has to tell "fix your file" from "try again later", because
    // only one of those should give a metered allowance back and stop.
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(
            errorResponse('validation_error', 'The file could not be processed.', '/data/attributes/file_url'),
            422,
        ),
    ]);

    serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));
})->throws(LetterRejectedException::class, 'The file could not be processed.');

it('puts the JSON:API error detail and pointer into the message', function (): void {
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(
            errorResponse('validation_error', 'Unknown delivery product.', '/data/attributes/delivery_product'),
            422,
        ),
    ]);

    try {
        serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));
        $this->fail('The rejection should have been thrown.');
    } catch (PingenException $exception) {
        expect($exception->getMessage())
            ->toContain('/data/attributes/delivery_product')
            ->toContain('Unknown delivery product.')
            ->and($exception->status)->toBe(422)
            ->and($exception->errors)->toHaveCount(1);
    }
});

it('reports a 429 as a rate limit, carrying what the headers said', function (): void {
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(
            errorResponse('rate_limit', 'Too many requests.'),
            429,
            ['Retry-After' => '30', 'X-RateLimit-Limit' => '100', 'X-RateLimit-Remaining' => '0'],
        ),
    ]);

    try {
        serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));
        $this->fail('The rate limit should have been thrown.');
    } catch (RateLimitExceededException $exception) {
        expect($exception->retryAfter)->toBe(30)
            ->and($exception->limit)->toBe(100)
            ->and($exception->remaining)->toBe(0);
    }
});

it('submits a draft with the configured print defaults', function (): void {
    Event::fake([LetterSubmitted::class]);

    $mock = new MockClient([
        SendLetterRequest::class => MockResponse::make(letterResponse(['status' => 'sending']), 200),
    ]);

    $service = serviceWithMock($mock);
    $service->submit('11111111-2222-4333-8444-555555555555');

    $body = json_decode((string) $mock->getLastPendingRequest()->body(), true);

    expect($body['data']['attributes']['delivery_product'])->toBe('cheap')
        ->and($body['data']['attributes']['print_mode'])->toBe('simplex')
        ->and($body['data']['attributes']['print_spectrum'])->toBe('grayscale');

    Event::assertDispatched(LetterSubmitted::class);
});

it('refuses a print option the configuration does not allow', function (): void {
    config()->set('pingen.allowed.delivery_product', ['cheap', 'fast']);

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft(
        contents: 'PDF-BYTES',
        fileName: 'invoice.pdf',
        deliveryProduct: 'luxury-courier',
    ));
})->throws(ConfigurationException::class, 'luxury-courier');

it('checks the allowlist before anything is uploaded', function (): void {
    // A bad option must fail while it is still free to fail: once the file is
    // in storage, a refusal has already cost a round trip and left a stray
    // upload behind.
    config()->set('pingen.allowed.print_mode', ['simplex', 'duplex']);

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
    ]);

    try {
        serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf', printMode: 'triplex'));
    } catch (ConfigurationException) {
        // expected
    }

    expect($mock->getRecordedResponses())->toBeEmpty();
});

it('refuses to auto-send without the print options', function (): void {
    config()->set('pingen.defaults.delivery_product', null);

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf', autoSend: true));
})->throws(ConfigurationException::class, 'delivery_product');

it('asks for a price without creating anything', function (): void {
    $mock = new MockClient([
        CalculatePriceRequest::class => MockResponse::make([
            'data' => ['type' => 'letters', 'attributes' => ['price_value' => 2.4, 'price_currency' => 'CHF']],
        ], 200),
    ]);

    $price = serviceWithMock($mock)->estimate('AT');

    expect($price->value)->toBe(2.4)
        ->and($price->currency)->toBe('CHF');
});

it('cancels a letter', function (): void {
    $mock = new MockClient([
        CancelLetterRequest::class => MockResponse::make([], 200),
    ]);

    serviceWithMock($mock)->cancel('11111111-2222-4333-8444-555555555555');

    expect($mock->getLastPendingRequest()->getUrl())
        ->toContain('/deliveries/letters/11111111-2222-4333-8444-555555555555/cancel');
});

it('reports a letter too far along to stop as a refusal', function (): void {
    // A 409 on cancel is the API saying the letter is past the point of
    // stopping. The caller has to tell that apart from a provider it could not
    // reach: one is final and belongs on the screen, the other is worth
    // retrying. Both used to arrive as the same class.
    $mock = new MockClient([
        CancelLetterRequest::class => MockResponse::make(
            ['errors' => [['code' => '409', 'detail' => 'Invalid letter status']]],
            409,
        ),
    ]);

    expect(fn () => serviceWithMock($mock)->cancel('11111111-2222-4333-8444-555555555555'))
        ->toThrow(LetterRejectedException::class);
});

it('leaves a cancel that failed in transit as an ordinary failure', function (): void {
    $mock = new MockClient([
        CancelLetterRequest::class => MockResponse::make(['errors' => [['detail' => 'Gateway down']]], 502),
    ]);

    expect(fn () => serviceWithMock($mock)->cancel('11111111-2222-4333-8444-555555555555'))
        ->toThrow(PingenException::class)
        ->not->toThrow(LetterRejectedException::class);
});

it('records the letter when database storage is on', function (): void {
    config()->set('pingen.store_logs', true);
    config()->set('pingen.storage_driver', 'database');

    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(letterResponse(), 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));

    $row = PingenLetter::query()->firstOrFail();

    expect($row->letter_id)->toBe('11111111-2222-4333-8444-555555555555')
        ->and($row->organisation_id)->toBe('00000000-0000-4000-8000-000000000001')
        ->and($row->status)->toBe('draft')
        ->and($row->file_pages)->toBe(2);
});

it('records nothing when storage is off', function (): void {
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
        UploadFileRequest::class => MockResponse::make('', 200),
        CreateLetterRequest::class => MockResponse::make(letterResponse(), 200),
    ]);

    serviceWithMock($mock)->send(new LetterDraft('PDF-BYTES', 'invoice.pdf'));

    expect(PingenLetter::query()->count())->toBe(0);
});

it('binds a second service to another organisation without touching the first', function (): void {
    $service = PingenService::fromConfig();
    $other = $service->forOrganisation('99999999-8888-4777-8666-555555555555');

    expect($service->organisationId())->toBe('00000000-0000-4000-8000-000000000001')
        ->and($other->organisationId())->toBe('99999999-8888-4777-8666-555555555555');
});

it('explains a submit the API refuses on status grounds', function (): void {
    // Pingen answers with a bare "Invalid deliverable status", which says
    // nothing about why. Confirmed against staging: a letter that failed
    // inspection sits on action_required and is refused here.
    seedAccessToken();

    $mock = new MockClient([
        SendLetterRequest::class => MockResponse::make(
            ['errors' => [['code' => '409', 'detail' => 'Invalid deliverable status']]],
            409,
        ),
    ]);

    $service = PingenService::fromConfig();
    $service->connector()->withMockClient($mock);

    try {
        $service->submit('letter-9', 'cheap', 'simplex', 'grayscale');

        $this->fail('The refusal should have thrown.');
    } catch (LetterRejectedException $e) {
        expect($e->getMessage())
            ->toContain('letter-9')
            ->toContain('not in a submittable state')
            ->toContain('action_required')
            ->and($e->status)->toBe(409);
    }
});

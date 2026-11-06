<?php

declare(strict_types=1);

use GraystackIT\Pingen\Requests\Letters\CalculatePriceRequest;
use GraystackIT\Pingen\Requests\Letters\CreateLetterRequest;
use GraystackIT\Pingen\Requests\Webhooks\CreateWebhookRequest;

/**
 * Decode a request body back into an array.
 *
 * @return array<string, mixed>
 */
function decodedBody(object $request): array
{
    return json_decode((string) $request->body(), true, 512, JSON_THROW_ON_ERROR);
}

it('wraps attributes in the JSON:API envelope', function (): void {
    $body = decodedBody(new CreateLetterRequest(
        organisationId: 'org-1',
        fileUrl: 'https://storage.example.test/u',
        fileUrlSignature: 'sig',
        fileOriginalName: 'invoice.pdf',
        addressPosition: 'left',
    ));

    expect($body)->toHaveKey('data')
        ->and($body['data']['type'])->toBe('letters')
        ->and($body['data']['attributes']['file_original_name'])->toBe('invoice.pdf')
        ->and($body['data']['attributes']['address_position'])->toBe('left');
});

it('drops attributes that were not given', function (): void {
    // An absent attribute means "use the account default"; an explicit null
    // would mean "unset it". Every optional argument here means the former, so
    // nulls must not reach the wire.
    $body = decodedBody(new CreateLetterRequest(
        organisationId: 'org-1',
        fileUrl: 'https://storage.example.test/u',
        fileUrlSignature: 'sig',
        fileOriginalName: 'invoice.pdf',
        addressPosition: 'left',
    ));

    expect($body['data']['attributes'])->not->toHaveKey('delivery_product')
        ->and($body['data']['attributes'])->not->toHaveKey('print_mode')
        ->and($body['data']['attributes'])->not->toHaveKey('meta_data');
});

it('keeps a false auto_send, which is not the same as absent', function (): void {
    $body = decodedBody(new CreateLetterRequest(
        organisationId: 'org-1',
        fileUrl: 'https://storage.example.test/u',
        fileUrlSignature: 'sig',
        fileOriginalName: 'invoice.pdf',
        addressPosition: 'left',
        autoSend: false,
    ));

    expect($body['data']['attributes'])->toHaveKey('auto_send')
        ->and($body['data']['attributes']['auto_send'])->toBeFalse();
});

it('sends meta data when there is some', function (): void {
    $body = decodedBody(new CreateLetterRequest(
        organisationId: 'org-1',
        fileUrl: 'https://storage.example.test/u',
        fileUrlSignature: 'sig',
        fileOriginalName: 'invoice.pdf',
        addressPosition: 'left',
        metaData: ['invoice_id' => 42],
    ));

    expect($body['data']['attributes']['meta_data'])->toBe(['invoice_id' => 42]);
});

it('uses the right resource type per endpoint', function (): void {
    $price = decodedBody(new CalculatePriceRequest('org-1', 'AT', 'cheap', 'simplex', 'grayscale'));
    $webhook = decodedBody(new CreateWebhookRequest('org-1', 'letters', 'https://app.test/hook', 'key'));

    expect($price['data']['type'])->toBe('letters')
        ->and($webhook['data']['type'])->toBe('webhooks')
        ->and($webhook['data']['attributes']['signing_key'])->toBe('key');
});

it('builds the endpoints the API documents', function (): void {
    expect((new CreateLetterRequest('org-1', 'u', 's', 'f.pdf', 'left'))->resolveEndpoint())
        ->toBe('/organisations/org-1/deliveries/letters')
        ->and((new CalculatePriceRequest('org-1', 'AT', 'cheap', 'simplex', 'grayscale'))->resolveEndpoint())
        ->toBe('/organisations/org-1/deliveries/letters/price-calculator')
        ->and((new CreateWebhookRequest('org-1', 'letters', 'https://app.test/hook', 'key'))->resolveEndpoint())
        ->toBe('/organisations/org-1/webhooks');
});

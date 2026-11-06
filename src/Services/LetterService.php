<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services;

use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Data\Letter;
use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\Data\LetterPrice;
use GraystackIT\Pingen\Events\LetterCreated;
use GraystackIT\Pingen\Events\LetterSubmitted;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Requests\Letters\CalculatePriceRequest;
use GraystackIT\Pingen\Requests\Letters\CancelLetterRequest;
use GraystackIT\Pingen\Requests\Letters\CreateLetterRequest;
use GraystackIT\Pingen\Requests\Letters\DeleteLetterRequest;
use GraystackIT\Pingen\Requests\Letters\DownloadLetterFileRequest;
use GraystackIT\Pingen\Requests\Letters\GetLetterRequest;
use GraystackIT\Pingen\Requests\Letters\ListLettersRequest;
use GraystackIT\Pingen\Requests\Letters\SendLetterRequest;
use GraystackIT\Pingen\Requests\Letters\UpdateLetterRequest;
use GraystackIT\Pingen\Services\Concerns\MapsPingenErrors;
use GraystackIT\Pingen\Support\LetterRecorder;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

/**
 * Everything that can be done to a letter.
 *
 * {@see uploadAndCreate()} is the one method most callers need: it performs
 * the three calls the API requires — ask for a slot, put the file in it,
 * create the letter quoting the slot's signature — and gives back the letter.
 */
class LetterService
{
    use MapsPingenErrors;

    /**
     * @param  PingenConnector  $connector  Connector bound to the organisation.
     * @param  FileUploadService  $uploads  Handles the upload half of a send.
     * @param  LetterRecorder  $recorder  Writes letters to the log or the database.
     */
    public function __construct(
        private readonly PingenConnector $connector,
        private readonly FileUploadService $uploads,
        private readonly LetterRecorder $recorder,
    ) {}

    /**
     * Upload a file and create the letter from it.
     *
     * @param  LetterDraft  $draft  What to send.
     *
     * @throws ConfigurationException When the draft names an option the configuration forbids.
     * @throws PingenException When any of the three calls fails.
     */
    public function uploadAndCreate(LetterDraft $draft): Letter
    {
        $options = $draft->resolved();

        $slot = $this->uploads->request();
        $this->uploads->upload($slot, $draft->contents, $draft->contentType);

        try {
            $response = $this->connector->send(new CreateLetterRequest(
                organisationId: $this->connector->organisationId(),
                fileUrl: $slot->url,
                fileUrlSignature: $slot->urlSignature,
                fileOriginalName: $draft->fileName,
                addressPosition: $options['address_position'],
                autoSend: $options['auto_send'],
                deliveryProduct: $options['delivery_product'],
                printMode: $options['print_mode'],
                printSpectrum: $options['print_spectrum'],
                metaData: $draft->metaData,
            ));
        } catch (RequestException $exception) {
            Log::error('Pingen: letter creation failed', [
                'file' => $draft->fileName,
                'status' => $exception->getResponse()->status(),
            ]);

            throw $this->mapException($exception, rejectable: true);
        }

        $letter = Letter::fromResponse((array) $response->json());

        $this->recorder->record($letter, $this->connector->organisationId());

        LetterCreated::dispatch($letter);

        if ($options['auto_send']) {
            LetterSubmitted::dispatch($letter);
        }

        return $letter;
    }

    /**
     * Create a letter from a file already uploaded to a slot.
     *
     * @param  LetterDraft  $draft  Supplies the name and the print options.
     * @param  string  $fileUrl  The signed URL the file was uploaded to.
     * @param  string  $fileUrlSignature  The signature returned with that URL.
     *
     * @throws PingenException When the API refuses.
     */
    public function create(LetterDraft $draft, string $fileUrl, string $fileUrlSignature): Letter
    {
        $options = $draft->resolved();

        try {
            $response = $this->connector->send(new CreateLetterRequest(
                organisationId: $this->connector->organisationId(),
                fileUrl: $fileUrl,
                fileUrlSignature: $fileUrlSignature,
                fileOriginalName: $draft->fileName,
                addressPosition: $options['address_position'],
                autoSend: $options['auto_send'],
                deliveryProduct: $options['delivery_product'],
                printMode: $options['print_mode'],
                printSpectrum: $options['print_spectrum'],
                metaData: $draft->metaData,
            ));
        } catch (RequestException $exception) {
            throw $this->mapException($exception, rejectable: true);
        }

        $letter = Letter::fromResponse((array) $response->json());

        $this->recorder->record($letter, $this->connector->organisationId());

        LetterCreated::dispatch($letter);

        return $letter;
    }

    /**
     * Read one letter.
     *
     * @param  string  $letterId  UUID of the letter.
     *
     * @throws PingenException When the API refuses.
     */
    public function get(string $letterId): Letter
    {
        try {
            $response = $this->connector->send(
                new GetLetterRequest($this->connector->organisationId(), $letterId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return Letter::fromResponse((array) $response->json());
    }

    /**
     * List letters.
     *
     * @param  int|null  $page  Page number, 1-based.
     * @param  int|null  $limit  Letters per page.
     * @param  string|null  $sort  Sort expression, e.g. "-created_at".
     * @param  array<string, scalar>  $filter  Filters, keyed by field name.
     * @return array{data: array<int, Letter>, meta: array<string, mixed>, links: array<string, mixed>}
     *
     * @throws PingenException When the API refuses.
     */
    public function list(?int $page = null, ?int $limit = null, ?string $sort = null, array $filter = []): array
    {
        try {
            $response = $this->connector->send(new ListLettersRequest(
                organisationId: $this->connector->organisationId(),
                page: $page,
                limit: $limit,
                sort: $sort,
                filter: $filter,
            ));
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        return [
            'data' => Letter::collectionFromResponse($body),
            'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : [],
            'links' => is_array($body['links'] ?? null) ? $body['links'] : [],
        ];
    }

    /**
     * Change a draft letter.
     *
     * @param  string  $letterId  UUID of the draft.
     * @param  array<string, mixed>  $attributes  Attributes to change.
     *
     * @throws PingenException When the API refuses.
     */
    public function update(string $letterId, array $attributes): Letter
    {
        try {
            $response = $this->connector->send(
                new UpdateLetterRequest($this->connector->organisationId(), $letterId, $attributes)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception, rejectable: true);
        }

        return Letter::fromResponse((array) $response->json());
    }

    /**
     * Submit a draft letter to production.
     *
     * The print options are required here, because this is the call that
     * decides how the letter is physically produced. They fall back to the
     * configured defaults when not given.
     *
     * @param  string  $letterId  UUID of the draft.
     * @param  string|null  $deliveryProduct  Postal product.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     * @param  array<string, mixed>|null  $metaData  Arbitrary data stored with the letter.
     *
     * @throws ConfigurationException When an option is missing or not allowed.
     * @throws PingenException When the API refuses.
     */
    public function send(
        string $letterId,
        ?string $deliveryProduct = null,
        ?string $printMode = null,
        ?string $printSpectrum = null,
        ?array $metaData = null,
    ): Letter {
        $options = (new LetterDraft(
            contents: '',
            fileName: '',
            autoSend: true,
            deliveryProduct: $deliveryProduct,
            printMode: $printMode,
            printSpectrum: $printSpectrum,
        ))->resolved();

        try {
            $response = $this->connector->send(new SendLetterRequest(
                organisationId: $this->connector->organisationId(),
                letterId: $letterId,
                deliveryProduct: (string) $options['delivery_product'],
                printMode: (string) $options['print_mode'],
                printSpectrum: (string) $options['print_spectrum'],
                metaData: $metaData,
            ));
        } catch (RequestException $exception) {
            Log::error('Pingen: submitting a letter failed', [
                'letter_id' => $letterId,
                'status' => $exception->getResponse()->status(),
            ]);

            throw $this->mapException($exception, rejectable: true);
        }

        $letter = Letter::fromResponse((array) $response->json());

        $this->recorder->record($letter, $this->connector->organisationId());

        LetterSubmitted::dispatch($letter);

        return $letter;
    }

    /**
     * Stop a submitted letter before it is printed.
     *
     * @param  string  $letterId  UUID of the letter.
     *
     * @throws PingenException When the API refuses, including when it is too late.
     */
    public function cancel(string $letterId): void
    {
        try {
            $this->connector->send(
                new CancelLetterRequest($this->connector->organisationId(), $letterId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }
    }

    /**
     * Delete a draft letter.
     *
     * @param  string  $letterId  UUID of the draft.
     *
     * @throws PingenException When the API refuses.
     */
    public function delete(string $letterId): void
    {
        try {
            $this->connector->send(
                new DeleteLetterRequest($this->connector->organisationId(), $letterId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }
    }

    /**
     * Download the letter exactly as it will be printed.
     *
     * @param  string  $letterId  UUID of the letter.
     * @return string The raw file bytes.
     *
     * @throws PingenException When the API refuses.
     */
    public function downloadFile(string $letterId): string
    {
        try {
            $response = $this->connector->send(
                new DownloadLetterFileRequest($this->connector->organisationId(), $letterId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return $response->body();
    }

    /**
     * Ask what a letter would cost, without creating one.
     *
     * @param  string  $country  Destination country, ISO 3166-1 alpha-2.
     * @param  string|null  $deliveryProduct  Postal product.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     * @param  array<int, mixed>  $paperTypes  Paper type per sheet.
     *
     * @throws ConfigurationException When an option is missing or not allowed.
     * @throws PingenException When the API refuses.
     */
    public function calculatePrice(
        string $country,
        ?string $deliveryProduct = null,
        ?string $printMode = null,
        ?string $printSpectrum = null,
        array $paperTypes = [],
    ): LetterPrice {
        $options = (new LetterDraft(
            contents: '',
            fileName: '',
            autoSend: true,
            deliveryProduct: $deliveryProduct,
            printMode: $printMode,
            printSpectrum: $printSpectrum,
        ))->resolved();

        try {
            $response = $this->connector->send(new CalculatePriceRequest(
                organisationId: $this->connector->organisationId(),
                country: $country,
                deliveryProduct: (string) $options['delivery_product'],
                printMode: (string) $options['print_mode'],
                printSpectrum: (string) $options['print_spectrum'],
                paperTypes: $paperTypes,
            ));
        } catch (RequestException $exception) {
            throw $this->mapException($exception, rejectable: true);
        }

        return LetterPrice::fromResponse((array) $response->json());
    }
}

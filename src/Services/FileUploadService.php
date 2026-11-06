<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services;

use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Connectors\UploadConnector;
use GraystackIT\Pingen\Data\FileUpload;
use GraystackIT\Pingen\Exceptions\FileUploadExpiredException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Requests\FileUpload\RequestFileUploadRequest;
use GraystackIT\Pingen\Requests\FileUpload\UploadFileRequest;
use GraystackIT\Pingen\Services\Concerns\MapsPingenErrors;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

/**
 * Requests upload slots and puts files into them.
 *
 * Two connectors, deliberately. Asking for a slot is an authenticated API call
 * and goes through {@see PingenConnector}; the upload itself goes to object
 * storage through {@see UploadConnector}, which carries no credentials at all.
 */
class FileUploadService
{
    use MapsPingenErrors;

    /**
     * @param  PingenConnector  $connector  Authenticated connector for the API call.
     * @param  UploadConnector  $uploads  Credential-free connector for the upload itself.
     */
    public function __construct(
        private readonly PingenConnector $connector,
        private readonly UploadConnector $uploads,
    ) {}

    /**
     * Ask Pingen for a slot to upload one file into.
     *
     * @throws PingenException When the API refuses.
     */
    public function request(): FileUpload
    {
        try {
            $response = $this->connector->send(new RequestFileUploadRequest);
        } catch (RequestException $exception) {
            Log::error('Pingen: could not request a file upload slot', [
                'status' => $exception->getResponse()->status(),
            ]);

            throw $this->mapException($exception);
        }

        return FileUpload::fromResponse((array) $response->json());
    }

    /**
     * Put the file bytes into a slot.
     *
     * @param  FileUpload  $slot  The slot to upload into.
     * @param  string  $contents  The raw file bytes.
     * @param  string  $contentType  Media type of the file.
     *
     * @throws FileUploadExpiredException When the slot has already expired.
     * @throws PingenException When the storage host refuses the file.
     */
    public function upload(FileUpload $slot, string $contents, string $contentType = 'application/pdf'): void
    {
        if ($slot->hasExpired()) {
            throw new FileUploadExpiredException(
                'The Pingen upload URL expired before the file was sent. Request a new one.'
            );
        }

        try {
            $this->uploads->send(new UploadFileRequest($slot->url, $contents, $contentType));
        } catch (RequestException $exception) {
            Log::error('Pingen: file upload failed', [
                'status' => $exception->getResponse()->status(),
            ]);

            throw $this->mapException($exception);
        }
    }
}

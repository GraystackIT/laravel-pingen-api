<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\FileUpload;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasStringBody;

/**
 * Puts the file bytes into the signed upload slot.
 *
 * Sent through {@see \GraystackIT\Pingen\Connectors\UploadConnector}, which
 * carries no credentials: the signature in the URL is the authorisation, and
 * the host is object storage rather than Pingen.
 *
 * The content type is `application/pdf` rather than JSON:API — this request is
 * not part of the API proper, it is a plain upload.
 */
class UploadFileRequest extends Request implements HasBody
{
    use HasStringBody;

    protected Method $method = Method::PUT;

    /**
     * @param  string  $url  The absolute signed upload URL.
     * @param  string  $contents  The raw file bytes.
     * @param  string  $contentType  Media type of the file.
     */
    public function __construct(
        private readonly string $url,
        private readonly string $contents,
        private readonly string $contentType = 'application/pdf',
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->url;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Content-Type' => $this->contentType];
    }

    protected function defaultBody(): ?string
    {
        return $this->contents;
    }
}

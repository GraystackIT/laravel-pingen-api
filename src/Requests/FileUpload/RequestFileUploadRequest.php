<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\FileUpload;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Asks Pingen for a slot to upload a file into.
 *
 * Answers with a signed URL, the signature to quote back when the letter is
 * created, and an expiry. This is the only endpoint in the API that is not
 * scoped to an organisation.
 */
class RequestFileUploadRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/file-upload';
    }
}

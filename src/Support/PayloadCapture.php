<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes verified webhook bodies to disk so real payloads can become fixtures.
 *
 * Runs only in the local and testing environments, and that restriction is not
 * a convenience. A Pingen payload carries a recipient's postal address, which
 * is personal data; nothing prunes these files; and the directory is usually
 * inside a repository. A capture path configured on a production system is
 * therefore ignored rather than obeyed.
 */
final class PayloadCapture
{
    /**
     * Write the request body, if capturing is switched on and permitted here.
     *
     * @param  Request  $request  The verified inbound request.
     * @param  string  $label  Short name for the file, usually the event category.
     */
    public static function write(Request $request, string $label = 'webhook'): void
    {
        $path = config('pingen.capture_path');

        if (! is_string($path) || trim($path) === '') {
            return;
        }

        if (! app()->environment(['local', 'testing'])) {
            Log::warning('Pingen: ignoring PINGEN_CAPTURE_PATH outside local and testing.');

            return;
        }

        try {
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }

            $name = sprintf('%s-%s-%s.json', date('Ymd-His'), Str::slug($label) ?: 'webhook', Str::random(6));

            file_put_contents(rtrim($path, '/\\').DIRECTORY_SEPARATOR.$name, $request->getContent());
        } catch (Throwable $exception) {
            Log::warning('Pingen: could not capture the webhook payload', ['error' => $exception->getMessage()]);
        }
    }
}

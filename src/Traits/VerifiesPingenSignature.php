<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the signature on an inbound Pingen webhook.
 *
 * Pingen signs a delivery with `Signature: hash_hmac('sha256', body, key)` as
 * plain hex. That is simpler than the Standard Webhooks scheme other providers
 * use, and weaker in one specific way worth stating plainly: **nothing
 * time-bound is signed**. There is no timestamp inside the signed content, so
 * a signature never expires and a delivery recorded off the wire stays valid
 * forever. Verification alone therefore proves the body came from Pingen at
 * some point — not that it is arriving for the first time.
 *
 * The replay guard that closes this is the unique delivery id in
 * {@see \GraystackIT\Pingen\Models\PingenWebhookEvent}. The two belong
 * together; verifying without it leaves the endpoint repeatable by anyone who
 * ever captured one request.
 */
trait VerifiesPingenSignature
{
    /**
     * Whether the request carries a valid signature for the given key.
     *
     * An empty key disables verification, which is only ever appropriate on a
     * local machine.
     *
     * @param  Request  $request  The inbound request.
     * @param  string|null  $secret  The webhook signing key.
     */
    protected function signatureIsValid(Request $request, ?string $secret): bool
    {
        if ($secret === null || trim($secret) === '') {
            return true;
        }

        $signature = $request->header('Signature');

        if (! is_string($signature) || $signature === '') {
            Log::warning('Pingen webhook: the Signature header is missing', ['ip' => $request->ip()]);

            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, trim($signature))) {
            Log::warning('Pingen webhook: the signature does not match the signing key', ['ip' => $request->ip()]);

            return false;
        }

        return true;
    }
}

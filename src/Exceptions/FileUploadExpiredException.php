<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

/**
 * Thrown when a signed upload URL is used after it expired.
 *
 * Pingen hands out an upload slot that is valid for a short while. A queued
 * send can easily outlive it — the slot is requested when the job is built and
 * used when a worker picks it up — so this is checked before the bytes are
 * sent rather than after the storage host rejects them. The caller recovers by
 * requesting a new slot, never by retrying the same one.
 */
class FileUploadExpiredException extends PingenException {}

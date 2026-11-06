<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

/**
 * Thrown when Pingen refuses a letter outright.
 *
 * Separate from the generic exception because a refusal is the caller's
 * problem to fix — an unreadable PDF, a page size that cannot be printed, an
 * address window with nothing in it — while a 500 is ours. An application that
 * meters sends needs to tell the two apart: only one of them should give a
 * reserved allowance back and stop retrying.
 */
class LetterRejectedException extends PingenException {}

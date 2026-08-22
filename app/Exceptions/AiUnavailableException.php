<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The AI layer could not answer.
 *
 * Always carries a message safe to show the user directly, because the product
 * rule is that AI failure is a visible, non-blocking state rather than an
 * error page.
 */
class AiUnavailableException extends RuntimeException {}

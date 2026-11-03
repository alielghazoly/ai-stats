<?php

namespace App\Ai;

use RuntimeException;

/** Thrown when the AI API itself is unavailable or returned an error. */
class AiUnavailable extends RuntimeException
{
}

<?php

namespace App\Ai;

use RuntimeException;

/** بيتعمل throw لما الـ AI API نفسه مش متاح أو رجّع خطأ. */
class AiUnavailable extends RuntimeException
{
}

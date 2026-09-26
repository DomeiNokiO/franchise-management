<?php

namespace App\Support;

/** Format mata uang Indonesia, ringkas untuk view. */
final class Money
{
    public static function id(float|string|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}

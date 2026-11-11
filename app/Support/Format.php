<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class Format
{
    public static function date(mixed $value, string $format = 'd M Y, H:i'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        return $date->timezone(config('plantgrid.display_timezone'))->format($format);
    }
}

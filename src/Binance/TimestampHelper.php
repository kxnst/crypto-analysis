<?php

declare(strict_types=1);

namespace App\Binance;

class TimestampHelper
{
    public const UNIT_MAP = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 86400,
        'w' => 604800,
        'M' => 2592000
    ];

    public const SECONDS_PER_DAY = 86400;

    public static function convertToSeconds(string $interval): int
    {
        $value = (int)substr($interval, 0, -1);
        $unit = substr($interval, -1);

        return $value * (self::UNIT_MAP[$unit] ?? throw new \InvalidArgumentException('Format not supported'));
    }

    public static function convertToMicroseconds(string $interval): int
    {
        return self::convertToSeconds($interval) * 1000;
    }
}

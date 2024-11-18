<?php

declare(strict_types=1);

namespace App\Analyzer\Presentation;

class Signal
{
    public const DIRECTION_UP = 1;
    public const DIRECTION_DOWN = -1;

    public function __construct(
        public readonly string $from,
        public readonly int    $direction,
        public readonly string $identifier,
        public readonly string $explanation,
        public readonly int    $timeStamp
    )
    {
    }
}

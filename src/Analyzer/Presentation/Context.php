<?php

declare(strict_types=1);

namespace App\Analyzer\Presentation;

readonly class Context
{
    public function __construct(
        public string $from,
        public int    $direction,
        public array  $tags,
        public int    $timeStamp
    )
    {
    }
}

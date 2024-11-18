<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CandleDTO
{
    public function __construct(
        public float  $openPrice,
        public float  $closePrice,
        public float  $highestBid,
        public float  $lowestBid,
        public int    $timeStamp,
        public float  $volume,
        public string $timeframe
    )
    {
    }

    public function getPrice(): float
    {
        return ($this->openPrice + $this->closePrice + $this->highestBid + $this->lowestBid) / 4;
    }


}

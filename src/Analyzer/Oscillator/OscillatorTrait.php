<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

use App\Binance\TimestampHelper;

trait OscillatorTrait
{
    protected int $requiredDays;

    protected function initialize(int $requiredDays): void
    {
        $this->requiredDays = $requiredDays;
    }

    public function getRequiredPreload(string $timeframe): int
    {
        $secondsPerCandle = TimestampHelper::convertToSeconds($timeframe);
        $secondsRequired = $this->requiredDays * TimestampHelper::SECONDS_PER_DAY;

        return (int)ceil($secondsRequired / $secondsPerCandle);
    }

    protected function getCandlesPerPeriod(string $timeframe, float $periodDays): int
    {
        $secondsPerCandle = TimestampHelper::convertToSeconds($timeframe);
        $candlesPerDay = TimestampHelper::SECONDS_PER_DAY / $secondsPerCandle;

        return (int)round($periodDays * $candlesPerDay);
    }
}

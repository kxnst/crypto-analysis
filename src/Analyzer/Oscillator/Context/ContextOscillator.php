<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator\Context;

use App\Analyzer\Oscillator\InternalAnalyzerResult;
use App\Analyzer\Oscillator\OscillatorInterface;
use App\Analyzer\Oscillator\OscillatorTrait;
use App\Analyzer\Presentation\Context;
use App\DTO\CandleDTO;

class ContextOscillator implements OscillatorInterface
{
    use OscillatorTrait;

    private array $tags = [];

    protected int $requiredDays;

    public function __construct()
    {
        // Встановлюємо необхідну кількість днів для аналізу
        $this->requiredDays = 30; // Аналізуємо останні 30 днів
        $this->initialize($this->requiredDays);
    }

    public function process(InternalAnalyzerResult $result): void
    {
        $candles = $result->getCandles();
        $currentCandle = $result->getCurrentCandle();
        $timeframe = $result->getTimeframe();

        $currentIndex = $result->getCurrentIndex();

        $this->tags = []; // Очищаємо теги перед новим аналізом

        // Визначаємо контекстні теги
        $this->determineVolatility($candles, $currentIndex, $timeframe);
        $this->determineTrend($candles, $currentIndex, $timeframe);
        $this->determineBreakouts($candles, $currentIndex, $timeframe);
        $this->determineConsolidation($candles, $currentIndex, $timeframe);
        $this->determineVolumeSpike($candles, $currentIndex, $timeframe);
        $this->determineMarketNoise($candles, $currentIndex, $timeframe);

        // Створюємо об'єкт Context
        $context = new Context(
            from: 'ContextOscillator',
            direction: 0, // 0, оскільки це контекст, а не сигнал
            tags: $this->tags,
            timeStamp: $currentCandle->timeStamp
        );

        // Додаємо контекст до результатів аналізу
        $result->addToContext($context);
    }

    private function determineVolatility(array $candles, int $currentIndex, string $timeframe): void
    {
        $periodDays = 14; // Період у днях
        $periodCandles = $this->getCandlesPerPeriod($timeframe, $periodDays);

        if ($currentIndex < $periodCandles) {
            return;
        }

        $volatilities = [];
        for ($i = $currentIndex - $periodCandles + 1; $i <= $currentIndex; $i++) {
            $candle = $candles[$i];
            $volatility = ($candle->highestBid - $candle->lowestBid) / $candle->lowestBid * 100;
            $volatilities[] = $volatility;
        }

        $averageVolatility = array_sum($volatilities) / count($volatilities);

        // Порівнюємо останню волатильність із середньою
        $lastVolatility = $volatilities[count($volatilities) - 1];

        if ($lastVolatility > $averageVolatility * 1.5) {
            $this->tags[] = 'HIGH_VOLATILITY';
        } elseif ($lastVolatility < $averageVolatility * 0.5) {
            $this->tags[] = 'LOW_VOLATILITY';
        }
    }

    private function determineTrend(array $candles, int $currentIndex, string $timeframe): void
    {
        $shortPeriodDays = 7;
        $longPeriodDays = 21;

        $shortPeriodCandles = $this->getCandlesPerPeriod($timeframe, $shortPeriodDays);
        $longPeriodCandles = $this->getCandlesPerPeriod($timeframe, $longPeriodDays);

        if ($currentIndex < $longPeriodCandles) {
            return;
        }

        $closePrices = array_map(fn(CandleDTO $candle) => $candle->closePrice, $candles);

        $shortMA = array_sum(
            array_slice(
                $closePrices,
                $currentIndex - $shortPeriodCandles + 1, $shortPeriodCandles)
            ) / $shortPeriodCandles;
        $longMA = array_sum(
            array_slice(
                $closePrices,
                $currentIndex - $longPeriodCandles + 1, $longPeriodCandles)
            ) / $longPeriodCandles;

        if ($shortMA > $longMA) {
            $this->tags[] = 'UPWARD_TREND';
        } elseif ($shortMA < $longMA) {
            $this->tags[] = 'DOWNWARD_TREND';
        } else {
            $this->tags[] = 'SIDEWAYS_TREND';
        }
    }

    private function determineBreakouts(array $candles, int $currentIndex, string $timeframe): void
    {
        $periodDays = 14;
        $periodCandles = $this->getCandlesPerPeriod($timeframe, $periodDays);

        if ($currentIndex < $periodCandles) {
            return;
        }

        $highPrices = array_map(fn(CandleDTO $candle) => $candle->highestBid, $candles);
        $lowPrices = array_map(fn(CandleDTO $candle) => $candle->lowestBid, $candles);

        $recentHigh = max(array_slice($highPrices, $currentIndex - $periodCandles + 1, $periodCandles));
        $recentLow = min(array_slice($lowPrices, $currentIndex - $periodCandles + 1, $periodCandles));

        $currentClose = $candles[$currentIndex]->closePrice;

        if ($currentClose > $recentHigh) {
            $this->tags[] = 'RESISTANCE_BREAKOUT';
        } elseif ($currentClose < $recentLow) {
            $this->tags[] = 'SUPPORT_BREAKOUT';
        }
    }

    private function determineConsolidation(array $candles, int $currentIndex, string $timeframe): void
    {
        $periodDays = 14;
        $periodCandles = $this->getCandlesPerPeriod($timeframe, $periodDays);

        if ($currentIndex < $periodCandles) {
            return;
        }

        $trs = [];

        for ($i = $currentIndex - $periodCandles + 1; $i <= $currentIndex; $i++) {
            $currentHigh = $candles[$i]->highestBid;
            $currentLow = $candles[$i]->lowestBid;
            $prevClose = $candles[$i - 1]->closePrice;

            $tr = max(
                $currentHigh - $currentLow,
                abs($currentHigh - $prevClose),
                abs($currentLow - $prevClose)
            );
            $trs[] = $tr;
        }

        // Розраховуємо діапазон цін за період
        $priceRange = [];
        for ($i = $currentIndex - $periodCandles + 1; $i <= $currentIndex; $i++) {
            $priceRange[] = $candles[$i]->closePrice;
        }

        $maxPrice = max($priceRange);
        $minPrice = min($priceRange);

        // Якщо діапазон цін менший за певний відсоток від поточної ціни, це консолідація
        $currentPrice = $candles[$currentIndex]->closePrice;
        $rangePercent = (($maxPrice - $minPrice) / $currentPrice) * 100;

        if ($rangePercent < 2) { // Порогове значення 2%
            $this->tags[] = 'CONSOLIDATION';
        }
    }

    private function determineVolumeSpike(array $candles, int $currentIndex, string $timeframe): void
    {
        $periodDays = 14;
        $periodCandles = $this->getCandlesPerPeriod($timeframe, $periodDays);

        if ($currentIndex < $periodCandles) {
            return;
        }

        $volumes = [];
        for ($i = $currentIndex - $periodCandles + 1; $i <= $currentIndex; $i++) {
            $volumes[] = $candles[$i]->volume;
        }

        $averageVolume = array_sum($volumes) / count($volumes);

        $currentVolume = $candles[$currentIndex]->volume;

        if ($currentVolume > $averageVolume * 2) {
            $this->tags[] = 'VOLUME_SPIKE';
        }
    }

    private function determineMarketNoise(array $candles, int $currentIndex, string $timeframe): void
    {
        $volatilityPeriodDays = 14;
        $volatilityPeriodCandles = $this->getCandlesPerPeriod($timeframe, $volatilityPeriodDays);

        $trendPeriodDays = 7;
        $trendPeriodCandles = $this->getCandlesPerPeriod($timeframe, $trendPeriodDays);

        $requiredCandles = max($volatilityPeriodCandles, $trendPeriodCandles);

        if ($currentIndex < $requiredCandles) {
            return;
        }

        // Розраховуємо волатильність
        $volatilities = [];
        for ($i = $currentIndex - $volatilityPeriodCandles + 1; $i <= $currentIndex; $i++) {
            $volatility = ($candles[$i]->highestBid - $candles[$i]->lowestBid) / $candles[$i]->lowestBid * 100;
            $volatilities[] = $volatility;
        }

        $averageVolatility = array_sum($volatilities) / count($volatilities);

        // Розраховуємо тренд
        $closePrices = array_map(fn(CandleDTO $candle) => $candle->closePrice, $candles);
        $trend = $closePrices[$currentIndex] - $closePrices[$currentIndex - $trendPeriodCandles];

        // Якщо волатильність висока, але тренд слабкий, це може бути ринковий шум
        if ($averageVolatility > 1 && abs($trend) / $closePrices[$currentIndex - $trendPeriodCandles] * 100 < 0.5) {
            $this->tags[] = 'MARKET_NOISE';
        }
    }
}

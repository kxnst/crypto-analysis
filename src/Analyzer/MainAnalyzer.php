<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Oscillator\OscillatorInterface;
use App\Analyzer\Oscillator\InternalAnalyzerResult;
use App\Binance\TimestampHelper;
use App\Repository\BinanceDataApiRepository;

class MainAnalyzer
{
    /**
     * @var OscillatorInterface[]
     */
    private array $oscillators;

    public function __construct(
        private readonly BinanceDataApiRepository $binanceDataApiRepository,
        array $oscillators = []
    )
    {
        $this->oscillators = $oscillators;
    }

    public function addOscillator(OscillatorInterface $oscillator): void
    {
        $this->oscillators[] = $oscillator;
    }

    public function analyze(
        string $symbol,
        string $timeframe,
        int    $timestampFrom,
        int    $timestampTo,
    ): AnalyzerResult
    {
        $preload = $this->getMaxRequiredPreload($timeframe);

        $candles = $this->binanceDataApiRepository->getData(
            $symbol,
            $timeframe,
            $timestampFrom - ($preload * TimestampHelper::convertToMicroseconds($timeframe)),
            $timestampTo
        );

        $firstCandle = $this->binanceDataApiRepository->getFirstNode($candles, $timestampFrom);

        $internalResult = new InternalAnalyzerResult(
            candles: $candles,
            currentCandle: $firstCandle,
            context: [],
            signals: [],
            timeframe: $timeframe
        );

        $search = array_slice($candles, array_search($firstCandle, $candles));
        foreach ($search as $currentCandle) {
            $internalResult->setCurrentCandle($currentCandle);

            foreach ($this->oscillators as $oscillator) {
                $oscillator->process($internalResult);
            }
        }

        $slicedCandles = array_slice($candles, array_search($firstCandle, $candles));

        return new AnalyzerResult(
            candles: $slicedCandles,
            context: $internalResult->getContext(),
            signals: $internalResult->getSignals()
        );
    }

    /**
     * @param string $timeframe
     * @return int Максимальна кількість свічок, необхідних для всіх осциляторів
     */
    public function getMaxRequiredPreload(string $timeframe): int
    {
        return max(
            array_map(
                fn(OscillatorInterface $oscillator) => $oscillator->getRequiredPreload($timeframe),
                $this->oscillators
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

use App\Analyzer\Presentation\Signal;
use App\DTO\CandleDTO;

class MACD implements OscillatorInterface
{
    use OscillatorTrait;

    private float $shortPeriodDays;
    private float $longPeriodDays;
    private float $signalPeriodDays;

    private int $shortPeriodCandles;
    private int $longPeriodCandles;
    private int $signalPeriodCandles;

    private array $emaShort = [];
    private array $emaLong = [];
    private array $macdLine = [];
    private array $signalLine = [];

    public function __construct(float $shortPeriodDays = 12, float $longPeriodDays = 26, float $signalPeriodDays = 9)
    {
        // Визначаємо максимальний період для необхідних днів
        $maxPeriodDays = max($shortPeriodDays, $longPeriodDays) + $signalPeriodDays;
        $this->initialize((int)$maxPeriodDays);

        $this->shortPeriodDays = $shortPeriodDays;
        $this->longPeriodDays = $longPeriodDays;
        $this->signalPeriodDays = $signalPeriodDays;
    }

    public function process(InternalAnalyzerResult $result): void
    {
        $candles = $result->getCandles();
        $currentCandle = $result->getCurrentCandle();
        $timeframe = $result->getTimeframe();

        // Перевіряємо, чи вже розраховані періоди в свічках
        if (!isset($this->shortPeriodCandles)) {
            $this->shortPeriodCandles = $this->getCandlesPerPeriod($timeframe, $this->shortPeriodDays);
            $this->longPeriodCandles = $this->getCandlesPerPeriod($timeframe, $this->longPeriodDays);
            $this->signalPeriodCandles = $this->getCandlesPerPeriod($timeframe, $this->signalPeriodDays);
        }

        $currentIndex = $result->getCurrentIndex();

        $emaShort = $this->getEma(
            $currentIndex,
            $this->shortPeriodCandles,
            $this->shortPeriodDays,
            $this->emaLong,
            $candles
        );
        $emaLong = $this->getEma(
            $currentIndex,
            $this->longPeriodCandles,
            $this->longPeriodDays,
            $this->emaShort,
            $candles
        );

        // Розраховуємо лінію MACD
        if (isset($this->emaShort[$currentIndex]) && isset($this->emaLong[$currentIndex])) {
            $this->macdLine[$currentIndex] = $emaShort - $emaLong;
        } else {
            $this->macdLine[$currentIndex] = null;
        }

        $signalLine = $this->getSignalLine(
            $currentIndex
        );

        // Перевіряємо наявність сигналу
        if ($currentIndex >= 1) {
            $prevMacd = $this->macdLine[$currentIndex - 1] ?? null;
            $currentMacd = $this->macdLine[$currentIndex];
            $prevSignal = $this->signalLine[$currentIndex - 1] ?? null;
            $currentSignal = $signalLine;

            if ($prevMacd && $currentMacd && $prevSignal) {
                $timeStamp = $currentCandle->timeStamp;

                // Бичачий перетин
                if ($prevMacd < $prevSignal && $currentMacd > $currentSignal) {
                    $signal = new Signal(
                        from: 'MACD',
                        direction: Signal::DIRECTION_UP,
                        identifier: 'MACD_BULLISH_CROSSOVER',
                        explanation: 'MACD лінія перетнула сигнальну лінію знизу вгору на '
                        . date('Y-m-d H:i:s', $timeStamp),
                        timeStamp: $timeStamp
                    );
                    $signals = $result->getSignals();
                    $signals[] = $signal;
                    $result->setSignals($signals);
                }

                // Ведмежий перетин
                if ($prevMacd > $prevSignal && $currentMacd < $currentSignal) {
                    $signal = new Signal(
                        from: 'MACD',
                        direction: Signal::DIRECTION_DOWN,
                        identifier: 'MACD_BEARISH_CROSSOVER',
                        explanation: 'MACD лінія перетнула сигнальну лінію зверху вниз на '
                        . date('Y-m-d H:i:s', $timeStamp),
                        timeStamp: $timeStamp
                    );
                    $signals = $result->getSignals();
                    $signals[] = $signal;
                    $result->setSignals($signals);
                }
            }
        }
    }

    /**
     * @param CandleDTO[] $prices
     */
    private function getEma(
        int       $currentIndex,
        float|int $count,
        float|int $n,
        array     &$values,
        array     $prices,
        ?float    $currentPrice = null
    ): float
    {
        $lambda = 2 / ($n + 1);
        if (!isset($values[$currentIndex - 1])) {
            $prevValues = [];
            for ($i = $currentIndex - $count; $i < $currentIndex; $i++) {
                $prevValues[] = $prices[$i]?->getPrice();
            }

            $prevValues = array_values($prevValues);
            $values[$currentIndex - 1] = array_sum($prevValues) / count($prevValues);
        }

        $prevValue = $values[$currentIndex - 1];

        $newEma = $lambda * ($currentPrice ?? $prices[$currentIndex]->getPrice()) + (1 - $lambda) * $prevValue;

        $values[$currentIndex] = $newEma;

        return $newEma;
    }

    private function getSignalLine(int $currentIndex): float
    {
        $lambda = 2 / (1 + $this->signalPeriodDays);

        if (!isset($this->signalLine[$currentIndex - 1])) {
            $macdStart = $currentIndex - $this->signalPeriodCandles - 1;
            $macdEnd = $currentIndex - 1;

            $macds = [];
            for ($i = $macdStart; $i < $macdEnd; $i++) {
                $macds[] = $this->macdLine[$i] ?? null;
            }
            $macds = array_values($macds);

            $this->signalLine[$currentIndex - 1] = array_sum($macds) / count($macds);
        }

        $prevSignalLine = $this->signalLine[$currentIndex - 1];

        $currentSignalLine = $lambda * $this->macdLine[$currentIndex] + (1 - $lambda) * $prevSignalLine;
        $this->signalLine[$currentIndex] = $currentSignalLine;

        return $currentSignalLine;
    }
}

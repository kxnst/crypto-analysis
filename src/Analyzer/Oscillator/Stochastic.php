<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

use App\Analyzer\Presentation\Signal;
use App\DTO\CandleDTO;

class Stochastic implements OscillatorInterface
{
    use OscillatorTrait;

    private float $kPeriodDays;
    private float $dPeriodDays;

    private int $kPeriodCandles;
    private int $dPeriodCandles;

    private array $kValues = [];

    public function __construct(float $kPeriodDays = 14, float $dPeriodDays = 3)
    {
        $maxPeriodDays = $kPeriodDays + $dPeriodDays;
        $this->initialize((int)$maxPeriodDays);

        $this->kPeriodDays = $kPeriodDays;
        $this->dPeriodDays = $dPeriodDays;
    }

    public function process(InternalAnalyzerResult $result): void
    {
        $candles = $result->getCandles();
        $currentCandle = $result->getCurrentCandle();
        $timeframe = $result->getTimeframe();

        // Перевіряємо, чи вже розраховані періоди в свічках
        if (!isset($this->kPeriodCandles)) {
            $this->kPeriodCandles = $this->getCandlesPerPeriod($timeframe, $this->kPeriodDays);
            $this->dPeriodCandles = $this->getCandlesPerPeriod($timeframe, $this->dPeriodDays);
        }

        $currentIndex = $result->getCurrentIndex();

        $dStartIndex = $currentIndex - $this->dPeriodCandles;
        $kStartIndex = $currentIndex - $this->kPeriodCandles;

        if (min($dStartIndex, $kStartIndex) < 0) {
            return;
        }

        $start = $currentIndex - $this->kPeriodCandles;

        for ($i = $start; $i <= $currentIndex; $i++) {
            if (isset($this->kValues[$i])) {
                continue;
            }

            $kValueStart = $i - $this->kPeriodCandles;

            if ($kValueStart < 0) {
                continue;
            }
            $kPrices = [];
            for ($k = $kValueStart; $k <= $i; $k++) {
                $kPrices[]= $candles[$k]->getPrice();
            }
            $kMin = min($kPrices);
            $kMax = max($kPrices);

            if ($kMin === $kMax) {
                $this->kValues[$i] = 0.5;
            } else {
                $this->kValues[$i] = ($candles[$i]->getPrice() - $kMin) / ($kMax - $kMin) * 100;
            }
        }

        $dValues = [];
        for ($n = $currentIndex - $this->dPeriodCandles; $n<$currentIndex; $n++ ) {
            $dValues[]=$this->kValues[$n];
        }
        $d = array_sum($dValues) / $this->dPeriodCandles;
        $k = $this->kValues[$currentIndex];

        if (!($k && $d)) {
            return;
        }
        $timeStamp = $currentCandle->timeStamp;

        if ($k < 20 && $d < 20) {
            $signal = new Signal(
                from: 'Stochastic',
                direction: Signal::DIRECTION_UP,
                identifier: 'STOCHASTIC_BULLISH',
                explanation: 'Stochastic вказує на бичачий сигнал на ' . date('Y-m-d H:i:s', $timeStamp),
                timeStamp: $timeStamp
            );
            $signals = $result->getSignals();
            $signals[] = $signal;
            $result->setSignals($signals);
        }

        if ($k > 80 && $d > 80) {
            $signal = new Signal(
                from: 'Stochastic',
                direction: Signal::DIRECTION_DOWN,
                identifier: 'STOCHASTIC_BEARISH',
                explanation: 'Stochastic вказує на ведмежий сигнал на ' . date('Y-m-d H:i:s', $timeStamp),
                timeStamp: $timeStamp
            );
            $signals = $result->getSignals();
            $signals[] = $signal;
            $result->setSignals($signals);
        }
    }
}

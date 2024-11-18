<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

use App\Analyzer\Presentation\Signal;

class RSI implements OscillatorInterface
{
    use OscillatorTrait;

    private float $periodDays;
    private int $periodCandles;

    public function __construct(float $periodDays = 14)
    {
        $this->initialize((int)$periodDays + 1); // +1 для початкового розрахунку

        $this->periodDays = $periodDays;
    }

    public function process(InternalAnalyzerResult $result): void
    {
        $candles = $result->getCandles();
        $currentCandle = $result->getCurrentCandle();
        $timeframe = $result->getTimeframe();

        $currentIndex = $result->getCurrentIndex();
        // Перевіряємо, чи вже розрахований період в свічках
        if (!isset($this->periodCandles)) {
            $this->periodCandles = $this->getCandlesPerPeriod($timeframe, $this->periodDays);
        }

        $candlesPerDay = $this->periodCandles / $this->periodDays;

        $losses = $gains = [];

        $prevPrice = $candles[$currentIndex - $this->periodCandles - 1];
        for ($i = $currentIndex - $this->periodCandles; $i < $currentIndex; $i++) {
            if ($candles[$i]->closePrice > $prevPrice->closePrice) {
                $gains[] = $candles[$i]->closePrice - $prevPrice->closePrice;
            } else {
                $losses[] = $prevPrice->closePrice - $candles[$i]->closePrice;
            }

            $prevPrice = $candles[$i];
        }

        $gains = array_sum($gains);
        $losses = array_sum($losses);

        $rs = $losses ? $gains / $losses : 5;

        $rsi = 100 - (100 / (1 + $rs));

        $timeStamp = $currentCandle->timeStamp;
        if ($rsi < 30) {
            $signal = new Signal(
                from: 'RSI',
                direction: Signal::DIRECTION_UP,
                identifier: 'RSI_OVERSOLD',
                explanation: 'RSI вказує на перепроданість на ' . date('Y-m-d H:i:s', $timeStamp),
                timeStamp: $timeStamp
            );
            $signals = $result->getSignals();
            $signals[] = $signal;
            $result->setSignals($signals);
        } elseif ($rsi > 70) {
            $signal = new Signal(
                from: 'RSI',
                direction: Signal::DIRECTION_DOWN,
                identifier: 'RSI_OVERBOUGHT',
                explanation: 'RSI вказує на перекупленість на ' . date('Y-m-d H:i:s', $timeStamp),
                timeStamp: $timeStamp
            );
            $signals = $result->getSignals();
            $signals[] = $signal;
            $result->setSignals($signals);
        }
    }

}

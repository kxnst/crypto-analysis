<?php

declare(strict_types=1);

namespace App\Analyzer;

class AnalyzerResult
{
    private array $candles;

    private array $context;

    private array $signals;

    public function __construct(array $candles, array $context, array $signals)
    {
        $this->candles = $candles;
        $this->context = $context;
        $this->signals = $signals;
    }

    public function getCandles(): array
    {
        return $this->candles;
    }

    public function setCandles(array $candles): void
    {
        $this->candles = $candles;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getSignals(): array
    {
        return $this->signals;
    }

    public function setSignals(array $signals): void
    {
        $this->signals = $signals;
    }
}

<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

use App\Analyzer\Presentation\Context;
use App\Analyzer\Presentation\Signal;
use App\DTO\CandleDTO;

class InternalAnalyzerResult
{
    /** @var CandleDTO[] */
    private array $candles;

    private ?CandleDTO $currentCandle;

    /** @var Context[] */
    private array $context;

    /** @var Signal[] */
    private array $signals;

    private string $timeframe;

    private int $currentIndex;

    public function __construct(
        array      $candles = [],
        ?CandleDTO $currentCandle = null,
        array      $context = [],
        array      $signals = [],
        string     $timeframe = ''
    )
    {
        $this->candles = $candles;
        $this->currentCandle = $currentCandle;
        $this->context = $context;
        $this->signals = $signals;
        $this->timeframe = $timeframe;
        $this->currentIndex = array_search($currentCandle, $candles, true) ?: -1;
    }

    /**
     * @return CandleDTO[]
     */
    public function getCandles(): array
    {
        return $this->candles;
    }

    public function setCandles(array $candles): void
    {
        $this->candles = $candles;
    }

    public function getCurrentCandle(): ?CandleDTO
    {
        return $this->currentCandle;
    }

    public function setCurrentCandle(?CandleDTO $currentCandle): void
    {
        $this->currentIndex = array_search($currentCandle, $this->candles);
        $this->currentCandle = $currentCandle;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function addToContext(Context $context): void
    {
        $this->context[] = $context;
    }

    public function getSignals(): array
    {
        return $this->signals;
    }

    public function setSignals(array $signals): void
    {
        $this->signals = $signals;
    }

    public function getTimeframe(): string
    {
        return $this->timeframe;
    }

    public function setTimeframe(string $timeframe): void
    {
        $this->timeframe = $timeframe;
    }

    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }
}

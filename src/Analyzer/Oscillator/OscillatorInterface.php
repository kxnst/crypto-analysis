<?php

declare(strict_types=1);

namespace App\Analyzer\Oscillator;

interface OscillatorInterface
{
    public function process(InternalAnalyzerResult $result);

    public function getRequiredPreload(string $timeStamp);
}

<?php

declare(strict_types=1);

namespace App\Service\Analysis;

use App\Analyzer\Oscillator\InternalAnalyzerResult;
use App\Analyzer\Presentation\Context;
use App\Analyzer\Presentation\Signal;

class AnalysisService
{
    public function __construct()
    {
    }

    public function analyse(InternalAnalyzerResult $result, int $memory, float $sensitivity): array
    {
        $context = $result->getContext();
        $signals = $result->getSignals();
        /** @var Context $currentContext */
        $currentContext = reset($context);

        $stack = [];
        $scanResults = [];
        foreach ($result->getCandles() as $candle) {
            $candleSignals = $this->getSignals($signals, $candle->timeStamp);

            $internalSignals = [];
            foreach ($candleSignals as $candleSignal) {
                $targetDiff = (1 + $sensitivity / 100);

                if ($candleSignal->direction == Signal::DIRECTION_UP) {
                    $successTarget = $candle->closePrice * $targetDiff;
                    $failTarget = $candle->closePrice / $targetDiff;
                } else {
                    $successTarget = $candle->closePrice / $targetDiff;
                    $failTarget = $candle->closePrice * $targetDiff;
                }

                $internalSignals[] = new LocalResult(
                    $candleSignal->from,
                    $candleSignal->direction,
                    $currentContext->tags,
                    $successTarget,
                    $failTarget
                );
            }
            $stack[] = $internalSignals;

            if (count($stack) > $memory) {
                $scanResults[] = array_shift($stack);
            }

            /** @var LocalResult[] $signalsHeap */
            foreach ($stack as $signalsHeap) {
                foreach ($signalsHeap as $signal) {
                    if ($signal->direction == Signal::DIRECTION_UP) {
                        if ($signal->successTarget < $candle->getPrice()) {
                            $signal->success();
                        } elseif ($signal->failTarget > $candle->getPrice()) {
                            $signal->fail();
                        }
                    } else {
                        if ($signal->successTarget > $candle->getPrice()) {
                            $signal->success();
                        } elseif ($signal->failTarget < $candle->getPrice()) {
                            $signal->fail();
                        }
                    }
                }
            }

            $currentContext = next($context);
        }

        $scanResults = array_merge($scanResults, $stack);
        $scanResults = array_merge(...$scanResults);

        $mappedResults = [];

        /** @var LocalResult $scanResult */
        foreach ($scanResults as $scanResult) {
            $combinations = $this->getCombinations($scanResult->tags);
            foreach ($combinations as $combination) {
                $key = implode(':', $combination);

                if (!isset($mappedResults[$scanResult->type][$key][$scanResult->getResult()])) {
                    $mappedResults[$scanResult->type][$key][$scanResult->getResult()] = 1;
                } else {
                    $mappedResults[$scanResult->type][$key][$scanResult->getResult()] += 1;
                }
            }
        }

        $calculatedResults = [];
        $totalResults = [];
        foreach ($mappedResults as $module => $mappedResult) {
            foreach ($mappedResult as $tags => $results) {
                $total = array_sum($results);

                foreach ($results as $direction => $result) {
                    if (!isset($totalResults[$direction])) {
                        $totalResults[$direction] = $result;
                    } else {
                        $totalResults[$direction] += $result;
                    }
                    $calculatedResults[$module][$tags][$direction] = round($result / $total * 100, 2);
                }
            }
        }

        return [$calculatedResults, $totalResults];
    }

    /** @return  Signal[] */
    private function getSignals(array $signals, int $timestamp): array
    {
        return array_filter($signals, fn(Signal $signal) => $signal->timeStamp == $timestamp);
    }

    private function getCombinations(array $data): array
    {
        $num = count($data);

        //The total number of possible combinations
        $total = pow(2, $num);

        $result = [];
        //Loop through each possible combination
        for ($i = 0; $i < $total; $i++) {
            $localResult = [];
            for ($j = 0; $j < $num; $j++) {
                if (pow(2, $j) & $i) {
                    $localResult[] = $data[$j];
                }
            }
            $result[] = $localResult;
        }

        return $result;
    }
}

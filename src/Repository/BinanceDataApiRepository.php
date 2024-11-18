<?php

declare(strict_types=1);

namespace App\Repository;

use App\Binance\TimestampHelper;
use App\DTO\CandleDTO;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BinanceDataApiRepository
{
    public const API_HOST = 'https://api2.binance.com/';

    public const MAX_CANDLES_IN_RESPONSE = 999;

    public function __construct(
        private readonly HttpClientInterface $client
    )
    {
    }

    public function getData(
        string $symbol,
        string $timeframe,
        int    $timestampFrom,
        int    $timestampTo,
    ): array
    {
        $timestampFrom = $this->decorateWithPreviousTimestamps($timestampFrom, $timeframe);
        $candlesCount = $this->getCandleCount($timestampFrom, $timestampTo, $timeframe);

        if ($candlesCount <= self::MAX_CANDLES_IN_RESPONSE) {
            $limit = $candlesCount + 10;
            $pagesCount = 1;
        } else {
            $limit = self::MAX_CANDLES_IN_RESPONSE;
            $pagesCount = ceil($candlesCount / $limit);
        }

        $candles = [];

        for ($i = 0; $i < $pagesCount; $i++) {
            $timestampShift = $i * TimestampHelper::convertToMicroseconds($timeframe) * self::MAX_CANDLES_IN_RESPONSE;

            $request = http_build_query([
                'symbol' => $symbol,
                'interval' => $timeframe,
                'startTime' => $timestampFrom + $timestampShift,
                'endTime' => $timestampTo + $timestampShift,
                'limit' => $limit
            ]);

            $response = $this->client->request('GET', self::API_HOST . 'api/v3/klines?' . $request);

            foreach (json_decode($response->getContent(), true) as $rawCandle) {
                $candle = new CandleDTO(
                    (float)$rawCandle[1],
                    (float)$rawCandle[4],
                    (float)$rawCandle[2],
                    (float)$rawCandle[3],
                    $rawCandle[0],
                    (float)$rawCandle[5],
                    $timeframe
                );

                $candles[] = $candle;
            }
        }

        return $candles;
    }

    /**
     * @param CandleDTO[] $candles
     * @param int $timestampFrom
     * @return CandleDTO|null
     */
    public function getFirstNode(array $candles, int $timestampFrom): ?CandleDTO
    {
        $prevElement = null;

        foreach ($candles as $candle) {
            if ($prevElement
                && (abs($prevElement->timeStamp - $timestampFrom) < abs($candle->timeStamp - $timestampFrom))
            ) {
                return $prevElement;
            }
            $prevElement = $candle;
        }

        return reset($candles);
    }

    private function decorateWithPreviousTimestamps(int $timestamp, string $timeframe): int
    {
        return $timestamp - TimestampHelper::convertToMicroseconds($timeframe) * 200;
    }

    private function getCandleCount(int $from, int $to, string $timeframe): int
    {
        return (int)(($to - $from) / TimestampHelper::convertToMicroseconds($timeframe));
    }
}

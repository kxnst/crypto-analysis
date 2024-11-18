<?php

declare(strict_types=1);

namespace App\Service\Analysis;

class LocalResult
{
    public const RESULT_UNKNOWN = -1;
    public const RESULT_MIXED = 1;
    public const RESULT_COMPLETED = 2;
    public const RESULT_FAILED = -2;

    private int $result;

    public function __construct(
        public readonly string $type,
        public readonly int    $direction,
        public readonly array  $tags,
        public readonly float  $successTarget,
        public readonly float  $failTarget,
    )
    {
        $this->result = self::RESULT_UNKNOWN;
    }

    public function success(): void
    {
        if ($this->result === self::RESULT_UNKNOWN) {
            $this->result = self::RESULT_COMPLETED;
        } elseif ($this->result === self::RESULT_FAILED) {
            $this->result = self::RESULT_MIXED;
        }
    }

    public function fail(): void
    {
        if ($this->result === self::RESULT_UNKNOWN) {
            $this->result = self::RESULT_FAILED;
        }
    }

    public function getResult(): int
    {
        return $this->result;
    }
}

<?php

namespace App\Service\Cache;

use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Serializer\SerializerInterface;

class CacheService
{
    private ?Redis $redis;

    private SerializerInterface $serializer;

    public function __construct(
        string              $redisDsn,
        SerializerInterface $serializer
    )
    {
        $this->serializer = $serializer;

        $this->redis = RedisAdapter::createConnection($redisDsn);

    }

    public function hGetAll(string $key)
    {
        return $this->redis->hGetAll($key);
    }

    public function hGet(string $key, string $hashKey)
    {
        return $this->redis->hGet($key, $hashKey);
    }

    public function hMGet(string $key, string|array $hashKey)
    {
        return $this->redis->hMGet($key, $hashKey);
    }

    public function hSet(string $key, string $hashKey, mixed $value): bool|int
    {
        return $this->redis->hSet($key, $hashKey, $value);
    }

    public function hMSet($key, $hashKeys): bool|int
    {
        return $this->redis->hMSet($key, $hashKeys);
    }

    public function hDel(string $key, string $cacheKey, array $otherHashKeys = []): bool|int
    {
        return $this->redis->hDel($key, $cacheKey, ...$otherHashKeys);
    }

    public function sMembers(string $key, ?int $count = null): array
    {
        return array_slice($this->redis->sMembers($key) ?: [], 0, $count);
    }

    public function sAdd(string $key, string|array $value): bool|int
    {
        return $this->redis->sAdd($key, $value);
    }

    public function sRem(string $key, string|array $value): int
    {
        return $this->redis->sRem($key, $value);
    }

    public function flushAll()
    {
        return $this->redis->flushAll();
    }

    public function keys($start = ''): array
    {
        return $this->redis->keys($start . '*') ?: [];
    }

    public function hKeys($key): array
    {
        return $this->redis->hKeys($key) ?: [];
    }

    public function flushDB()
    {
        return $this->redis->flushDB();
    }

    public function del($key1, ...$otherKeys)
    {
        return $this->redis->del($key1, ...$otherKeys);
    }

    public function scan(&$iterator, $pattern = null, $count = 0)
    {
        return $this->redis->scan($iterator, $pattern, $count);
    }

    private function jsonEncode(mixed $data): string
    {
        return $this->serializer->serialize($data, 'json');
    }
}

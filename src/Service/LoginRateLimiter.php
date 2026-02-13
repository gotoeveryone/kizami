<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class LoginRateLimiter
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly int $lockSeconds,
    ) {
    }

    public function isBlocked(string $key): bool
    {
        $record = $this->getRecord($key);

        return $record !== null && ($record['blocked_until'] ?? 0) > time();
    }

    public function getRetryAfterSeconds(string $key): int
    {
        $record = $this->getRecord($key);
        if ($record === null) {
            return 0;
        }

        return max(0, ((int) ($record['blocked_until'] ?? 0)) - time());
    }

    public function registerFailure(string $key): void
    {
        $now = time();
        $this->mutate(function (array $data) use ($key, $now): array {
            $this->cleanupExpired($data, $now);

            $record = $data[$key] ?? [
                'attempts' => 0,
                'first_failed_at' => $now,
                'blocked_until' => 0,
            ];

            $firstFailedAt = (int) ($record['first_failed_at'] ?? $now);
            if (($now - $firstFailedAt) > $this->windowSeconds) {
                $record['attempts'] = 0;
                $record['first_failed_at'] = $now;
            }

            $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
            if ($record['attempts'] >= $this->maxAttempts) {
                $record['blocked_until'] = $now + $this->lockSeconds;
            }

            $data[$key] = $record;

            return $data;
        });
    }

    public function clear(string $key): void
    {
        $this->mutate(function (array $data) use ($key): array {
            unset($data[$key]);

            return $data;
        });
    }

    /**
     * @return array{attempts:int,first_failed_at:int,blocked_until:int}|null
     */
    private function getRecord(string $key): ?array
    {
        $data = $this->readLockedData();
        $now = time();
        $this->cleanupExpired($data, $now);

        $record = $data[$key] ?? null;
        if (!is_array($record)) {
            return null;
        }

        return [
            'attempts' => (int) ($record['attempts'] ?? 0),
            'first_failed_at' => (int) ($record['first_failed_at'] ?? 0),
            'blocked_until' => (int) ($record['blocked_until'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function cleanupExpired(array &$data, int $now): void
    {
        foreach ($data as $k => $record) {
            if (!is_array($record)) {
                unset($data[$k]);
                continue;
            }

            $blockedUntil = (int) ($record['blocked_until'] ?? 0);
            $firstFailedAt = (int) ($record['first_failed_at'] ?? 0);
            if ($blockedUntil > 0 && $blockedUntil <= $now) {
                unset($data[$k]);
                continue;
            }
            if ($firstFailedAt > 0 && ($now - $firstFailedAt) > ($this->windowSeconds + $this->lockSeconds)) {
                unset($data[$k]);
            }
        }
    }

    /**
     * @param callable(array<string,mixed>):array<string,mixed> $mutator
     */
    private function mutate(callable $mutator): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('failed to create rate limiter storage directory');
        }

        $handle = fopen($this->storagePath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('failed to open rate limiter storage');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('failed to lock rate limiter storage');
            }

            $data = $this->readFromHandle($handle);
            $nextData = $mutator($data);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($nextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readLockedData(): array
    {
        if (!file_exists($this->storagePath)) {
            return [];
        }

        $handle = fopen($this->storagePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('failed to open rate limiter storage');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('failed to lock rate limiter storage');
            }

            $data = $this->readFromHandle($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $data;
    }

    /**
     * @param resource $handle
     * @return array<string,mixed>
     */
    private function readFromHandle($handle): array
    {
        rewind($handle);
        $json = stream_get_contents($handle);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}

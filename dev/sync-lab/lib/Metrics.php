<?php
declare(strict_types=1);

final class BesSyncLab_Metrics
{
    public int $requestCount = 0;
    public int $http429Count = 0;
    public float $startedAt;
    /** @var list<string> */
    public array $errors = [];
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public function recordRequest(string $method, string $url, int $status): void
    {
        $this->requestCount++;
        if ($status === 429) {
            $this->http429Count++;
        }
        $this->requests[] = [
            'n' => $this->requestCount,
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'at' => date('c'),
        ];
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /** @return array<string, mixed> */
    public function toArray(int $memberCount = 0): array
    {
        $duration = microtime(true) - $this->startedAt;

        return [
            'request_count' => $this->requestCount,
            'http_429_count' => $this->http429Count,
            'duration_sec' => round($duration, 2),
            'requests_per_member' => $memberCount > 0 ? round($this->requestCount / $memberCount, 2) : null,
            'error_count' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}

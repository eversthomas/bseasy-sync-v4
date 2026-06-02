<?php
declare(strict_types=1);

final class BesSyncLab_ApiClient
{
    private string $token;
    private ?string $baseUsed = null;
    /** @var array<string, mixed> */
    private array $config;
    private BesSyncLab_Metrics $metrics;
    private bool $verbose;

    /** @param array<string, mixed> $config */
    public function __construct(string $token, array $config, BesSyncLab_Metrics $metrics, bool $verbose = false)
    {
        $this->token = $token;
        $this->config = $config;
        $this->metrics = $metrics;
        $this->verbose = $verbose;
    }

    public function getBaseUsed(): ?string
    {
        return $this->baseUsed;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array{0:int,1:?array,2:string,3:?string}
     */
    public function get(string $path, array $query = []): array
    {
        $isAbsolute = (bool) preg_match('~^https?://~i', $path);

        if ($isAbsolute) {
            return $this->execute($path, $query);
        }

        $lastError = null;
        foreach ($this->config['api_bases'] as $base) {
            $url = rtrim($base, '/') . '/' . $this->config['api_version'] . '/' . ltrim($path, '/');
            try {
                [$code, $data, $body, $finalUrl] = $this->execute($url, $query);
                if ($code >= 200 && $code < 500) {
                    $this->baseUsed = rtrim($base, '/');
                    return [$code, $data, $body, $finalUrl];
                }
                $lastError = "HTTP $code bei $finalUrl";
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?? 'API-Request fehlgeschlagen');
    }

    /**
     * Versucht Request mit query-Parameter; bei 400 ohne query erneut.
     *
     * @param array<string, scalar|null> $query
     * @return array{0:int,1:?array,2:string}
     */
    public function safeGetTryQuery(string $path, array $query = []): array
    {
        [$code, $data, , $url] = $this->get($path, $query);

        if ($code === 400 && isset($query['query'])) {
            $fallback = $query;
            unset($fallback['query']);
            [$code2, $data2, , $url2] = $this->get($path, $fallback);
            return [$code2, $data2, $url2 ?? $url ?? ''];
        }

        return [$code, $data, $url ?? ''];
    }

    /**
     * Paginierte Liste — folgt `next` oder inkrementiert page.
     *
     * @param array<string, scalar|null> $query
     * @return list<array<string, mixed>>
     */
    public function fetchAllList(string $path, array $query = [], int $maxPages = 500): array
    {
        $all = [];
        $page = 1;
        $nextUrl = null;

        for ($i = 0; $i < $maxPages; $i++) {
            if ($nextUrl) {
                [$code, $data, , $url] = $this->get($nextUrl, []);
            } else {
                $q = $query;
                if (!isset($q['page'])) {
                    $q['page'] = $page;
                }
                [$code, $data, , $url] = $this->get($path, $q);
            }

            if ($code === 429) {
                $this->handle429($data);
                continue;
            }

            if ($code !== 200 || !is_array($data)) {
                $this->metrics->addError("Liste $path Seite $page: HTTP $code");
                break;
            }

            foreach ($this->normalizeList($data) as $row) {
                $all[] = $row;
            }

            if (!empty($data['next']) && is_string($data['next'])) {
                $nextUrl = $data['next'];
                $page++;
            } elseif (!empty($data['hasNext'])) {
                $nextUrl = null;
                $page++;
            } else {
                break;
            }

            usleep((int) ($this->config['request_pause_us'] ?? 200000));
        }

        return $all;
    }

    /** @return list<array<string, mixed>> */
    public function normalizeList(array $data): array
    {
        if (isset($data['results']) && is_array($data['results'])) {
            return $data['results'];
        }
        if (bes_sync_lab_is_list($data)) {
            return $data;
        }
        return [];
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array{0:int,1:?array,2:string,3:?string}
     */
    private function execute(string $url, array $query): array
    {
        if ($query) {
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $url .= $sep . http_build_query($query);
        }

        if ($this->verbose) {
            fwrite(STDERR, "[API] GET $url\n");
        }

        $attempts = 0;
        while ($attempts < 3) {
            $attempts++;
            [$code, $body, $headers] = $this->curlGet($url);
            $this->metrics->recordRequest('GET', $url, $code);

            if ($code === 429) {
                $data = json_decode($body, true);
                $this->handle429(is_array($data) ? $data : null);
                continue;
            }

            if (!empty($headers['tokenrefreshneeded']) && strtolower((string) $headers['tokenrefreshneeded']) === 'true') {
                $this->refreshToken();
                continue;
            }

            $json = $body !== '' ? json_decode($body, true) : null;
            if ($json === null && $body !== '' && $code >= 200 && $code < 300) {
                throw new RuntimeException("JSON-Parse-Fehler: $url");
            }

            return [$code, is_array($json) ? $json : null, $body, $url];
        }

        return [429, null, '', $url];
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    private function curlGet(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP curl-Erweiterung fehlt.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 45),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
                'User-Agent: BSEasy-Sync-Lab/' . BES_SYNC_LAB_VERSION,
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_HEADER => true,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            throw new RuntimeException("cURL-Fehler: $err");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headerRaw = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $headers = $this->parseHeaders($headerRaw);

        return [$status, $body, $headers];
    }

    /** @return array<string, string> */
    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", $raw) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        return $headers;
    }

    private function refreshToken(): void
    {
        foreach ($this->config['api_bases'] as $base) {
            $url = rtrim($base, '/') . '/' . $this->config['api_version'] . '/refresh-token';
            [$code, $body, ] = $this->curlGet($url);
            $this->metrics->recordRequest('GET', $url, $code);
            if ($code !== 200) {
                continue;
            }
            $data = json_decode($body, true);
            if (is_array($data) && !empty($data['Bearer'])) {
                $this->token = (string) $data['Bearer'];
                if ($this->verbose) {
                    fwrite(STDERR, "[API] Token refreshed\n");
                }
                return;
            }
        }
        $this->metrics->addError('Token-Refresh fehlgeschlagen');
    }

    /** @param array<string, mixed>|null $data */
    private function handle429(?array $data): void
    {
        $wait = 30;
        if ($data && isset($data['detail']) && is_string($data['detail'])) {
            if (preg_match('/Erwarte Verfügbarkeit in (\d+)/i', $data['detail'], $m)) {
                $wait = max(1, (int) $m[1]);
            }
        }
        if ($this->verbose) {
            fwrite(STDERR, "[API] 429 — warte {$wait}s\n");
        }
        sleep($wait + 1);
    }
}

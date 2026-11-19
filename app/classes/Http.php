<?php
/**
 * cURL helpers. Every outbound request in this app goes through here so the
 * timeouts, redirect rules and header handling stay in one place.
 */

declare(strict_types=1);

final class Http
{
    /**
     * @param array<int,string> $headers
     * @param array<string,mixed>|null $json
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public static function request(
        string $url,
        array $headers = [],
        ?array $json = null,
        string $method = 'GET',
        int $timeout = 25,
        int $connectTimeout = 8,
    ): array {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not start the HTTP client.');
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        if ($json !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_SLASHES));
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('Request failed: ' . $error);
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => (string) $body,
        ];
    }

    /** @return array<string,mixed> */
    public static function json(
        string $url,
        array $headers = [],
        ?array $json = null,
        string $method = 'GET',
        int $timeout = 20,
    ): array {
        $response = self::request($url, $headers, $json, $method, $timeout);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Upstream answered ' . $response['status'] . ' with a non-JSON body.');
        }
        return $decoded;
    }

    /**
     * Streams an upstream body to the browser. The caller sets response headers
     * first; this only copies bytes and never buffers the whole file.
     *
     * @param array<int,string> $headers
     */
    public static function stream(string $url, array $headers, ?string $range = null, int $timeout = 90): void
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not start the stream client.');
        }
        if ($range !== null) {
            $headers[] = 'Range: ' . $range;
        }
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int {
                echo $chunk;
                flush();
                return strlen($chunk);
            },
        ]);
        curl_exec($handle);
        curl_close($handle);
    }

    /** A file's exact size, read from a one-byte range answer. */
    public static function size(string $url, array $headers): ?int
    {
        try {
            $probe = self::request($url, [...$headers, 'Range: bytes=0-0'], null, 'GET', 15);
        } catch (Throwable) {
            return null;
        }
        if (preg_match('#/(\d+)$#', $probe['headers']['content-range'] ?? '', $match)) {
            return (int) $match[1];
        }
        $length = $probe['headers']['content-length'] ?? '';
        if ($probe['status'] === 200 && ctype_digit($length)) {
            return (int) $length;
        }
        return null;
    }
}

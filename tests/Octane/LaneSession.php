<?php

namespace Lathe\Tests\Octane;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

/**
 * One signed-in user on one server, with its own cookie jar.
 */
class LaneSession
{
    /** Keys whose values change on every response, so the comparison drops them. */
    public const VOLATILE = ['csrf_token', 'nonce', 'requestId', 'request_id'];

    protected Client $client;

    protected CookieJar $cookies;

    public function __construct(string $baseUrl, string $email)
    {
        $this->cookies = new CookieJar;
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'cookies' => $this->cookies,
            'http_errors' => false,
            'timeout' => 30,
        ]);

        $this->client->get('/lathe/login');
        $xsrf = $this->cookies->getCookieByName('XSRF-TOKEN')?->getValue() ?? '';

        $login = $this->client->post('/lathe/login', [
            'headers' => ['Accept' => 'application/json', 'X-XSRF-TOKEN' => urldecode($xsrf)],
            'form_params' => ['email' => $email, 'password' => 'password'],
        ]);

        if ($login->getStatusCode() !== 200) {
            throw new RuntimeException("{$email} could not sign in at {$baseUrl}: HTTP {$login->getStatusCode()}");
        }
    }

    /**
     * GET a path as an Inertia visit.
     *
     * @param  array<string, string>  $headers
     * @return array{status: int, body: mixed, memory: int|null, pid: string|null}
     */
    public function get(string $path, array $headers = []): array
    {
        $response = $this->client->get($path, [
            'headers' => ['X-Inertia' => 'true', 'Accept' => 'application/json', ...$headers],
        ]);

        $memory = $response->getHeaderLine('X-Worker-Memory');

        return [
            'status' => $response->getStatusCode(),
            'body' => self::stable(json_decode((string) $response->getBody(), true)),
            'memory' => $memory === '' ? null : (int) $memory,
            'pid' => $response->getHeaderLine('X-Worker-Pid') ?: null,
        ];
    }

    /**
     * The value without its volatile keys, at any depth.
     */
    public static function stable(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $kept = [];

        foreach ($value as $key => $item) {
            if (! in_array($key, self::VOLATILE, true)) {
                $kept[$key] = self::stable($item);
            }
        }

        return $kept;
    }

    /**
     * The first path where the two values differ, as "a.b.c: golden X, octane Y", or null.
     */
    public static function firstDifference(mixed $golden, mixed $octane, string $at = ''): ?string
    {
        if (is_array($golden) && is_array($octane)) {
            foreach (array_unique([...array_keys($golden), ...array_keys($octane)]) as $key) {
                $path = ltrim("{$at}.{$key}", '.');

                if (! array_key_exists($key, $golden) || ! array_key_exists($key, $octane)) {
                    return "{$path}: only one response has it";
                }

                $difference = self::firstDifference($golden[$key], $octane[$key], $path);

                if ($difference !== null) {
                    return $difference;
                }
            }

            return null;
        }

        return $golden === $octane ? null : sprintf('%s: golden %s, octane %s', $at ?: '(root)', json_encode($golden), json_encode($octane));
    }
}

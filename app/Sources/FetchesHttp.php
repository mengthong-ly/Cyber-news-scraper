<?php

namespace App\Sources;

use App\Support\HostResolver;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait FetchesHttp
{
    protected function http(): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(15)
            ->retry(2, 2000, throw: false)
            ->withUserAgent('CambodiaCyberWatch/1.0 (+defensive monitoring)');
    }

    /**
     * GET a user-configured URL. Every hop is checked against public addresses and the connection is
     * pinned to the checked IP, so neither redirects nor DNS changes can reach internal services.
     */
    protected function safeGet(string $url, int $maxRedirects = 5): Response
    {
        $resolver = app(HostResolver::class);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $target = $resolver->resolvePublic($url);
            $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

            $response = $this->http()->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]],
            ])->get($url);

            if (! $response->redirect() || ! $response->header('Location')) {
                return $response;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
        }

        throw new RuntimeException('Too many redirects');
    }

    /**
     * Strip tags and collapse whitespace from feed HTML.
     */
    protected function plain(?string $html, int $limit = 2000): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }
}

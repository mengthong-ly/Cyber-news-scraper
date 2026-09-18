<?php

namespace App\Sources;

use App\Models\Source;
use DOMDocument;
use DOMXPath;
use RuntimeException;

/**
 * Pages without a feed. Config: url, item (XPath to each entry), title (relative XPath), link (relative XPath),
 * language?, country_code?, publisher?. Only use on sites whose robots.txt allows crawling.
 */
class HtmlXPathAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $url = (string) $source->setting('url');
        $response = $this->safeGet($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} from {$url}");
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->body());
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query((string) $source->setting('item'));

        if ($nodes === false) {
            throw new RuntimeException('Invalid item XPath');
        }

        $items = [];

        foreach ($nodes as $node) {
            $title = trim(preg_replace('/\s+/u', ' ', (string) $xpath->evaluate('string('.$source->setting('title').')', $node)));
            $link = trim((string) $xpath->evaluate('string('.$source->setting('link', '@href').')', $node));

            if ($title === '' || $link === '') {
                continue;
            }

            $items[] = new ItemData(
                url: $this->absolute($link, $url),
                title: $title,
                publisher: $source->setting('publisher', parse_url($url, PHP_URL_HOST)),
                language: $source->setting('language'),
                countryCode: $source->setting('country_code'),
            );
        }

        if ($items === [] && $nodes->length === 0) {
            throw new RuntimeException('No entries matched the item XPath; the page layout may have changed');
        }

        return $items;
    }

    private function absolute(string $link, string $base): string
    {
        if (preg_match('#^https?://#i', $link)) {
            return $link;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'];

        return str_starts_with($link, '/') ? $origin.$link : rtrim($base, '/').'/'.$link;
    }
}

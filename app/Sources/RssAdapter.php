<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * RSS 2.0 and Atom feeds. Config: url, language?, country_code?, kind?, publisher?
 */
class RssAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        return $this->parse($this->download((string) $source->setting('url')), $source);
    }

    protected function download(string $url): SimpleXMLElement
    {
        $response = $this->safeGet($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} from {$url}");
        }

        // Some WordPress feeds emit whitespace before the XML declaration, which is invalid XML.
        $xml = @simplexml_load_string(ltrim($response->body()), options: LIBXML_NOCDATA);

        if ($xml === false) {
            throw new RuntimeException("Response from {$url} is not valid RSS/Atom");
        }

        return $xml;
    }

    /**
     * @return list<ItemData>
     */
    public function parse(SimpleXMLElement $xml, Source $source): array
    {
        $isAtom = $xml->getName() === 'feed';
        $entries = $isAtom ? $xml->entry : $xml->channel->item;
        $channelTitle = trim((string) ($isAtom ? $xml->title : $xml->channel->title));
        $items = [];

        foreach ($entries as $entry) {
            $link = $isAtom ? $this->atomLink($entry) : trim((string) $entry->link);
            $publisher = trim((string) ($entry->source ?? '')) ?: ($source->setting('publisher') ?? ($channelTitle ?: null));
            $title = html_entity_decode(trim((string) $entry->title), ENT_QUOTES | ENT_HTML5);

            // Aggregators such as Google News append " - Publisher" to every title.
            if ($publisher !== null && str_ends_with($title, ' - '.$publisher)) {
                $title = mb_substr($title, 0, -mb_strlen(' - '.$publisher));
            }

            $content = $entry->children('http://purl.org/rss/1.0/modules/content/');
            $body = $isAtom
                ? (string) ($entry->summary ?: $entry->content)
                : ((string) $entry->description ?: (string) ($content->encoded ?? ''));

            $items[] = new ItemData(
                url: $link,
                title: $title,
                kind: $source->setting('kind', 'news'),
                excerpt: $this->plain($body),
                publisher: $publisher,
                publishedAt: $this->date((string) ($isAtom ? ($entry->published ?: $entry->updated) : ($entry->pubDate ?: $entry->children('http://purl.org/dc/elements/1.1/')->date))),
                language: $source->setting('language'),
                countryCode: $source->setting('country_code'),
                author: trim((string) ($isAtom ? $entry->author->name : $entry->children('http://purl.org/dc/elements/1.1/')->creator)) ?: null,
            );
        }

        return $items;
    }

    private function atomLink(SimpleXMLElement $entry): string
    {
        foreach ($entry->link as $link) {
            if (in_array((string) $link['rel'], ['', 'alternate'], true)) {
                return trim((string) $link['href']);
            }
        }

        return trim((string) ($entry->link['href'] ?? ''));
    }

    private function date(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

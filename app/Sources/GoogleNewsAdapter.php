<?php

namespace App\Sources;

use App\Models\Source;

/**
 * Google News RSS search. Config: query, hl? (en-US), gl? (US), ceid? (US:en), days? (1), language?, country_code?
 * Used for sites that have no feed or block automated clients (e.g. "site:phnompenhpost.com").
 */
class GoogleNewsAdapter extends RssAdapter
{
    public function fetch(Source $source): iterable
    {
        $query = (string) $source->setting('query');

        if (! str_contains($query, 'when:')) {
            $query .= ' when:'.max(1, (int) $source->setting('days', 1)).'d';
        }

        $url = 'https://news.google.com/rss/search?'.http_build_query([
            'q' => $query,
            'hl' => $source->setting('hl', 'en-US'),
            'gl' => $source->setting('gl', 'US'),
            'ceid' => $source->setting('ceid', 'US:en'),
        ]);

        return $this->parse($this->download($url), $source);
    }
}

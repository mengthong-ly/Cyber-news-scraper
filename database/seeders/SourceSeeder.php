<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Sources verified on 2026-09-17 (see .claude/research/cambodia-mod-data-sources.md).
 * Sources that need a key or a legal decision are created disabled.
 */
class SourceSeeder extends Seeder
{
    public function run(): void
    {
        // General outlets carry every topic, so they keep only cyber-related items (cyber_only).
        $rss = fn (string $name, string $url, string $language, string $country, string $kind = 'news', int $interval = 30) => [
            'name' => $name, 'type' => 'rss', 'interval_minutes' => $interval,
            'config' => ['url' => $url, 'language' => $language, 'country_code' => $country, 'kind' => $kind, 'cyber_only' => $kind === 'news'],
        ];

        // Google's KH editions redirect from some networks; switch hl/gl/ceid to km/KH/KH:km on a server in Cambodia if they work there.
        $site = fn (string $name, string $domain, string $country = 'KH') => [
            'name' => $name, 'type' => 'google_news', 'interval_minutes' => 60,
            'config' => ['query' => "site:{$domain}", 'country_code' => $country, 'publisher' => $name, 'cyber_only' => true],
        ];

        $sources = [
            // Cambodian news with working feeds.
            $rss('Khmer Times', 'https://www.khmertimeskh.com/feed/', 'en', 'KH'),
            $rss('CamboJA News', 'https://cambojanews.com/feed/', 'en', 'KH'),
            $rss('Fresh News (English)', 'https://en.freshnewsasia.com/index.php/en/?format=feed&type=rss', 'en', 'KH'),
            $rss('Kampuchea Thmey', 'https://www.kampucheathmey.com/feed', 'km', 'KH'),
            $rss('DAP News', 'https://dap-news.com/feed/', 'km', 'KH'),
            $rss('Koh Santepheap', 'https://kohsantepheapdaily.com.kh/feed', 'km', 'KH'),
            $rss('RFA Khmer', 'https://www.rfa.org/arc/outboundfeeds/khmer/rss/', 'km', 'KH', interval: 60),

            // Government and CERT advisories.
            $rss('CamCERT advisories', 'https://www.camcert.gov.kh/feed/', 'km', 'KH', 'advisory', 60),
            $rss('Ministry of Post and Telecommunications', 'https://mptc.gov.kh/feed/', 'km', 'KH', interval: 120),
            $rss('ThaiCERT', 'https://www.thaicert.or.th/feed/', 'th', 'TH', 'advisory', 120),
            $rss('CISA advisories', 'https://www.cisa.gov/cybersecurity-advisories/all.xml', 'en', 'US', 'advisory', 120),

            // Sites without a feed, or behind a bot check we must not bypass.
            $site('Phnom Penh Post', 'phnompenhpost.com'),
            $site('Thmey Thmey', 'thmeythmey.com'),
            $site('Fresh News (Khmer)', 'freshnewsasia.com'),
            $site('Kiripost', 'kiripost.com'),
            $site('Cambodianess', 'cambodianess.com'),
            $site('AKP (Agence Kampuchea Presse)', 'akp.gov.kh'),

            // Crawled page (robots.txt allows all agents).
            [
                'name' => 'Sabay News', 'type' => 'html_xpath', 'interval_minutes' => 60,
                'config' => [
                    'url' => 'https://news.sabay.com.kh/',
                    'item' => "//a[contains(@href, '/article/') and .//div[contains(@class, 'title')]]",
                    'title' => ".//div[contains(@class, 'title')]",
                    'link' => '@href',
                    'language' => 'km',
                    'country_code' => 'KH',
                    'publisher' => 'Sabay News',
                    'cyber_only' => true,
                ],
            ],

            // Global cyber news for every country (~20 minutes per run).
            ['name' => 'Global cyber news (GDELT + Google News)', 'type' => 'global_news', 'interval_minutes' => 1440, 'config' => ['days' => 1]],

            // Threat intelligence.
            ['name' => 'CISA Known Exploited Vulnerabilities', 'type' => 'cisa_kev', 'interval_minutes' => 360, 'config' => ['days' => 14]],
            ['name' => 'NVD new CVEs (watchlist products)', 'type' => 'nvd', 'interval_minutes' => 720, 'config' => []],
            // The key-less API is for personal use only: enable after setting the PRO base URL and key.
            ['name' => 'ransomware.live — Cambodian victims', 'type' => 'ransomware_live', 'interval_minutes' => 120, 'enabled' => false, 'config' => ['country' => 'KH', 'recent_days' => 7]],
            ['name' => 'abuse.ch URLhaus — .kh hosts', 'type' => 'urlhaus', 'interval_minutes' => 60, 'enabled' => false, 'config' => ['host_suffixes' => ['.kh']]],
            ['name' => 'AlienVault OTX — Cambodia pulses', 'type' => 'otx', 'interval_minutes' => 360, 'enabled' => false, 'config' => ['query' => 'cambodia']],

            // Social media (official APIs only).
            [
                'name' => 'Bluesky search', 'type' => 'bluesky', 'interval_minutes' => 60, 'enabled' => false,
                'config' => ['queries' => ['Cambodia hack', 'Cambodia ransomware', 'Cambodia data leak', 'gov.kh']],
            ],
            // 3 queries x 3 runs a day = 9 of the 100 searches YouTube allows daily.
            [
                'name' => 'YouTube search', 'type' => 'youtube', 'interval_minutes' => 480, 'enabled' => false,
                'config' => ['queries' => ['Cambodia cyber attack', 'Cambodia hacked', 'កម្ពុជា ហេគឃ័រ'], 'region_code' => 'KH'],
            ],
            // Legal review required before AI processing of Telegram content.
            ['name' => 'Telegram channels (bot added by owners)', 'type' => 'telegram_bot', 'interval_minutes' => 10, 'enabled' => false, 'ai_enabled' => false, 'config' => []],
        ];

        foreach ($sources as $source) {
            Source::updateOrCreate(['name' => $source['name']], $source);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\FetchSource;
use App\Models\Alert;
use App\Models\Item;
use App\Models\Source;
use App\Models\User;
use App\Models\WatchlistTerm;
use App\Notifications\AlertRaised;
use App\Support\HostResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SourceIngestionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FEED = "\n<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<rss version=\"2.0\"><channel><title>DAP News</title>
  <item><title>ហេគឃ័រវាយប្រហារគេហទំព័រ</title><link>https://dap-news.com/a/1</link><pubDate>Wed, 16 Sep 2026 08:00:00 +0700</pubDate><description><![CDATA[<p>Hackers attacked <b>mod.gov.kh</b></p>]]></description></item>
  <item><title>ក្រុមបាល់ទាត់ឈ្នះ</title><link>https://dap-news.com/a/2</link><pubDate>Wed, 16 Sep 2026 09:00:00 +0700</pubDate><description>Football results</description></item>
</channel></rss>";

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();

        // No real DNS in tests: *.internal resolves to a private address, everything else to a public one.
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            protected function lookup(string $host): array
            {
                return str_ends_with($host, '.internal') ? ['10.0.0.5'] : ['93.184.215.14'];
            }
        });
    }

    public function test_feeds_cannot_reach_internal_hosts_directly_or_through_redirects()
    {
        $direct = Source::factory()->create(['config' => ['url' => 'http://metadata.internal/feed']]);
        $redirected = Source::factory()->create(['config' => ['url' => 'https://public.example.com/feed']]);
        Http::fake([
            'https://public.example.com/feed' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        ]);

        FetchSource::dispatchSync($direct);
        FetchSource::dispatchSync($redirected);

        $this->assertStringContainsString('non-public address', $direct->fresh()->last_error);
        $this->assertStringContainsString('non-public address', $redirected->fresh()->last_error);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_redirects_to_public_hosts_are_followed()
    {
        $source = Source::factory()->create(['config' => ['url' => 'http://old.example.com/feed']]);
        Http::fake([
            'http://old.example.com/feed' => Http::response('', 301, ['Location' => 'https://new.example.com/rss']),
            'https://new.example.com/rss' => Http::response(self::FEED),
        ]);

        FetchSource::dispatchSync($source);

        $this->assertSame(2, Item::count());
    }

    public function test_rss_items_are_filtered_matched_against_the_watchlist_and_alerted()
    {
        Notification::fake();
        $analyst = User::factory()->create();
        WatchlistTerm::factory()->create(['kind' => 'domain', 'term' => 'mod.gov.kh']);
        $source = Source::factory()->create([
            'config' => ['url' => 'https://dap-news.com/feed/', 'language' => 'km', 'country_code' => 'KH', 'cyber_only' => true],
        ]);
        Http::fake(['https://dap-news.com/feed/' => Http::response(self::FEED)]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame('ហេគឃ័រវាយប្រហារគេហទំព័រ', $item->title);
        $this->assertSame('Hackers attacked mod.gov.kh', $item->excerpt);
        $this->assertSame('DAP News', $item->publisher);
        $this->assertSame(['km', 'KH', 4, true], [$item->language, $item->country_code, $item->severity, $item->is_cambodia]);
        $this->assertSame(['mod.gov.kh'], $item->watch_hits);
        $this->assertSame('pending', $item->enrichment_status);
        $this->assertTrue(Alert::where('item_id', $item->id)->exists());
        Notification::assertSentTo($analyst, AlertRaised::class);

        $source->refresh();
        $this->assertSame([0, 1, null], [$source->consecutive_failures, $source->last_item_count, $source->last_error]);
    }

    public function test_refetching_does_not_duplicate_items_or_alerts()
    {
        Notification::fake();
        $source = Source::factory()->create(['config' => ['url' => 'https://dap-news.com/feed/']]);
        Http::fake(['https://dap-news.com/feed/' => Http::response(self::FEED)]);

        FetchSource::dispatchSync($source);
        FetchSource::dispatchSync($source);

        $this->assertSame(2, Item::count());
        $this->assertSame(0, $source->fresh()->last_item_count);
    }

    public function test_a_failing_source_records_the_error_and_becomes_unhealthy()
    {
        $source = Source::factory()->create(['config' => ['url' => 'https://example.org/feed'], 'consecutive_failures' => 2]);
        Http::fake(['https://example.org/feed' => Http::response('Not found', 404)]);

        FetchSource::dispatchSync($source);

        $source->refresh();
        $this->assertSame(3, $source->consecutive_failures);
        $this->assertStringContainsString('HTTP 404', $source->last_error);
        $this->assertFalse($source->isHealthy());
    }

    public function test_sources_without_ai_permission_never_queue_items_for_ai()
    {
        $source = Source::factory()->create(['ai_enabled' => false, 'config' => ['url' => 'https://dap-news.com/feed/', 'country_code' => 'KH']]);
        Http::fake(['https://dap-news.com/feed/' => Http::response(self::FEED)]);

        FetchSource::dispatchSync($source);

        $this->assertSame(0, Item::where('ai_allowed', true)->count());
        $this->assertSame(0, Item::where('enrichment_status', 'pending')->count());
    }

    public function test_google_news_titles_lose_the_publisher_suffix_and_urls_lose_fragments()
    {
        $source = Source::factory()->create(['type' => 'google_news', 'config' => ['query' => 'site:example.com']]);
        Http::fake(['news.google.com/rss/search*' => Http::response(
            '<rss><channel><item><title>Scam ring busted - Phnom Penh Post</title><link>https://news.google.com/rss/articles/x#frag</link>'
            .'<source url="https://phnompenhpost.com">Phnom Penh Post</source></item></channel></rss>'
        )]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame('Scam ring busted', $item->title);
        $this->assertSame('https://news.google.com/rss/articles/x', $item->url);
        $this->assertSame('crime', $item->category);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'site:example.com when:1d'));
    }

    public function test_html_xpath_source_extracts_entries()
    {
        $source = Source::factory()->create(['type' => 'html_xpath', 'config' => [
            'url' => 'https://news.example.com/',
            'item' => "//a[contains(@href, '/article/')]",
            'title' => ".//div[@class='title']",
        ]]);
        Http::fake(['https://news.example.com/' => Http::response(
            '<html><body><a href="/article/7#utm"><div class="title"> Data  leak reported </div></a><a href="/about">About</a></body></html>'
        )]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame(['Data leak reported', 'https://news.example.com/article/7'], [$item->title, $item->url]);
    }

    public function test_recent_ransomware_claims_are_critical_and_old_ones_are_context()
    {
        Notification::fake();
        $source = Source::factory()->create(['type' => 'ransomware_live', 'config' => ['country' => 'KH']]);
        Http::fake(['api.ransomware.live/v2/countryvictims/KH' => Http::response([
            ['post_title' => 'Example Bank', 'group_name' => 'grp', 'discovered' => now()->subDay()->toIso8601String(), 'description' => 'Bank in Cambodia'],
            ['post_title' => 'Old Company', 'group_name' => 'grp', 'discovered' => now()->subYear()->toIso8601String()],
        ])]);

        FetchSource::dispatchSync($source);

        $recent = Item::where('title', 'like', '%Example Bank%')->sole();
        $old = Item::where('title', 'like', '%Old Company%')->sole();
        $this->assertSame([5, 'threat', true], [$recent->severity, $recent->kind, $recent->is_cambodia]);
        $this->assertSame(['grp'], $recent->entities['threat_actors']);
        $this->assertSame(3, $old->severity);
        $this->assertSame([$recent->id], Alert::pluck('item_id')->all());
    }

    public function test_nvd_keeps_only_cves_for_watchlist_products_and_remembers_its_position()
    {
        $this->freezeTime();
        WatchlistTerm::factory()->create(['kind' => 'product', 'term' => 'fortios']);
        $source = Source::factory()->create(['type' => 'nvd', 'config' => []]);
        Http::fake(['services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 2,
            'vulnerabilities' => [
                ['cve' => ['id' => 'CVE-2026-1', 'published' => '2026-09-16T10:00:00.000', 'descriptions' => [['lang' => 'en', 'value' => 'Heap overflow in Fortinet FortiOS SSL-VPN.']],
                    'metrics' => ['cvssMetricV31' => [['cvssData' => ['baseScore' => 9.8]]]]]],
                ['cve' => ['id' => 'CVE-2026-2', 'published' => '2026-09-16T10:00:00.000', 'descriptions' => [['lang' => 'en', 'value' => 'Bug in another product.']]]],
            ],
        ])]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame(['vulnerability', 5, true], [$item->kind, $item->severity, $item->is_cambodia]);
        $this->assertSame(['CVE-2026-1'], $item->entities['cves']);
        $this->assertSame(now()->utc()->toIso8601String(), $source->fresh()->state['published_until']);
    }

    public function test_urlhaus_links_to_the_report_and_defangs_the_malware_url()
    {
        config(['services.abusech.key' => 'key']);
        $source = Source::factory()->create(['type' => 'urlhaus', 'config' => ['host_suffixes' => ['.kh']]]);
        Http::fake(['urlhaus-api.abuse.ch/*' => Http::response(['urls' => [
            ['url' => 'http://bad.example.com.kh/x.exe', 'host' => 'bad.example.com.kh', 'threat' => 'malware_download', 'urlhaus_reference' => 'https://urlhaus.abuse.ch/url/1/', 'date_added' => '2026-09-16 10:00:00 UTC', 'tags' => ['exe']],
            ['url' => 'http://other.example.org/y', 'host' => 'other.example.org', 'threat' => 'malware_download', 'urlhaus_reference' => 'https://urlhaus.abuse.ch/url/2/'],
        ]])]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame('https://urlhaus.abuse.ch/url/1/', $item->url);
        $this->assertStringContainsString('hxxp://bad[.]example[.]com[.]kh/x[.]exe', $item->excerpt);
        Http::assertSent(fn ($request) => $request->hasHeader('Auth-Key', 'key'));
    }

    public function test_telegram_bot_posts_are_stored_and_the_offset_advances()
    {
        config(['services.telegram.bot_token' => 'token']);
        $source = Source::factory()->create(['type' => 'telegram_bot', 'ai_enabled' => false, 'config' => []]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 41, 'channel_post' => ['message_id' => 9, 'date' => 1789000000, 'text' => 'Cyber advisory for partners', 'chat' => ['id' => -1001, 'title' => 'Partner CERT', 'username' => 'partnercert']]],
        ]])]);

        FetchSource::dispatchSync($source);

        $item = Item::sole();
        $this->assertSame(['https://t.me/partnercert/9', 'social', false], [$item->url, $item->kind, $item->ai_allowed]);
        $this->assertSame(42, $source->fresh()->state['offset']);
    }

    public function test_dispatch_command_queues_only_due_enabled_sources()
    {
        Queue::fake([FetchSource::class]);
        $due = Source::factory()->create(['last_fetched_at' => now()->subHours(2), 'interval_minutes' => 60]);
        Source::factory()->create(['last_fetched_at' => now()->subMinutes(10), 'interval_minutes' => 60]);
        Source::factory()->create(['enabled' => false]);
        Source::factory()->create(['type' => 'manual']);

        $this->artisan('sources:dispatch')->assertSuccessful();

        Queue::assertPushed(FetchSource::class, 1);
        Queue::assertPushed(FetchSource::class, fn (FetchSource $job) => $job->source->is($due));
    }
}

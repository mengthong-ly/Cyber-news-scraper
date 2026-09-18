<?php

namespace Tests\Feature;

use App\Jobs\FetchSource;
use App\Models\Source;
use App\Models\User;
use App\Models\WatchlistTerm;
use App\Support\HostResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourcesAndWatchlistTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_analyst_adds_a_source_with_json_settings()
    {
        $this->actingAs(User::factory()->create())->post(route('sources.store'), [
            'name' => 'CamCERT',
            'type' => 'rss',
            'config' => '{"url": "https://93.184.215.14/feed/", "language": "km"}',
            'interval_minutes' => 60,
            'enabled' => '1',
            'ai_enabled' => '1',
        ])->assertSessionHasNoErrors();

        $source = Source::sole();
        $this->assertSame(['url' => 'https://93.184.215.14/feed/', 'language' => 'km'], $source->config);
    }

    public function test_source_validation_rejects_missing_settings_bad_json_and_internal_hosts()
    {
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            protected function lookup(string $host): array
            {
                return ['192.168.1.10'];
            }
        });

        $analyst = User::factory()->create();
        $base = ['name' => 'X', 'type' => 'html_xpath', 'interval_minutes' => 60];

        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'config' => '{"url": "https://93.184.215.14/"}'])
            ->assertSessionHasErrors('config');
        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'config' => '{not json'])
            ->assertSessionHasErrors('config');
        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'type' => 'rss', 'config' => '{"url": "http://127.0.0.1/admin"}'])
            ->assertSessionHasErrors('config.url');
        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'type' => 'rss', 'config' => '{"url": "http://169.254.169.254/latest"}'])
            ->assertSessionHasErrors('config.url');
        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'type' => 'rss', 'config' => '{"url": "http://[::ffff:127.0.0.1]/"}'])
            ->assertSessionHasErrors('config.url');
        $this->actingAs($analyst)->post(route('sources.store'), [...$base, 'type' => 'rss', 'config' => '{"url": "https://intranet.example/feed"}'])
            ->assertSessionHasErrors('config.url');

        $this->assertDatabaseCount('sources', 0);
    }

    public function test_fetch_now_queues_the_source()
    {
        Queue::fake([FetchSource::class]);
        $source = Source::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('sources.fetch', $source))->assertRedirect();

        Queue::assertPushed(FetchSource::class, fn (FetchSource $job) => $job->source->is($source));
    }

    public function test_watchlist_terms_are_added_per_kind_with_their_own_errors()
    {
        $analyst = User::factory()->create();
        WatchlistTerm::factory()->create(['kind' => 'actor', 'term' => 'Qilin']);

        $this->actingAs($analyst)->post(route('watchlist.store'), ['kind' => 'domain', 'term' => ' mod.gov.kh '])->assertSessionHasNoErrors();
        $this->actingAs($analyst)->post(route('watchlist.store'), ['kind' => 'actor', 'term' => 'Qilin'])->assertSessionHasErrors('term', errorBag: 'actor');

        $this->assertTrue(WatchlistTerm::where(['kind' => 'domain', 'term' => 'mod.gov.kh'])->exists());
    }
}

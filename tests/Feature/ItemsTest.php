<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_filters_narrow_the_feed_and_searches_are_audited()
    {
        Item::factory()->create(['country_code' => 'KH', 'category' => 'attack', 'is_cambodia' => true, 'severity' => 4, 'title' => 'Ministry site defaced']);
        Item::factory()->create(['country_code' => 'KH', 'category' => 'attack', 'is_cambodia' => true, 'severity' => 2]);
        Item::factory()->create(['country_code' => 'FR', 'category' => 'attack']);

        $this->actingAs(User::factory()->viewer()->create())
            ->get(route('items.index', ['cambodia' => 1, 'min_severity' => 4, 'q' => 'defaced']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('items/index')
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Ministry site defaced')
                ->where('items.data.0.country', 'Cambodia'));

        $this->assertSame('defaced', AuditLog::where('action', 'search')->sole()->subject);
    }

    public function test_export_is_filtered_audited_and_neutralises_spreadsheet_formulas()
    {
        Item::factory()->create(['country_code' => 'KH', 'title' => '=HYPERLINK("http://evil")']);
        Item::factory()->create(['country_code' => 'FR', 'title' => 'France headline']);

        $response = $this->actingAs(User::factory()->viewer()->create())->get(route('items.export', ['country' => 'KH']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('France headline', $csv);
        $this->assertTrue(AuditLog::where('action', 'export')->exists());
    }

    public function test_analysts_record_public_posts_with_a_private_screenshot()
    {
        Storage::fake('local');
        Notification::fake();
        $analyst = User::factory()->create();

        $this->actingAs($analyst)->post(route('items.store'), [
            'url' => 'https://www.facebook.com/fakepage/posts/1',
            'title' => 'Fake ministry page asks for passwords',
            'platform' => 'Facebook',
            'severity' => 4,
            'is_cambodia' => '1',
            'ai_allowed' => '0',
            'notes' => 'Impersonation',
            'screenshot' => UploadedFile::fake()->image('shot.png'),
        ])->assertRedirect(route('items.index'));

        $item = Item::sole();
        $this->assertSame(['social', 'Manual: Facebook', 4, true, false, 'skipped'], [
            $item->kind, $item->publisher, $item->severity, $item->is_cambodia, $item->ai_allowed, $item->enrichment_status,
        ]);
        $this->assertSame($analyst->id, $item->submitted_by);
        $this->assertNotNull($item->alert);
        Storage::disk('local')->assertExists($item->screenshot_path);

        $this->actingAs(User::factory()->viewer()->create())->get(route('items.screenshot', $item))->assertOk();
    }

    public function test_duplicate_posts_are_rejected()
    {
        Item::factory()->create(['url' => 'https://x.com/a/1', 'url_hash' => sha1('https://x.com/a/1')]);

        $this->actingAs(User::factory()->create())->post(route('items.store'), [
            'url' => 'https://x.com/a/1', 'title' => 'Again', 'platform' => 'X', 'ai_allowed' => '1',
        ])->assertSessionHasErrors('url');

        $this->assertSame(1, Item::count());
    }

    public function test_analyst_corrections_can_raise_an_alert()
    {
        Notification::fake();
        $item = Item::factory()->create(['is_cambodia' => true, 'severity' => 2, 'enrichment_status' => 'needs_review']);

        $this->actingAs(User::factory()->create())
            ->patch(route('items.update', $item), ['severity' => 5, 'reviewed' => true])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame([5, 'done'], [$item->severity, $item->enrichment_status]);
        $this->assertNotNull($item->alert);
    }

    public function test_old_items_are_pruned_unless_part_of_an_incident()
    {
        $old = Item::factory()->create(['created_at' => now()->subMonths(13)]);
        $kept = Item::factory()->create(['created_at' => now()->subMonths(13)]);
        $kept->incidents()->create(['title' => 'Keep']);
        $recent = Item::factory()->create();

        $this->artisan('model:prune', ['--model' => [Item::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($kept);
        $this->assertModelExists($recent);
    }
}

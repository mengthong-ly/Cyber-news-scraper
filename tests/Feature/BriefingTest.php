<?php

namespace Tests\Feature;

use App\Models\Briefing;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\FakesClaude;
use Tests\TestCase;

class BriefingTest extends TestCase
{
    use FakesClaude, LazilyRefreshDatabase;

    public function test_briefing_is_written_from_recent_ai_allowed_items()
    {
        config(['cyber.ai.enabled' => true]);
        $this->freezeTime();
        Item::factory()->cambodianAlert()->create(['title' => 'Bank data leaked']);
        Item::factory()->cambodianAlert()->create(['title' => 'Telegram-only claim', 'ai_allowed' => false]);
        $this->fakeClaude([self::jsonResponse(self::claudeMessage(['body_en' => '# Key points', 'body_km' => '# ចំណុចសំខាន់']))]);

        $this->artisan('briefing:generate')->assertSuccessful();

        $briefing = Briefing::sole();
        $this->assertSame(['ready', '# Key points', '# ចំណុចសំខាន់'], [$briefing->status, $briefing->body_en, $briefing->body_km]);

        $prompt = json_decode((string) $this->claudeRequests[0]['request']->getBody(), true)['messages'][0]['content'];
        $this->assertStringContainsString('Bank data leaked', $prompt);
        $this->assertStringNotContainsString('Telegram-only claim', $prompt);

        $this->actingAs(User::factory()->viewer()->create())->get(route('briefings.index'))
            ->assertInertia(fn ($page) => $page->component('briefings/show')->where('briefing.body_km', '# ចំណុចសំខាន់'));
    }

    public function test_a_refused_briefing_is_marked_failed()
    {
        config(['cyber.ai.enabled' => true]);
        Item::factory()->cambodianAlert()->create();
        $this->fakeClaude([self::jsonResponse(self::claudeMessage('', 'refusal'))]);

        $this->artisan('briefing:generate')->assertFailed();

        $this->assertSame('failed', Briefing::sole()->status);
    }

    public function test_no_items_means_an_empty_briefing_without_calling_the_api()
    {
        config(['cyber.ai.enabled' => true]);
        $this->fakeClaude([]);

        $this->artisan('briefing:generate')->assertSuccessful();

        $this->assertSame('empty', Briefing::sole()->status);
        $this->assertSame([], $this->claudeRequests);
    }

    public function test_viewers_cannot_trigger_a_briefing()
    {
        config(['cyber.ai.enabled' => true]);

        $this->actingAs(User::factory()->viewer()->create())->post(route('briefings.store'))->assertForbidden();
    }
}

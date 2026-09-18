<?php

namespace Tests\Feature;

use Anthropic\Messages\Message;
use App\Models\Alert;
use App\Models\EnrichmentBatch;
use App\Models\Item;
use App\Support\AlertRaiser;
use App\Support\Enricher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FakesClaude;
use Tests\TestCase;

class EnrichmentTest extends TestCase
{
    use FakesClaude, LazilyRefreshDatabase;

    public function test_submit_sends_only_pending_ai_allowed_items_as_a_batch()
    {
        $pending = Item::factory()->create(['enrichment_status' => 'pending', 'title' => 'ហេគឃ័រវាយប្រហារ']);
        Item::factory()->create(['enrichment_status' => 'pending', 'ai_allowed' => false]);
        Item::factory()->create(['enrichment_status' => 'skipped']);
        $this->fakeClaude([self::jsonResponse(self::batch('in_progress'))]);

        $record = app(Enricher::class)->submit();

        $this->assertSame([$pending->id], $record->item_ids);
        $this->assertSame('queued', $pending->fresh()->enrichment_status);

        $body = json_decode((string) $this->claudeRequests[0]['request']->getBody(), true);
        $this->assertCount(1, $body['requests']);
        $this->assertSame("item-{$pending->id}", $body['requests'][0]['custom_id']);
        $this->assertSame('claude-opus-5', $body['requests'][0]['params']['model']);
        $this->assertSame('json_schema', $body['requests'][0]['params']['output_config']['format']['type']);
        $this->assertStringContainsString('ហេគឃ័រវាយប្រហារ', $body['requests'][0]['params']['messages'][0]['content']);
    }

    public function test_collect_applies_labels_raises_alerts_and_flags_refusals()
    {
        Notification::fake();
        $labelled = Item::factory()->create(['enrichment_status' => 'queued', 'severity' => 2, 'title' => 'ក្រសួងត្រូវបានវាយប្រហារ']);
        $refused = Item::factory()->create(['enrichment_status' => 'queued']);
        $missing = Item::factory()->create(['enrichment_status' => 'queued']);
        $record = EnrichmentBatch::create(['batch_id' => 'msgbatch_test', 'item_ids' => [$labelled->id, $refused->id, $missing->id]]);

        $this->fakeClaude([
            self::jsonResponse(self::batch('ended')),
            self::jsonlResponse([
                ['custom_id' => "item-{$labelled->id}", 'result' => ['type' => 'succeeded', 'message' => self::claudeMessage([
                    'is_cambodia' => true,
                    'title_en' => 'Ministry website attacked',
                    'summary_en' => 'Hackers defaced a ministry website.',
                    'category' => 'attack',
                    'severity' => 5,
                    'entities' => ['orgs' => [], 'domains' => ['example.gov.kh'], 'cves' => [], 'threat_actors' => ['Group X']],
                    'confidence' => 'high',
                ])]],
                ['custom_id' => "item-{$refused->id}", 'result' => ['type' => 'succeeded', 'message' => self::claudeMessage('', 'refusal')]],
            ]),
        ]);

        $done = app(Enricher::class)->collect($record, app(AlertRaiser::class));

        $this->assertTrue($done);
        $labelled->refresh();
        $this->assertSame('Ministry website attacked', $labelled->title_en);
        $this->assertSame(5, $labelled->severity);
        $this->assertTrue($labelled->is_cambodia);
        $this->assertSame(['Group X'], $labelled->entities['threat_actors']);
        $this->assertSame('done', $labelled->enrichment_status);
        $this->assertTrue(Alert::where('item_id', $labelled->id)->exists());

        $this->assertSame('needs_review', $refused->fresh()->enrichment_status);
        $this->assertSame('pending', $missing->fresh()->enrichment_status, 'items the batch did not return are retried');
        $this->assertSame(['ended', 1, 1], [$record->status, $record->succeeded, $record->failed]);
    }

    public function test_the_model_cannot_lower_a_rule_based_severity()
    {
        $item = Item::factory()->create(['severity' => 5, 'is_cambodia' => true]);

        $applied = app(Enricher::class)->apply($item, Message::fromArray(self::claudeMessage([
            'is_cambodia' => false, 'title_en' => 't', 'summary_en' => 's', 'category' => 'general',
            'severity' => 1, 'entities' => ['orgs' => [], 'domains' => [], 'cves' => [], 'threat_actors' => []], 'confidence' => 'low',
        ])));

        $this->assertTrue($applied);
        $item->refresh();
        $this->assertSame(5, $item->severity);
        $this->assertTrue($item->is_cambodia);
        $this->assertSame('needs_review', $item->enrichment_status);
    }

    public function test_collect_waits_while_the_batch_is_processing()
    {
        $item = Item::factory()->create(['enrichment_status' => 'queued']);
        $record = EnrichmentBatch::create(['batch_id' => 'msgbatch_test', 'item_ids' => [$item->id]]);
        $this->fakeClaude([self::jsonResponse(self::batch('in_progress'))]);

        $this->assertFalse(app(Enricher::class)->collect($record, app(AlertRaiser::class)));
        $this->assertSame('in_progress', $record->fresh()->status);
        $this->assertSame('queued', $item->fresh()->enrichment_status);
    }

    public function test_submit_command_does_nothing_while_ai_is_disabled()
    {
        Item::factory()->create(['enrichment_status' => 'pending']);
        $this->fakeClaude([]);

        $this->artisan('enrich:submit')->assertSuccessful();

        $this->assertSame([], $this->claudeRequests);
        $this->assertDatabaseCount('enrichment_batches', 0);
    }
}

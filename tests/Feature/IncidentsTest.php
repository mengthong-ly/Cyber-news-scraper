<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\Item;
use App\Models\User;
use App\Support\AlertRaiser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IncidentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_analyst_opens_an_incident_from_an_item_and_works_it()
    {
        $analyst = User::factory()->create();
        $item = Item::factory()->create(['title' => 'Leak at a Cambodian bank', 'severity' => 5]);
        $other = Item::factory()->create();

        $this->actingAs($analyst)->post(route('incidents.store'), ['item_id' => $item->id]);

        $incident = Incident::sole();
        $this->assertSame(['Leak at a Cambodian bank', 5, $analyst->id], [$incident->title, $incident->severity, $incident->assignee_id]);

        $this->actingAs($analyst)->post(route('incidents.items.attach', $incident), ['item_id' => $other->id]);
        $this->actingAs($analyst)->post(route('incidents.notes.store', $incident), ['body' => 'Bank notified.']);
        $this->actingAs($analyst)->patch(route('incidents.update', $incident), ['status' => 'closed']);

        $incident->refresh();
        $this->assertSame(2, $incident->items()->count());
        $this->assertSame('Bank notified.', $incident->notes()->sole()->body);
        $this->assertNotNull($incident->closed_at);

        $this->actingAs($analyst)->get(route('incidents.show', $incident))->assertOk();
    }

    public function test_new_alerts_join_an_open_incident_that_shares_an_indicator()
    {
        Notification::fake();
        $incident = Incident::factory()->create();
        $incident->items()->attach(Item::factory()->create(['entities' => ['threat_actors' => ['Qilin']]]));
        $closed = Incident::factory()->create(['status' => 'closed']);
        $closed->items()->attach(Item::factory()->create(['entities' => ['cves' => ['CVE-2026-9']]]));

        $related = Item::factory()->cambodianAlert()->create(['entities' => ['threat_actors' => ['QILIN']]]);
        $unrelated = Item::factory()->cambodianAlert()->create(['entities' => ['cves' => ['CVE-2026-9']]]);

        app(AlertRaiser::class)->evaluate($related);
        app(AlertRaiser::class)->evaluate($unrelated);

        $this->assertTrue($incident->items()->whereKey($related->id)->exists());
        $this->assertFalse($closed->items()->whereKey($unrelated->id)->exists());
        $this->assertSame(2, Alert::count());
    }

    public function test_acknowledging_an_alert_hides_it_from_the_open_list()
    {
        Notification::fake();
        $alert = app(AlertRaiser::class)->evaluate(Item::factory()->cambodianAlert()->create());
        $analyst = User::factory()->create();

        $this->actingAs($analyst)->post(route('alerts.acknowledge', $alert))->assertRedirect();

        $this->assertSame($analyst->id, $alert->fresh()->acknowledged_by);
        $this->actingAs($analyst)->get(route('alerts.index'))
            ->assertInertia(fn ($page) => $page->has('alerts.data', 0));
    }

    public function test_items_below_the_threshold_or_outside_cambodia_do_not_alert()
    {
        $this->assertNull(app(AlertRaiser::class)->evaluate(Item::factory()->create(['is_cambodia' => true, 'severity' => 3])));
        $this->assertNull(app(AlertRaiser::class)->evaluate(Item::factory()->create(['is_cambodia' => false, 'severity' => 5])));
    }
}

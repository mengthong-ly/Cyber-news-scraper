<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_the_map_gets_every_country_with_recent_items()
    {
        $codes = ['KH', 'US', 'TH', 'VN', 'FR', 'JP', 'DE', 'GB', 'SG', 'IN'];

        foreach ($codes as $code) {
            Item::factory()->create(['country_code' => $code]);
        }

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('topCountries', count($codes)));
    }
}

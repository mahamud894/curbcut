<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_dashboard_opens_the_sites_overview()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect('/sites');
        $this->get('/sites')->assertOk();
    }
}

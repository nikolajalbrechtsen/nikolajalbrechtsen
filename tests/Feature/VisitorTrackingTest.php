<?php

namespace Tests\Feature;

use App\Filament\Widgets\VisitorStats;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function visit(string $url, string $ip, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $agent)->get($url);
    }

    public function test_unique_visitors_are_counted_by_ip(): void
    {
        $this->visit('/', '10.0.0.1');
        $this->visit('/about', '10.0.0.1');
        $this->visit('/blog', '10.0.0.2');

        $this->assertSame(2, DB::table('visits')->distinct()->count('ip_hash'));
        $this->assertSame(2, (int) DB::table('visits')->max('hits'));
        // raw IPs are never stored
        $this->assertSame(0, DB::table('visits')->where('ip_hash', 'like', '10.0.0%')->count());
    }

    public function test_bots_admin_and_errors_are_ignored(): void
    {
        $this->visit('/', '10.0.0.3', 'Googlebot/2.1');
        $this->visit('/admin/login', '10.0.0.4');
        $this->visit('/does-not-exist', '10.0.0.5');

        $this->assertSame(0, DB::table('visits')->count());
    }

    public function test_dashboard_shows_total_visitors(): void
    {
        $this->visit('/', '10.0.0.1');
        $this->visit('/', '10.0.0.2');

        $this->actingAs(User::role('super_admin')->first());
        $this->get('/admin')->assertOk();

        Livewire::test(VisitorStats::class)
            ->assertSee('Total visitors')
            ->assertSee('Visitors today')
            ->assertSee('Unique by IP');
    }
}

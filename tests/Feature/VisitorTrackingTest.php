<?php

namespace Tests\Feature;

use App\Filament\Resources\Visitors\Pages\ListVisitors;
use App\Filament\Resources\Visitors\VisitorResource;
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

    public function test_cloudflare_client_ip_is_used_even_if_forwarded_for_changes(): void
    {
        foreach (['/', '/about', '/blog'] as $i => $url) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->withHeaders([
                    'CF-Connecting-IP' => '203.0.113.7',
                    'X-Forwarded-For' => "203.0.113.7, 172.68.0.{$i}",
                    'User-Agent' => 'Mozilla/5.0 Chrome/130',
                ])->get($url);
        }

        $this->assertSame(1, DB::table('visits')->count());
        $this->assertSame(3, (int) DB::table('visits')->value('hits'));
    }

    public function test_rotating_ipv6_privacy_addresses_count_as_one_visitor(): void
    {
        $this->visit('/', '2001:db8:abcd:12::1');
        $this->visit('/about', '2001:db8:abcd:12:a1b2:c3d4:e5f6:7788');
        $this->visit('/blog', '2001:db8:abcd:99::1'); // different network

        $this->assertSame(2, DB::table('visits')->count());
    }

    public function test_ip_and_location_are_stored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'CF-Connecting-IP' => '203.0.113.7',
                'CF-IPCountry' => 'DK',
                'CF-IPCity' => 'Copenhagen',
                'CF-Region' => 'Capital Region',
                'Referer' => 'https://www.google.com/',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130',
            ])->get('/about');

        $visit = DB::table('visits')->first();
        $this->assertSame('203.0.113.7', $visit->ip);
        $this->assertSame('DK', $visit->country);
        $this->assertSame('Copenhagen', $visit->city);
        $this->assertSame('Capital Region', $visit->region);
        $this->assertSame('https://www.google.com/', $visit->referrer);
        $this->assertSame('/about', $visit->last_path);
    }

    public function test_visitors_page_lists_each_ip_once(): void
    {
        $this->visit('/', '10.0.0.1');
        $this->visit('/about', '10.0.0.1');
        $this->visit('/', '10.0.0.2');

        $this->actingAs(User::role('super_admin')->first());

        $this->get(VisitorResource::getUrl())
            ->assertOk()
            ->assertSee('10.0.0.1')
            ->assertSee('10.0.0.2');

        Livewire::test(ListVisitors::class)->assertCountTableRecords(2);
        Livewire::test(VisitorStats::class)->assertSee(VisitorResource::getUrl(), false);
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

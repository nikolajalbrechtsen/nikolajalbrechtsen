<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HttpsProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cloudflare_tunnel_requests_get_https_links(): void
    {
        $this->withHeaders([

            'CF-Visitor' => '{"scheme":"https"}',
            'X-Forwarded-Proto' => 'https',
        ])->get('http://nikolajalbrechtsen.com/')
            ->assertOk()
            ->assertSee('https://nikolajalbrechtsen.com/login', false)
            ->assertDontSee('http://nikolajalbrechtsen.com/login', false);
    }

    public function test_cf_visitor_header_alone_is_enough(): void
    {
        $this->withHeaders(['CF-Visitor' => '{"scheme":"https"}'])
            ->get('http://nikolajalbrechtsen.com/')
            ->assertSee('https://nikolajalbrechtsen.com/login', false);
    }

    public function test_lan_access_by_ip_keeps_http_links(): void
    {
        $this->withHeaders([])
            ->get('http://192.168.1.50/')
            ->assertOk()
            ->assertSee('http://192.168.1.50/login', false);
    }

    public function test_real_visitor_ip_is_used_behind_the_tunnel(): void
    {
        // cloudflared connects from 127.0.0.1 and passes the visitor IP along
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7', 'User-Agent' => 'Mozilla/5.0 Chrome/130'])
            ->get('/');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.9', 'User-Agent' => 'Mozilla/5.0 Chrome/130'])
            ->get('/');

        $this->assertSame(2, DB::table('visits')->distinct()->count('ip_hash'));
    }
}

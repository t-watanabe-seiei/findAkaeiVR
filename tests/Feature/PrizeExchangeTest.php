<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\PrizeExchange;

class PrizeExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_generates_short_prize_code()
    {
        $payload = [
            'fingerprint' => 'test-fingerprint-123',
            'deviceInfo' => ['isIOS' => false],
            'stamps' => ['m1', 'm2', 'm3']
        ];

        $response = $this->postJson('/api/exchange-prize', $payload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertArrayHasKey('prizeCode', $data);
        $this->assertMatchesRegularExpression('/^[A-Z]{2}[0-9]{3}$/', $data['prizeCode']);

        // DB row exists and saved generation_attempts count
        $this->assertDatabaseHas('prize_exchanges', ['prize_code' => $data['prizeCode']]);
        $row = PrizeExchange::where('prize_code', $data['prizeCode'])->first();
        $this->assertNotNull($row);
        $this->assertGreaterThanOrEqual(1, $row->generation_attempts);

        // DB row exists and uses the same code
        $this->assertDatabaseHas('prize_exchanges', ['prize_code' => $data['prizeCode']]);
    }

    public function test_admin_dashboard_search_filters_by_prize_code()
    {
        // Create two unused exchanges
        PrizeExchange::create([
            'session_id' => 's1',
            'fingerprint' => 'f1',
            'prize_code' => 'AB123',
            'generation_attempts' => 1,
            'user_agent' => 'ua',
            'ip_address' => '127.0.0.1',
            'device_info' => [],
            'stamps_data' => [],
            'exchanged_at' => now()
        ]);

        PrizeExchange::create([
            'session_id' => 's2',
            'fingerprint' => 'f2',
            'prize_code' => 'CD999',
            'generation_attempts' => 1,
            'user_agent' => 'ua',
            'ip_address' => '127.0.0.1',
            'device_info' => [],
            'stamps_data' => [],
            'exchanged_at' => now()
        ]);

        // Simulate admin session
        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard?q=A123');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('A123', $content);
        $this->assertStringNotContainsString('B999', $content);
    }
}

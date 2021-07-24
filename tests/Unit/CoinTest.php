<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class CoinTest extends TestCase
{
    public function testCoinListSuccessfully()
    {
        $this->json('GET', 'api/v1/coins', ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonStructure([
                ["name", "code"]
            ]);
    }

    public function testCoinTickerSuccessfully()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $coinCode = "bitcoin";
        $response = $this->json('GET', 'api/v1/ticker/' . $coinCode, ['Accept' => 'application/json']);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'code', 'price', 'volume', 'daily_change', 'last_updated'
            ]);
    }

    public function testCoinTickerInvalidCoinCode()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $coinCode = "invalid_code";
        $this->json('GET', 'api/v1/ticker/' . $coinCode, ['Accept' => 'application/json'])
            ->assertStatus(404)
            ->assertJsonStructure([
                "error",
                "error_message",
                "timestamp"
            ]);
    }
}

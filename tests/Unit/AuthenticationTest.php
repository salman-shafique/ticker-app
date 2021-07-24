<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function testRequiredFieldsForRegistration()
    {
        $this->json('POST', 'api/v1/register', ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonStructure([
                "error",
                "error_message",
                "timestamp"
            ]);
    }


    public function testSuccessfulRegistration()
    {
        $userData = [
            "name" => "John Doe",
            "email" => "doe@example.com",
            "password" => "user12345"
        ];

        $this->json('POST', 'api/v1/register', $userData, ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonStructure([
                "user" => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                "access_token",
                "expires_in"
            ]);
    }


    public function testMustEnterEmailAndPassword()
    {
        $this->json('POST', 'api/v1/login', ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonStructure([
                "error",
                "error_message",
                "timestamp"
            ]);
    }

    public function testSuccessfulLogin()
    {
        $user = factory(User::class)->create([
            "name" => "John Doe",
            "email" => "john@example.com",
            "password" => "user123"
        ]);
        $loginData = ['email' => $user->email, 'password' => 'user123'];
        $response = $this->json('POST', 'api/v1/login', $loginData, ['Accept' => 'application/json']);
        $response->assertStatus(200)
            ->assertJsonStructure([
                "user" => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                "access_token",
                "token_type",
                "expires_in",
            ]);

        $this->assertAuthenticated();
    }
}

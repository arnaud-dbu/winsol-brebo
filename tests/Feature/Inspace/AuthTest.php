<?php

namespace Tests\Feature\Inspace;

use Tests\TestCase;

class AuthTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_missing_token_gives_401(): void
    {
        $this->getJson('/api/inspace/v1/schema')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Ontbrekend of ongeldig token.');
    }

    public function test_wrong_token_gives_401(): void
    {
        $this->withToken('niet-dit-token')
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(401);
    }

    public function test_valid_token_gives_200(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(200);
    }
}

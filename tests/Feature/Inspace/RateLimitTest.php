<?php

namespace Tests\Feature\Inspace;

use Illuminate\Cache\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
        config()->set('inspace.rate_limit', 3);

        // `file_testing` (config/cache.php) is een echte, op schijf
        // persistente store die de hele testrun overleeft: zonder deze reset
        // zou een teller van een eerdere test(klasse) op dezelfde ip- of
        // tokensleutel de exacte aantallen hieronder laten afwijken. De
        // sleutel is precies wat `ThrottleRequests::handleRequestUsingNamedLimiter()`
        // opbouwt: `md5($limiterName.$limit->key)`.
        $limiter = app(RateLimiter::class);
        $limiter->clear(md5('inspace127.0.0.1'));
        $limiter->clear(md5('inspacetest'));
    }

    /**
     * Vóór de fix stond `inspace.token` vóór `throttle:inspace`, dus een
     * ongeldig token kreeg zijn `401` al van de tokenmiddleware zonder dat de
     * throttle ooit draaide — een brute-force van foute tokens telde dus
     * nooit mee. Met de omgedraaide volgorde telt elke poging mee, geldig of
     * niet.
     */
    public function test_invalid_tokens_are_throttled_too(): void
    {
        foreach (range(1, 3) as $_) {
            $this->withToken('niet-dit-token')
                ->getJson('/api/inspace/v1/schema')
                ->assertStatus(401);
        }

        $this->withToken('niet-dit-token')
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(429);
    }

    /**
     * De throttle-sleutel valt op het tokenlabel met een ip-terugval: een
     * uitgeputte ip-emmer (gevuld door ongeldige tokens) mag een geldig token
     * niet raken, want dat heeft zijn eigen emmer.
     */
    public function test_a_valid_token_is_not_throttled_by_invalid_attempts_from_the_same_ip(): void
    {
        foreach (range(1, 3) as $_) {
            $this->withToken('niet-dit-token')->getJson('/api/inspace/v1/schema');
        }

        $this->withToken('niet-dit-token')
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(429);

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(200);
    }
}

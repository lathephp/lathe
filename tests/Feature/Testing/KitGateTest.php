<?php

namespace Lathe\Tests\Feature\Testing;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Lathe\Testing\LatheTest;
use Lathe\Testing\TestCase;

/**
 * A class-based test, because Pest binds the Feature folder to the package's own TestCase.
 */
class KitGateTest extends TestCase
{
    public function test_the_default_gate_admits_every_signed_in_user(): void
    {
        LatheTest::actingAs(new GenericUser(['id' => 1, 'email' => 'anyone@example.com']));

        $this->assertTrue(Lathe::check(request()));
    }

    public function test_a_redefined_gate_gives_403(): void
    {
        Gate::define('viewLathe', fn () => false);
        LatheTest::actingAs(new GenericUser(['id' => 1]));

        $this->getJson('/lathe/api/resources/orders')->assertForbidden();
    }

    public function test_a_visitor_with_no_session_stays_out(): void
    {
        $this->assertFalse(Lathe::check(request()));
    }

    public function test_acting_as_signs_in_on_the_panel_guard(): void
    {
        config(['lathe.guard' => 'admin', 'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users']]);
        $user = new GenericUser(['id' => 7]);

        LatheTest::actingAs($user);

        $this->assertSame('admin', app(FortifyConfig::class)->guard());
        $this->assertSame($user, auth()->guard('admin')->user());
    }
}

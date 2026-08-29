<?php

namespace Tests\Unit;

use App\Support\ActorIdentity;
use Illuminate\Auth\GenericUser;
use Tests\TestCase;

class ActorIdentityTest extends TestCase
{
    public function test_authenticated_actor_is_resolved_to_ssot_user_id(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-001', 'User_ID' => 'USR-001']));

        $this->assertSame('USR-001', ActorIdentity::required());
        $this->assertSame('USR-001', ActorIdentity::resolve('SYSTEM'));
        $this->assertSame('USR-001', ActorIdentity::resolve('SPOOFED-ACTOR'));
    }

    public function test_explicit_system_actor_is_allowed_for_non_web_automation(): void
    {
        auth()->logout();

        $this->assertNull(request()->route());
        $this->assertSame('SYSTEM', ActorIdentity::resolve('SYSTEM'));
    }

    public function test_missing_actor_fails_closed(): void
    {
        auth()->logout();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        ActorIdentity::resolve();
    }
}

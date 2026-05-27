<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_token_is_long_and_unique(): void
    {
        $a = Invitation::generateToken();
        $b = Invitation::generateToken();

        $this->assertGreaterThanOrEqual(40, strlen($a));
        $this->assertNotSame($a, $b);
    }

    public function test_pending_state_helpers(): void
    {
        $pending = Invitation::factory()->create();
        $expired = Invitation::factory()->expired()->create();
        $accepted = Invitation::factory()->accepted()->create();

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isExpired());

        $this->assertFalse($expired->isPending());
        $this->assertTrue($expired->isExpired());

        $this->assertFalse($accepted->isPending());
        $this->assertFalse($accepted->isExpired());
    }

    public function test_pending_scope_returns_only_usable_invites(): void
    {
        Invitation::factory()->create();
        Invitation::factory()->expired()->create();
        Invitation::factory()->accepted()->create();

        $this->assertSame(1, Invitation::pending()->count());
    }
}

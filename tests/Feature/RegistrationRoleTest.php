<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for a privilege-escalation hole: the registration form
     * used to expose an "Account type" dropdown, and the controller accepted
     * whatever it was sent — so anyone could sign up as an administrator.
     */
    public function test_a_visitor_cannot_register_themselves_as_an_administrator(): void
    {
        $this->post('/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'role' => 'administrator',      // attacker-supplied
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $user = User::where('email', 'mallory@example.com')->firstOrFail();

        $this->assertSame(UserRole::Researcher, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_new_accounts_default_to_the_researcher_role(): void
    {
        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $this->assertSame(
            UserRole::Researcher,
            User::where('email', 'ada@example.com')->firstOrFail()->role
        );
    }

    public function test_the_registration_form_no_longer_offers_a_role_selector(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee('name="role"', false);
    }
}

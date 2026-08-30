<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;

/*
| Requirement 22: administrators "manage user accounts and roles".
|
| Role management alone left an administrator with no lever over a bad
| account — the report queue could surface an abusive user and the only
| response available was hiding their comments one at a time.
|
| Suspension rather than deletion: deleting a user cascades through their
| papers, collections, notes, highlights and comments, destroying a library to
| silence an account, with no undo.
*/

function admin(): User
{
    return User::factory()->create(['role' => UserRole::Administrator]);
}

it('lets an admin suspend an account', function () {
    $a = admin();
    $user = User::factory()->create();

    actingAs($a)->patch(route('admin.users.suspension', $user), ['reason' => 'Repeated abuse'])
        ->assertRedirect();

    $user->refresh();

    expect($user->isSuspended())->toBeTrue()
        ->and($user->suspended_by)->toBe($a->id)
        ->and($user->suspension_reason)->toBe('Repeated abuse')
        ->and($user->suspended_at)->not->toBeNull();
});

it('stops a suspended user signing in', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);
    $user->suspended_at = now();
    $user->save();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-horse'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('says the account is suspended rather than pretending the password is wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);
    $user->suspended_at = now();
    $user->save();

    // Someone locked out needs to know it was a decision, not a typo, or they
    // will reset their password repeatedly and never get in.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-horse'])
        ->assertSessionHasErrors(['email' => trans('auth.suspended')]);
});

it('does not reveal a suspension to someone who has the wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);
    $user->suspended_at = now();
    $user->save();

    // The suspension check runs after the credential check, so a stranger
    // guessing passwords gets the ordinary failure and learns nothing about
    // the account's state.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

it('signs out a user who is suspended mid-session', function () {
    $user = User::factory()->create();

    actingAs($user)->get(route('dashboard'))->assertOk();

    // Suspended while they are already signed in.
    $user->suspended_at = now();
    $user->save();

    // Checking only at the login gate would let them carry on for weeks on a
    // remembered session.
    actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('lets an admin reinstate an account', function () {
    $a = admin();
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    actingAs($a)->patch(route('admin.users.suspension', $user), ['reason' => 'Mistake']);
    expect($user->fresh()->isSuspended())->toBeTrue();

    actingAs($a)->patch(route('admin.users.suspension', $user))->assertRedirect();

    $user->refresh();
    expect($user->isSuspended())->toBeFalse()
        ->and($user->suspended_by)->toBeNull()
        ->and($user->suspension_reason)->toBeNull();

    // And they can sign in again - as themselves, so the admin session that
    // performed the reinstatement has to end first.
    auth()->logout();
    session()->invalidate();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-horse']);
    $this->assertAuthenticatedAs($user);
});

it('refuses to let an admin suspend themselves', function () {
    $a = admin();

    // Unrecoverable without database access.
    actingAs($a)->patch(route('admin.users.suspension', $a))->assertRedirect();

    expect($a->fresh()->isSuspended())->toBeFalse();
});

it('keeps researchers away from the suspension control', function () {
    $target = User::factory()->create();

    actingAs(User::factory()->create())
        ->patch(route('admin.users.suspension', $target))
        ->assertForbidden();

    expect($target->fresh()->isSuspended())->toBeFalse();
});

it('shows suspension state on the admin users page', function () {
    $a = admin();
    $user = User::factory()->create(['name' => 'Blocked Person']);

    actingAs($a)->get(route('admin.users'))->assertOk()->assertSee('Suspend');

    actingAs($a)->patch(route('admin.users.suspension', $user), ['reason' => 'Spamming']);

    actingAs($a)->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Blocked Person')
        ->assertSee('Suspended')
        ->assertSee('Reinstate')
        ->assertSee('Spamming');
});

it('keeps a suspended user\'s work intact', function () {
    $a = admin();
    $user = User::factory()->create();
    $paper = App\Models\Paper::create(['title' => 'Their Paper', 'user_id' => $user->id]);

    actingAs($a)->patch(route('admin.users.suspension', $user), ['reason' => 'Under review']);

    // Suspension is not deletion: the library survives, which is the whole
    // reason for preferring it.
    expect(App\Models\Paper::find($paper->id))->not->toBeNull()
        ->and(User::find($user->id))->not->toBeNull();
});

it('keeps the two auth locales in step', function () {
    expect(array_keys(require base_path('lang/en/auth.php')))
        ->toEqual(array_keys(require base_path('lang/bn/auth.php')));
});

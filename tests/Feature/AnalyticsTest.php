<?php

use App\Enums\ReadingStatus;
use App\Models\Paper;
use App\Models\User;
use App\Services\AnalyticsService;

use function Pest\Laravel\actingAs;

/*
| Requirement 21: "an analytics dashboard showing papers added over time and
| breakdowns by year, venue, tag, and reading status."
|
| Venue was the breakdown the service never had, so it gets the most cover.
*/

it('groups papers by venue, busiest first', function () {
    $user = User::factory()->create();

    foreach (['Nature', 'Nature', 'Nature', 'NeurIPS', 'NeurIPS', 'ICML'] as $venue) {
        Paper::create(['title' => 'P', 'user_id' => $user->id, 'venue' => $venue]);
    }

    $byVenue = app(AnalyticsService::class)->papersByVenue($user->id);

    expect($byVenue)->toBe(['Nature' => 3, 'NeurIPS' => 2, 'ICML' => 1]);
});

it('excludes papers with no venue from the venue breakdown', function () {
    $user = User::factory()->create();

    Paper::create(['title' => 'Has venue', 'user_id' => $user->id, 'venue' => 'Nature']);
    Paper::create(['title' => 'Null venue', 'user_id' => $user->id, 'venue' => null]);
    Paper::create(['title' => 'Blank venue', 'user_id' => $user->id, 'venue' => '']);

    $byVenue = app(AnalyticsService::class)->papersByVenue($user->id);

    // A large "Unknown" bucket would dominate the chart without meaning anything.
    expect($byVenue)->toBe(['Nature' => 1]);
});

it('never counts another researcher\'s venues', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Paper::create(['title' => 'Mine', 'user_id' => $mine->id, 'venue' => 'Nature']);
    Paper::create(['title' => 'Theirs', 'user_id' => $theirs->id, 'venue' => 'Science']);

    $byVenue = app(AnalyticsService::class)->papersByVenue($mine->id);

    expect($byVenue)->toBe(['Nature' => 1])
        ->and($byVenue)->not->toHaveKey('Science');
});

it('caps the venue list at the requested limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 15) as $i) {
        Paper::create(['title' => "P{$i}", 'user_id' => $user->id, 'venue' => "Venue {$i}"]);
    }

    expect(app(AnalyticsService::class)->papersByVenue($user->id, 5))->toHaveCount(5);
});

it('renders the analytics dashboard with all five breakdowns', function () {
    $user = User::factory()->create();

    Paper::create([
        'title' => 'A paper',
        'user_id' => $user->id,
        'venue' => 'Nature Machine Intelligence',
        'year' => 2021,
        'reading_status' => ReadingStatus::Read,
    ]);

    actingAs($user)
        ->get(route('analytics'))
        ->assertOk()
        ->assertSee('Papers added over time')
        ->assertSee('By publication year')
        ->assertSee('By venue')
        ->assertSee('By tag')
        ->assertSee('By reading status')
        ->assertSee('Nature Machine Intelligence');
});

it('renders the analytics dashboard for an empty library without erroring', function () {
    // Every chart divides by a max; an empty library must not divide by zero.
    actingAs(User::factory()->create())
        ->get(route('analytics'))
        ->assertOk()
        ->assertSee('By venue');
});

it('requires sign-in', function () {
    $this->get(route('analytics'))->assertRedirect(route('login'));
});

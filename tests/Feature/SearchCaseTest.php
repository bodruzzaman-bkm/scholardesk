<?php

use App\Models\Paper;
use App\Models\User;
use App\Support\Search;
use Illuminate\Support\Facades\DB;

/*
| Support\Search exists because the drivers disagree about LIKE, silently:
|
|   SQLite    case-insensitive for ASCII by default
|   Postgres  case-SENSITIVE; ILIKE is the insensitive form
|
| A library that searched fine on SQLite would quietly stop matching once
| deployed to Postgres — no error, just fewer results. These pin the intent so
| a future change to the helper cannot reintroduce that.
*/

it('picks the case-insensitive operator for the driver', function () {
    $driver = DB::connection()->getDriverName();

    expect(Search::operator())->toBe($driver === 'pgsql' ? 'ILIKE' : 'LIKE');
});

it('builds a clause that names the column and keeps the escape character', function () {
    $clause = Search::clause('title');

    expect($clause)->toContain('title')
        ->and($clause)->toContain("ESCAPE '\\'")
        // The value is bound, never interpolated.
        ->and($clause)->toContain('?');
});

it('neutralises wildcards so a literal percent is not a match-all', function () {
    // Without escaping, searching "50%" returns the entire library.
    expect(Search::pattern('50%'))->toBe('%50\%%')
        ->and(Search::pattern('a_b'))->toBe('%a\_b%');
});

it('matches regardless of the case the user typed', function () {
    $user = User::factory()->create();
    Paper::create(['title' => 'Attention Is All You Need', 'user_id' => $user->id]);

    foreach (['attention', 'ATTENTION', 'AtTeNtIoN'] as $term) {
        expect(Paper::query()->ownedBy($user->id)->search($term)->count())
            ->toBe(1, "searching \"{$term}\" should find the paper");
    }
});

it('still matches a literal percent rather than everything', function () {
    $user = User::factory()->create();
    Paper::create(['title' => 'Growth of 50% in yield', 'user_id' => $user->id]);
    Paper::create(['title' => 'Unrelated work', 'user_id' => $user->id]);

    expect(Paper::query()->ownedBy($user->id)->search('50%')->count())->toBe(1);

    // A bare "%" is the wildcard. Escaped, it matches only the row that
    // literally contains a percent sign — not the whole library, which is
    // what an unescaped pattern would return.
    expect(Paper::query()->ownedBy($user->id)->search('%')->count())->toBe(1);
});

it('searches every column the scope advertises', function () {
    $user = User::factory()->create();
    Paper::create([
        'title' => 'A Paper', 'user_id' => $user->id,
        'authors' => 'Ada Lovelace', 'abstract' => 'concerning analytical engines',
        'venue' => 'NeurIPS', 'doi' => '10.1234/abcd',
    ]);

    foreach (['a paper', 'lovelace', 'analytical', 'neurips', '10.1234'] as $term) {
        expect(Paper::query()->ownedBy($user->id)->search($term)->count())
            ->toBe(1, "\"{$term}\" should match");
    }
});

it('filters by author case-insensitively too', function () {
    $user = User::factory()->create();
    Paper::create(['title' => 'P', 'user_id' => $user->id, 'authors' => 'Ashish Vaswani, Noam Shazeer']);

    // Mid-list match, and the case the user is unlikely to type correctly.
    expect(Paper::query()->ownedBy($user->id)->withAuthor('shazeer')->count())->toBe(1);
});

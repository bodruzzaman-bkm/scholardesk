<?php

use App\Models\Collection;
use App\Models\Note;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/*
| Requirement 16: "export a whole collection, its papers, notes, and a
| formatted bibliography, as a single downloadable file."
|
| The bundle used to be a lone .md — the notes and bibliography were there but
| the papers were not. These assert the archive actually carries the PDFs.
*/

/** Opens the downloaded zip and returns its entry names. */
function archiveEntries(string $body): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($tmp, $body);

    $zip = new ZipArchive();
    expect($zip->open($tmp))->toBeTrue();

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    $zip->close();
    unlink($tmp);

    return $names;
}

beforeEach(function () {
    Storage::fake('public');
});

it('downloads the collection as a zip', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Deep Learning', 'user_id' => $user->id]);

    $response = actingAs($user)->get(route('collections.bundle', $collection));

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertDownload('deep-learning.zip');
});

it('includes the markdown bundle and a bibtex bibliography', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Deep Learning', 'user_id' => $user->id]);

    $paper = Paper::create([
        'title' => 'Attention Is All You Need',
        'user_id' => $user->id,
        'authors' => 'Ashish Vaswani',
        'year' => 2017,
        'venue' => 'NeurIPS',
    ]);
    $collection->papers()->attach($paper);

    $body = actingAs($user)->get(route('collections.bundle', $collection))->streamedContent();
    $entries = archiveEntries($body);

    expect($entries)->toContain('deep-learning.md')
        ->and($entries)->toContain('bibliography.bib');
});

it('includes each paper PDF in the archive', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Reading List', 'user_id' => $user->id]);

    $path = UploadedFile::fake()->create('paper.pdf', 10, 'application/pdf')
        ->store('papers', 'public');

    $paper = Paper::create([
        'title' => 'A Readable Paper',
        'user_id' => $user->id,
        'file_path' => $path,
    ]);
    $collection->papers()->attach($paper);

    $body = actingAs($user)->get(route('collections.bundle', $collection))->streamedContent();

    expect(archiveEntries($body))->toContain('papers/a-readable-paper.pdf');
});

it('skips a metadata-only paper instead of failing the export', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Mixed', 'user_id' => $user->id]);

    // Imported by DOI, no open-access PDF. Normal, not an error.
    $noPdf = Paper::create(['title' => 'Citation Only', 'user_id' => $user->id, 'file_path' => null]);

    $path = UploadedFile::fake()->create('real.pdf', 5, 'application/pdf')->store('papers', 'public');
    $withPdf = Paper::create(['title' => 'Has A File', 'user_id' => $user->id, 'file_path' => $path]);

    $collection->papers()->attach([$noPdf->id, $withPdf->id]);

    $response = actingAs($user)->get(route('collections.bundle', $collection));
    $response->assertOk();

    $entries = archiveEntries($response->streamedContent());

    expect($entries)->toContain('papers/has-a-file.pdf')
        ->and($entries)->not->toContain('papers/citation-only.pdf');
});

it('does not lose a paper when two share a title', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Dupes', 'user_id' => $user->id]);

    foreach (range(1, 2) as $i) {
        $path = UploadedFile::fake()->create("f{$i}.pdf", 5, 'application/pdf')->store('papers', 'public');
        $p = Paper::create(['title' => 'Same Title', 'user_id' => $user->id, 'file_path' => $path]);
        $collection->papers()->attach($p);
    }

    $body = actingAs($user)->get(route('collections.bundle', $collection))->streamedContent();
    $entries = archiveEntries($body);

    // A zip with duplicate entry names keeps only one of them.
    expect($entries)->toContain('papers/same-title.pdf')
        ->and($entries)->toContain('papers/same-title-2.pdf');
});

it('exports only the requesting user\'s notes', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $collection = Collection::create(['name' => 'Shared', 'user_id' => $owner->id]);
    $paper = Paper::create(['title' => 'A Paper', 'user_id' => $owner->id]);
    $collection->papers()->attach($paper);

    Note::create(['paper_id' => $paper->id, 'user_id' => $owner->id, 'content' => 'MY PRIVATE NOTE']);
    Note::create(['paper_id' => $paper->id, 'user_id' => $other->id, 'content' => 'THEIR PRIVATE NOTE']);

    $body = actingAs($owner)->get(route('collections.bundle', $collection))->streamedContent();

    $tmp = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($tmp, $body);
    $zip = new ZipArchive();
    $zip->open($tmp);
    $markdown = $zip->getFromName('shared.md');
    $zip->close();
    unlink($tmp);

    expect($markdown)->toContain('MY PRIVATE NOTE')
        ->and($markdown)->not->toContain('THEIR PRIVATE NOTE');
});

it('refuses a collection the user cannot see', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $collection = Collection::create(['name' => 'Private', 'user_id' => $owner->id]);

    actingAs($stranger)->get(route('collections.bundle', $collection))->assertForbidden();
});

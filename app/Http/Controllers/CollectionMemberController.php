<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class CollectionMemberController extends Controller
{
    public function __construct(private CollectionService $collections) {}

    public function store(Request $request, Collection $collection): RedirectResponse
    {
        $this->authorize('manageMembers', $collection);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', new Enum(MemberRole::class)],
        ]);

        $role = MemberRole::from($validated['role']);

        // Ownership is structural, not something to hand out through the
        // invite form.
        if ($role === MemberRole::Owner) {
            $role = MemberRole::Editor;
        }

        $this->collections->addMember($collection, $request->user(), $validated['email'], $role);

        return back()->with('success', 'Collaborator added.');
    }

    public function update(Request $request, Collection $collection, CollectionMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $collection);
        $this->assertBelongs($collection, $member);

        $validated = $request->validate([
            'role' => ['required', new Enum(MemberRole::class)],
        ]);

        $this->collections->changeMemberRole($collection, $member, MemberRole::from($validated['role']));

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Collection $collection, CollectionMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $collection);
        $this->assertBelongs($collection, $member);

        $this->collections->removeMember($collection, $request->user(), $member);

        return back()->with('success', 'Collaborator removed.');
    }

    /**
     * Route-model binding resolves the member independently of the collection,
     * so the pairing has to be checked or a member id from another collection
     * would be accepted.
     */
    private function assertBelongs(Collection $collection, CollectionMember $member): void
    {
        abort_unless($member->collection_id === $collection->id, 404);
    }
}

<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\MemberRole;
use App\Enums\NotificationType;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collection membership and paper filing.
 *
 * Every method that grants access to papers re-checks the *paper* as well as
 * the collection: collection membership grants access to the papers inside it,
 * so accepting an arbitrary paper id here would hand out someone else's PDF.
 * That subtlety is called out explicitly in ScholarDesk's collection service
 * and is preserved.
 */
class CollectionService
{
    public function __construct(
        private ActivityService $activities,
        private NotificationService $notifications,
    ) {}

    public function create(User $owner, array $data): Collection
    {
        return DB::transaction(function () use ($owner, $data) {
            $collection = Collection::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'user_id' => $owner->id,
            ]);

            // The creator gets an explicit OWNER row so every access check can
            // read membership from one place.
            CollectionMember::create([
                'collection_id' => $collection->id,
                'user_id' => $owner->id,
                'role' => MemberRole::Owner,
            ]);

            return $collection;
        });
    }

    /**
     * File papers already in the library into a collection.
     *
     * Papers the actor cannot access are skipped rather than failing the whole
     * batch — but if *nothing* was permitted the request was invalid, and that
     * is reported instead of a silent success.
     *
     * @param  list<int>  $paperIds
     * @return array{added: int, skipped: int}
     */
    public function addPapers(Collection $collection, User $actor, array $paperIds): array
    {
        $unique = array_values(array_unique(array_filter($paperIds)));

        if ($unique === []) {
            return ['added' => 0, 'skipped' => 0];
        }

        $allowed = Paper::query()
            ->accessibleBy($actor->id)
            ->whereIn('id', $unique)
            ->get();

        if ($allowed->isEmpty()) {
            throw ValidationException::withMessages([
                'paper_id' => 'You do not have access to those papers.',
            ]);
        }

        $before = $collection->papers()->count();
        $collection->papers()->syncWithoutDetaching($allowed->pluck('id')->all());
        $added = $collection->papers()->count() - $before;

        foreach ($allowed as $paper) {
            $this->activities->record($collection, $actor, ActivityType::PaperAdded, [
                'paper_id' => $paper->id,
                'title' => $paper->title,
            ]);
        }

        $this->notifyMembers(
            $collection,
            $actor,
            NotificationType::Share,
            sprintf('%s added %d paper(s) to “%s”', $actor->name, $allowed->count(), $collection->name),
        );

        return ['added' => $added, 'skipped' => count($unique) - $allowed->count()];
    }

    public function removePaper(Collection $collection, User $actor, Paper $paper): void
    {
        $collection->papers()->detach($paper->id);

        $this->activities->record($collection, $actor, ActivityType::PaperRemoved, [
            'paper_id' => $paper->id,
            'title' => $paper->title,
        ]);
    }

    /**
     * Invite a user by email.
     *
     * @throws ValidationException when no such user exists, or when the target
     *                             is the owner (whose role is not reassignable).
     */
    public function addMember(Collection $collection, User $actor, string $email, MemberRole $role): CollectionMember
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'No account with that email address.',
            ]);
        }

        if ($user->id === $collection->user_id) {
            throw ValidationException::withMessages([
                'email' => 'That person owns this collection already.',
            ]);
        }

        $member = CollectionMember::updateOrCreate(
            ['collection_id' => $collection->id, 'user_id' => $user->id],
            ['role' => $role],
        );

        $this->activities->record($collection, $actor, ActivityType::MemberAdded, [
            'user_id' => $user->id,
            'name' => $user->name,
        ]);

        $this->notifications->notify(
            $user,
            NotificationType::Share,
            sprintf('%s shared the collection “%s” with you', $actor->name, $collection->name),
            route('collections.show', $collection, absolute: false),
        );

        return $member;
    }

    public function changeMemberRole(Collection $collection, CollectionMember $member, MemberRole $role): void
    {
        // The owner's own membership is structural and not editable.
        if ($member->user_id === $collection->user_id) {
            throw ValidationException::withMessages([
                'role' => 'The collection owner’s role cannot be changed.',
            ]);
        }

        $member->update(['role' => $role]);
    }

    public function removeMember(Collection $collection, User $actor, CollectionMember $member): void
    {
        if ($member->user_id === $collection->user_id) {
            throw ValidationException::withMessages([
                'member' => 'The collection owner cannot be removed.',
            ]);
        }

        $name = $member->user?->name;
        $member->delete();

        $this->activities->record($collection, $actor, ActivityType::MemberRemoved, ['name' => $name]);
    }

    /** Fan a notification out to everyone with access except the actor. */
    public function notifyMembers(Collection $collection, User $actor, NotificationType $type, string $message): void
    {
        $recipients = $collection->memberUsers()->get();

        $this->notifications->notifyMany(
            $recipients,
            $type,
            $message,
            route('collections.show', $collection, absolute: false),
            $actor,
        );
    }
}

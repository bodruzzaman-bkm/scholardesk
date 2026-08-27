<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\NotificationType;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_notification_is_stored_and_counted_as_unread(): void
    {
        $user = User::factory()->create();

        app(NotificationService::class)->notify($user, NotificationType::System, 'Something happened', '/dashboard');

        $this->assertSame(1, $user->unreadNotificationCount());
        $this->assertDatabaseHas('notifications_inapp', [
            'user_id' => $user->id,
            'message' => 'Something happened',
            'is_read' => false,
        ]);
    }

    public function test_the_unread_count_endpoint_returns_the_users_own_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        app(NotificationService::class)->notify($user, NotificationType::System, 'Mine');
        app(NotificationService::class)->notify($other, NotificationType::System, 'Theirs');
        app(NotificationService::class)->notify($other, NotificationType::System, 'Theirs again');

        $this->actingAs($user)->getJson(route('notifications.count'))
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_reading_a_notification_marks_it_and_follows_its_link(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->notify($user, NotificationType::Share, 'Shared', '/collections');

        $notification = InAppNotification::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect('/collections');

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_a_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        app(NotificationService::class)->notify($owner, NotificationType::System, 'Private');
        $notification = InAppNotification::where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        foreach (range(1, 3) as $i) {
            $service->notify($user, NotificationType::System, "Message {$i}");
        }

        $this->assertSame(3, $user->unreadNotificationCount());

        $this->actingAs($user)->post(route('notifications.readAll'))->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotificationCount());
    }

    public function test_the_notifications_page_lists_them(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->notify($user, NotificationType::Comment, 'Someone commented');

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Someone commented');
    }

    public function test_the_actor_is_not_notified_of_their_own_action(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $collection = Collection::create(['name' => 'Team', 'user_id' => $owner->id]);
        CollectionMember::create(['collection_id' => $collection->id, 'user_id' => $owner->id, 'role' => MemberRole::Owner]);
        CollectionMember::create(['collection_id' => $collection->id, 'user_id' => $member->id, 'role' => MemberRole::Editor]);

        $this->actingAs($owner)->post(route('comments.store', $collection), ['content' => 'Hello team']);

        // The commenter should not be told about their own comment.
        $this->assertSame(0, InAppNotification::where('user_id', $owner->id)->count());
        $this->assertSame(1, InAppNotification::where('user_id', $member->id)->count());
    }

    /**
     * A failing mailer must never break the request that triggered the
     * notification — the in-app row is the source of truth.
     */
    public function test_an_email_failure_does_not_break_the_notification(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('SMTP down'));

        $user = User::factory()->create();

        app(NotificationService::class)->notify($user, NotificationType::System, 'Still recorded');

        $this->assertDatabaseHas('notifications_inapp', ['message' => 'Still recorded']);
    }
}

<?php

namespace App\Enums;

enum ActivityType: string
{
    case PaperAdded = 'paper_added';
    case PaperRemoved = 'paper_removed';
    case NoteAdded = 'note_added';
    case CommentAdded = 'comment_added';
    case MemberAdded = 'member_added';
    case MemberRemoved = 'member_removed';
    case StatusChanged = 'status_changed';
    case ReviewGenerated = 'review_generated';

    /**
     * Human sentence for the feed. The actor's name is rendered separately,
     * so these read as the predicate: "Ada added a paper".
     *
     * @param  array<string, mixed>  $metadata
     */
    public function describe(array $metadata = []): string
    {
        $title = $metadata['title'] ?? null;
        $name = $metadata['name'] ?? null;

        return match ($this) {
            self::PaperAdded => $title ? "added “{$title}”" : 'added a paper',
            self::PaperRemoved => $title ? "removed “{$title}”" : 'removed a paper',
            self::NoteAdded => $title ? "wrote a note on “{$title}”" : 'wrote a note',
            self::CommentAdded => 'posted a comment',
            self::MemberAdded => $name ? "added {$name} to the collection" : 'added a member',
            self::MemberRemoved => $name ? "removed {$name}" : 'removed a member',
            self::StatusChanged => $title ? "changed the reading status of “{$title}”" : 'changed a reading status',
            self::ReviewGenerated => 'generated a literature review draft',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PaperAdded, self::PaperRemoved => '📄',
            self::NoteAdded => '📝',
            self::CommentAdded => '💬',
            self::MemberAdded, self::MemberRemoved => '👤',
            self::StatusChanged => '🔖',
            self::ReviewGenerated => '✨',
        };
    }
}

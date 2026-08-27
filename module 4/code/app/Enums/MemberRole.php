<?php

namespace App\Enums;

/**
 * Per-collection role. Ranked, so guards can require a *minimum* role.
 */
enum MemberRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Viewer => 'Viewer',
            self::Editor => 'Editor',
            self::Owner => 'Owner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Viewer => 'Can read papers, notes and comments.',
            self::Editor => 'Can also add or remove papers and post comments.',
            self::Owner => 'Full control, including members and deletion.',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 1,
            self::Editor => 2,
            self::Owner => 3,
        };
    }

    /** True when this role is at least as privileged as $minimum. */
    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /** @return array<string, string> */
    public static function assignableOptions(): array
    {
        // Ownership is not handed out through the member picker; it belongs to
        // the creator, so only viewer/editor are offered when inviting.
        return [
            self::Viewer->value => self::Viewer->label(),
            self::Editor->value => self::Editor->label(),
        ];
    }
}

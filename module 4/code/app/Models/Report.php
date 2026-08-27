<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A user's report about a comment or a paper (requirement 22).
 */
class Report extends Model
{
    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** The reported comment or paper. Null once the target is deleted. */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Who filed it. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The administrator who closed it. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Open->value);
    }

    public function scopeWithStatus(Builder $query, mixed $status): Builder
    {
        if (blank($status) || ! in_array($status, ReportStatus::values(), true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function isOpen(): bool
    {
        return $this->status === ReportStatus::Open;
    }

    /**
     * A short human label for what was reported.
     *
     * The target can be gone — a comment deleted by its author, a paper
     * removed — and the report should still render in the queue rather than
     * throwing on a null relation.
     */
    public function targetLabel(): string
    {
        return match ($this->reportable_type) {
            Comment::class => 'Comment',
            Paper::class => 'Paper',
            default => 'Content',
        };
    }
}

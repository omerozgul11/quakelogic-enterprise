<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Arr;

/**
 * Records create / edit / delete activity for a model into `audit_logs`, keyed
 * to the acting user (admin-only Activity feed). Only user-initiated actions are
 * logged — console commands, jobs and seeders run without an authenticated user
 * and are intentionally skipped, so the feed stays a true per-user trail.
 *
 * Note: `saveQuietly()` and query-builder `->update()` do not fire model events,
 * so internal/bulk churn is not audited.
 */
trait Auditable
{
    /** Attributes never worth recording in an edit diff. Extend per-model via $auditExclude. */
    private const AUDIT_IGNORE = ['created_at', 'updated_at', 'updated_by', 'ulid'];

    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAuditLog('created'));
        static::updated(fn ($model) => $model->writeAuditLog('updated'));
        static::deleted(fn ($model) => $model->writeAuditLog('deleted'));
    }

    public function writeAuditLog(string $event): void
    {
        if (AuditLog::isMuted() || !auth()->check()) {
            return;
        }

        if ($event === 'updated') {
            $ignore = array_merge(self::AUDIT_IGNORE, $this->auditExclude ?? []);
            $changed = Arr::except($this->getChanges(), $ignore);
            if (empty($changed)) {
                return; // nothing meaningful changed (e.g. only timestamps)
            }
            AuditLog::record('updated', $this, Arr::only($this->getOriginal(), array_keys($changed)), $changed);
            return;
        }

        AuditLog::record($event, $this);
    }
}

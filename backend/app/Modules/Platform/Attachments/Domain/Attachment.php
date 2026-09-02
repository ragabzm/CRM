<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One uploaded file.
 *
 * Deliberately has NO `belongsTo` relations to its owners. Platform is T0 and
 * cannot see Customers or Tickets; a relation here would invert the whole
 * dependency graph to save one query.
 *
 * @property string $owner_type
 * @property string $owner_id
 * @property string $filename
 * @property string $stored_path
 * @property string $mime_type
 * @property string $scan_status
 */
final class Attachment extends Model
{
    use HasUlids;

    protected $table = 'attachments';

    protected $fillable = [
        'owner_type', 'owner_id', 'filename', 'stored_path',
        'byte_size', 'mime_type', 'uploader_id', 'uploaded_at',
        'scan_status', 'scan_result', 'scanned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'scan_result' => 'array',
            'uploaded_at' => 'immutable_datetime',
            'scanned_at' => 'immutable_datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    public function scopeClean(Builder $query): Builder
    {
        return $query->where('scan_status', ScanStatus::Clean->value);
    }

    public function scopeFor(Builder $query, AttachmentOwnerType $type, string $ownerId): Builder
    {
        return $query->where('owner_type', $type->value)->where('owner_id', $ownerId);
    }

    public function status(): ScanStatus
    {
        return ScanStatus::from($this->scan_status);
    }

    /**
     * Whether the file may be handed to anyone.
     *
     * Derived, never stored. A `downloadable` column would be a second source
     * of truth that a half-finished scan job could leave disagreeing with
     * `scan_status` — and the direction it disagreed in would be the dangerous
     * one.
     */
    public function isDownloadable(): bool
    {
        return $this->status() === ScanStatus::Clean;
    }
}

<?php

namespace App\Models;

use App\Data\ProcessAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CourtProcess extends Model
{
    public const STATUS_ACTIVE = 'ativo';

    public const STATUS_SUSPENDED = 'suspenso';

    public const STATUS_ARCHIVED_PROVISIONALLY = 'arquivado_provisoriamente';

    public const STATUS_ARCHIVED_DEFINITIVELY = 'arquivado_definitivamente';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_ARCHIVED_PROVISIONALLY,
        self::STATUS_ARCHIVED_DEFINITIVELY,
    ];

    protected $table = 'court_processes';

    protected $guarded = ['id'];

    protected $attributes = ['operational_status' => self::STATUS_ACTIVE];

    protected function casts(): array
    {
        return [
            'competency_code' => 'integer',
            'secrecy_code' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $process): void {
            if (! in_array($process->operational_status, self::STATUSES, true)) {
                throw ValidationException::withMessages(['operational_status' => 'Situação do Processo inválida.']);
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function personLinks(): HasMany
    {
        return $this->hasMany(PersonProcess::class, 'court_process_id');
    }

    public function accessScope(): ProcessAccessScope
    {
        return new ProcessAccessScope(
            $this->unit_id,
            $this->competency_code === null ? null : (int) $this->competency_code,
            $this->secrecy_code === null ? null : (string) $this->secrecy_code,
        );
    }
}

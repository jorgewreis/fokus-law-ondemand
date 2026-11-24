<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PersonProcess extends Model
{
    public const ROLE_REU = 'reu';

    public const ROLE_VITIMA = 'vitima';

    public const ROLE_TESTEMUNHA = 'testemunha';

    public const ROLE_DEFENSOR = 'defensor';

    public const WITNESS_ORIGIN_DENUNCIA = 'denuncia';

    public const WITNESS_ORIGIN_DEFESA = 'defesa';

    public const WITNESS_ORIGIN_AMBAS = 'ambas';

    protected $table = 'person_process';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $roles = [self::ROLE_REU, self::ROLE_VITIMA, self::ROLE_TESTEMUNHA, self::ROLE_DEFENSOR];
            $origins = [self::WITNESS_ORIGIN_DENUNCIA, self::WITNESS_ORIGIN_DEFESA, self::WITNESS_ORIGIN_AMBAS];

            if (! in_array($link->role, $roles, true)) {
                throw ValidationException::withMessages(['role' => 'Tipo de participação inválido.']);
            }

            if ($link->role === self::ROLE_TESTEMUNHA && ! in_array($link->witness_origin, $origins, true)) {
                throw ValidationException::withMessages(['witness_origin' => 'Informe a origem da participação da testemunha.']);
            }

            if ($link->role !== self::ROLE_TESTEMUNHA && $link->witness_origin !== null) {
                throw ValidationException::withMessages(['witness_origin' => 'A origem só se aplica à participação de testemunha.']);
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function courtProcess(): BelongsTo
    {
        return $this->belongsTo(CourtProcess::class, 'court_process_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

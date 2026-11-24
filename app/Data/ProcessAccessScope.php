<?php

namespace App\Data;

/** Values must come from a trusted persisted Process, never from request input. */
final readonly class ProcessAccessScope
{
    public function __construct(
        public int|string|null $unitId,
        public ?int $competencyCode,
        public ?string $secrecyCode,
    ) {}
}

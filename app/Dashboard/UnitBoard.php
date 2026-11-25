<?php

namespace App\Dashboard;

use DateTimeInterface;

interface UnitBoard
{
    /**
     * Snapshot shown on the public page.
     *
     * Phase 1 returns labeled example data. A later phase can bind another
     * implementation that reads the unit's own tables. The public page must
     * keep receiving only aggregates and anonymized labels.
     *
     * @return array<string, mixed>
     */
    public function present(DateTimeInterface $moment): array;
}

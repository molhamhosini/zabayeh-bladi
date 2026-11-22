<?php

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * The same resource serves both panels. In the branch portal every query is limited
 * to the signed-in staff member's branch, and new records are stamped with it.
 */
trait ScopedToBranch
{
    public static function inBranchPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'branch';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::inBranchPanel() ? $query->where('branch_id', auth()->user()?->branch_id ?? 0) : $query;
    }

    public static function stampBranch(array $data): array
    {
        if (static::inBranchPanel()) {
            $data['branch_id'] = auth()->user()->branch_id;
        }

        return $data;
    }
}

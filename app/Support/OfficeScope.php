<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class OfficeScope
{
    public static function equipment(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user || $user->role === 'super_admin') {
            return $query;
        }

        return filled($user->office_code)
            ? $query->where('office_code', $user->office_code)
            : $query->whereRaw('1 = 0');
    }

    public static function records(Builder $query, string $equipmentRelation = 'equipment', ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user || $user->role === 'super_admin') {
            return $query;
        }

        if (! filled($user->office_code)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($equipmentRelation, fn (Builder $equipment) => $equipment
            ->where('office_code', $user->office_code));
    }

    public static function personnel(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $query->where('role', '<>', 'super_admin');

        if (! $user || $user->role === 'super_admin') {
            return $query;
        }

        if (! filled($user->office_code)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('office_code', $user->office_code);
    }

    public static function users(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        return $user?->role === 'super_admin'
            ? $query
            : self::personnel($query, $user);
    }

    public static function equipmentExistsRule(?User $user = null): Exists
    {
        $user ??= auth()->user();
        $rule = Rule::exists('equipment', 'id');

        if ($user && $user->role !== 'super_admin') {
            $rule->where(filled($user->office_code)
                ? fn ($query) => $query->where('office_code', $user->office_code)
                : fn ($query) => $query->whereRaw('1 = 0'));
        }

        return $rule;
    }
}

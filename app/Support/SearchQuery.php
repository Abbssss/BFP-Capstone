<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class SearchQuery
{
    public static function apply(Builder $query, ?string $term, array $columns, array $relations = []): Builder
    {
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($term ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $query->where(function (Builder $match) use ($columns, $relations, $token): void {
                foreach ($columns as $column) {
                    $match->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", ['%'.$token.'%']);
                }

                foreach ($relations as $relation => $fields) {
                    $match->orWhereHas($relation, function (Builder $related) use ($fields, $token): void {
                        $related->where(function (Builder $relatedMatch) use ($fields, $token): void {
                            foreach ($fields as $field) {
                                $relatedMatch->orWhereRaw("LOWER(COALESCE({$field}, '')) LIKE ?", ['%'.$token.'%']);
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    public static function matches(string $term, array $values): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter($values, 'is_scalar')));
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }
}

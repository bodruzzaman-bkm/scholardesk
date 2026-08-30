<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive LIKE that behaves the same on every driver.
 *
 * The drivers disagree, and the disagreement is silent:
 *
 *   SQLite    LIKE is case-insensitive for ASCII by default
 *   MySQL     LIKE follows the column collation, normally case-insensitive
 *   Postgres  LIKE is case-SENSITIVE; ILIKE is the insensitive form
 *
 * So a library that searched fine on SQLite would quietly stop matching
 * "Attention" for a query of "attention" once deployed to Postgres — the
 * feature would look broken without erroring, which is the worst failure
 * mode for a search box.
 */
class Search
{
    /**
     * Wrap a user's term as a LIKE pattern with its wildcards neutralised.
     *
     * Escaping matters: without it, a search for "50%" matches every row,
     * because % is the wildcard.
     */
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
    }

    /**
     * The case-insensitive LIKE operator for the current connection.
     */
    public static function operator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * `column <LIKE> ? ESCAPE '\'` for the current driver.
     *
     * The ESCAPE clause is stated explicitly because SQLite has no default
     * escape character, so the pattern built by pattern() would otherwise be
     * interpreted with its backslashes intact.
     *
     * The column name is never user input at any call site — it is always a
     * hard-coded literal — and only the value is bound.
     */
    public static function clause(string $column): string
    {
        return sprintf("%s %s ? ESCAPE '\\'", $column, self::operator());
    }

    /**
     * OR a case-insensitive match across several columns.
     *
     * @param  list<string>  $columns  hard-coded column names, never user input
     */
    public static function anyColumn(Builder $query, array $columns, string $term): Builder
    {
        $pattern = self::pattern($term);

        return $query->where(function (Builder $q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhereRaw(self::clause($column), [$pattern]);
            }
        });
    }
}

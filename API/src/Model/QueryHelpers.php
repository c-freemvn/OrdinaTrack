<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Connections\Database;
use Throwable;

/**
 * Query helpers shared by the admin models.
 *
 * Unlike Database::fetch()/execute(), these let PDOExceptions propagate so callers
 * can roll back and report failures instead of silently continuing.
 */
trait QueryHelpers
{
    private static function rows(string $sql, array $params = []): array
    {
        return Database::query($sql, $params)->fetchAll();
    }

    private static function row(string $sql, array $params = []): ?array
    {
        return Database::query($sql, $params)->fetch() ?: null;
    }

    private static function run(string $sql, array $params = []): int
    {
        return Database::query($sql, $params)->rowCount();
    }

    /**
     * Run $work inside a transaction, rolling back on any failure
     */
    private static function transaction(callable $work): void
    {
        Database::beginTransaction();
        try {
            $work();
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * UPDATE $table SET <fields> WHERE id = ? ($table and keys are internal, never user input)
     */
    private static function updateFields(string $table, int $id, array $fields): void
    {
        $set = implode(', ', array_map(fn($column) => "$column = ?", array_keys($fields)));
        self::run("UPDATE $table SET $set WHERE id = ?", [...array_values($fields), $id]);
    }

    private static function nullIfEmpty($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === null || $value === '') ? null : (string)$value;
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}

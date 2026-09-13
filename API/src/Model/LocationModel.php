<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Connections\Database;
use RuntimeException;

/**
 * LocationModel
 *
 * Manages the church hierarchy: provinces > districts > branches.
 * Every level has a name, optional code/description/administrator (a user) and an
 * active flag; branches also carry an address and contact details.
 *
 * Deletes are hard deletes and are refused while a location still has children, so
 * removing a province can never silently cascade away its districts and branches.
 * Deactivate (is_active = 0) to retire a location instead.
 *
 * Business-rule failures throw RuntimeException with an HTTP status as the code
 * (404 not found, 409 conflict, 422 invalid), like SuperAdminModel.
 */
class LocationModel
{
    use QueryHelpers;

    public const TYPES = [
        'provinces' => [
            'label' => 'Province',
            'parent' => null,
            'children' => ['table' => 'districts', 'column' => 'province_id', 'label' => 'districts', 'singular' => 'district'],
            'columns' => ['name', 'code', 'description', 'administrator_id', 'is_active'],
        ],
        'districts' => [
            'label' => 'District',
            'parent' => ['table' => 'provinces', 'column' => 'province_id', 'label' => 'Province'],
            'children' => ['table' => 'branches', 'column' => 'district_id', 'label' => 'branches', 'singular' => 'branch'],
            'columns' => ['name', 'code', 'province_id', 'description', 'administrator_id', 'is_active'],
        ],
        'branches' => [
            'label' => 'Branch',
            'parent' => ['table' => 'districts', 'column' => 'district_id', 'label' => 'District'],
            'children' => null,
            'columns' => [
                'name', 'code', 'district_id', 'description', 'address',
                'contact_person', 'contact_phone', 'contact_email', 'administrator_id', 'is_active',
            ],
        ],
    ];

    public static function label(string $type): string
    {
        return self::config($type)['label'];
    }

    /**
     * List locations with parent names, child counts and administrator name
     *
     * @param array $filters id?, province_id?, district_id?, search?
     */
    public static function getAll(string $type, array $filters = []): array
    {
        self::config($type);

        $where = ['x.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['id'])) {
            $where[] = 'x.id = ?';
            $params[] = (int)$filters['id'];
        }
        if (!empty($filters['province_id']) && $type !== 'provinces') {
            $where[] = ($type === 'districts' ? 'x' : 'd') . '.province_id = ?';
            $params[] = (int)$filters['province_id'];
        }
        if (!empty($filters['district_id']) && $type === 'branches') {
            $where[] = 'x.district_id = ?';
            $params[] = (int)$filters['district_id'];
        }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(x.name LIKE ? OR x.code LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        $adminColumns = "x.administrator_id, CONCAT(u.first_name, ' ', u.last_name) AS administrator_name";
        $adminJoin = 'LEFT JOIN users u ON u.id = x.administrator_id AND u.deleted_at IS NULL';

        $sql = match ($type) {
            'provinces' => "
                SELECT x.id, x.name, x.code, x.description, x.is_active, x.created_at, $adminColumns,
                    (SELECT COUNT(*) FROM districts d
                        WHERE d.province_id = x.id AND d.deleted_at IS NULL) AS district_count,
                    (SELECT COUNT(*) FROM branches b INNER JOIN districts d ON d.id = b.district_id
                        WHERE d.province_id = x.id AND b.deleted_at IS NULL AND d.deleted_at IS NULL) AS branch_count
                FROM provinces x
                $adminJoin
                WHERE $whereSql
                ORDER BY x.name",
            'districts' => "
                SELECT x.id, x.name, x.code, x.province_id, p.name AS province_name,
                    x.description, x.is_active, x.created_at, $adminColumns,
                    (SELECT COUNT(*) FROM branches b
                        WHERE b.district_id = x.id AND b.deleted_at IS NULL) AS branch_count
                FROM districts x
                INNER JOIN provinces p ON p.id = x.province_id
                $adminJoin
                WHERE $whereSql
                ORDER BY p.name, x.name",
            'branches' => "
                SELECT x.id, x.name, x.code, x.district_id, d.name AS district_name,
                    d.province_id, p.name AS province_name, x.description, x.address,
                    x.contact_person, x.contact_phone, x.contact_email, x.is_active, x.created_at, $adminColumns
                FROM branches x
                INNER JOIN districts d ON d.id = x.district_id
                INNER JOIN provinces p ON p.id = d.province_id
                $adminJoin
                WHERE $whereSql
                ORDER BY p.name, d.name, x.name",
        };

        return self::rows($sql, $params);
    }

    public static function getOne(string $type, int $id): ?array
    {
        return self::getAll($type, ['id' => $id])[0] ?? null;
    }

    /**
     * Create a location (districts need province_id, branches need district_id)
     */
    public static function create(string $type, array $input): array
    {
        $config = self::config($type);
        $values = self::prepare($type, $input);

        if (($values['name'] ?? '') === '') {
            throw new RuntimeException("{$config['label']} name is required", 422);
        }
        if ($config['parent'] && !array_key_exists($config['parent']['column'], $values)) {
            throw new RuntimeException("{$config['parent']['label']} is required", 422);
        }
        self::assertUnique($type, $values);

        $columns = array_keys($values);
        self::run(
            "INSERT INTO $type (" . implode(', ', $columns) . ") VALUES (" . self::placeholders($columns) . ")",
            array_values($values)
        );

        return self::getOne($type, (int)Database::lastInsertId());
    }

    /**
     * Update a location (partial update; changing the parent moves it)
     */
    public static function update(string $type, int $id, array $input): array
    {
        $config = self::config($type);
        $current = self::requireLocation($type, $id);
        $values = self::prepare($type, $input);

        if (array_key_exists('name', $values) && $values['name'] === '') {
            throw new RuntimeException("{$config['label']} name cannot be empty", 422);
        }

        $identity = ['name', 'code', $config['parent']['column'] ?? 'name'];
        if (array_intersect_key($values, array_flip($identity))) {
            self::assertUnique($type, $values, $current);
        }

        if ($values) {
            self::updateFields($type, $id, $values);
        }

        return self::getOne($type, $id);
    }

    /**
     * Delete a location that has no children left
     */
    public static function delete(string $type, int $id): void
    {
        $config = self::config($type);
        self::requireLocation($type, $id);

        if ($children = $config['children']) {
            $count = (int)self::row(
                "SELECT COUNT(*) AS count FROM {$children['table']} WHERE {$children['column']} = ? AND deleted_at IS NULL",
                [$id]
            )['count'];

            if ($count > 0) {
                $label = strtolower($config['label']);
                $noun = $count === 1 ? $children['singular'] : $children['label'];
                throw new RuntimeException(
                    "This $label still has $count $noun. Move or delete them first, or deactivate the $label instead.",
                    409
                );
            }
        }

        // Branches hold members through their organization record, so deleting one would
        // orphan them. Refused the same way as a province that still has districts.
        if ($type === 'branches') {
            $members = (int)self::row(
                "SELECT COUNT(*) AS count FROM members m
                 INNER JOIN branches b ON b.organization_id = m.organization_id
                 WHERE b.id = ? AND m.deleted_at IS NULL",
                [$id]
            )['count'];

            if ($members > 0) {
                $noun = $members === 1 ? 'member' : 'members';
                throw new RuntimeException(
                    "This branch still has $members $noun. Remove them first, or deactivate the branch instead.",
                    409
                );
            }
        }

        self::run("DELETE FROM $type WHERE id = ?", [$id]);
    }

    // ==================== HELPERS ====================

    private static function config(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Unknown location type', 404);
        }
        return self::TYPES[$type];
    }

    private static function requireLocation(string $type, int $id): array
    {
        $location = self::row("SELECT * FROM $type WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$location) {
            throw new RuntimeException(self::label($type) . ' not found', 404);
        }
        return $location;
    }

    /**
     * Keep only this type's columns, normalise values and check referenced rows exist
     */
    private static function prepare(string $type, array $input): array
    {
        $config = self::config($type);
        $values = array_intersect_key($input, array_flip($config['columns']));

        foreach ($values as $column => $value) {
            $values[$column] = match ($column) {
                'name' => trim((string)$value),
                'code' => ($code = self::nullIfEmpty($value)) === null ? null : strtoupper($code),
                'is_active' => $value ? 1 : 0,
                'province_id', 'district_id', 'administrator_id' => $value === null ? null : (int)$value,
                default => self::nullIfEmpty($value),
            };
        }

        if ($parent = $config['parent']) {
            $column = $parent['column'];
            if (array_key_exists($column, $values)) {
                if (!$values[$column]) {
                    throw new RuntimeException("{$parent['label']} is required", 422);
                }
                if (!self::row("SELECT id FROM {$parent['table']} WHERE id = ? AND deleted_at IS NULL", [$values[$column]])) {
                    throw new RuntimeException("{$parent['label']} not found", 422);
                }
            }
        }

        if (!empty($values['administrator_id'])
            && !self::row("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL", [$values['administrator_id']])) {
            throw new RuntimeException('Administrator user not found', 422);
        }

        return $values;
    }

    /**
     * Names are unique within their parent (provinces: nationally); province codes are unique.
     * Matches the DB unique keys but fails with a readable 409 instead of a PDO error.
     */
    private static function assertUnique(string $type, array $values, ?array $current = null): void
    {
        $config = self::config($type);
        $name = $values['name'] ?? $current['name'];
        $exclude = $current ? ' AND id <> ?' : '';
        $excludeParams = $current ? [(int)$current['id']] : [];
        $label = strtolower($config['label']);

        if ($parent = $config['parent']) {
            $column = $parent['column'];
            $clash = self::row(
                "SELECT id FROM $type WHERE name = ? AND $column = ?$exclude",
                [$name, $values[$column] ?? $current[$column], ...$excludeParams]
            );
            $scope = ' in this ' . strtolower($parent['label']);
        } else {
            $clash = self::row("SELECT id FROM $type WHERE name = ?$exclude", [$name, ...$excludeParams]);
            $scope = '';
        }
        if ($clash) {
            throw new RuntimeException("A $label named \"$name\" already exists$scope", 409);
        }

        if ($type === 'provinces' && !empty($values['code'])
            && self::row("SELECT id FROM provinces WHERE code = ?$exclude", [$values['code'], ...$excludeParams])) {
            throw new RuntimeException("Province code \"{$values['code']}\" is already in use", 409);
        }
    }
}

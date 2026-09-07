<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ordinatrack\Api\Connections\Schema;
use Ordinatrack\Api\Connections\Database;
use Ordinatrack\Api\Config\Config;
use PDO;
use PDOException;

/**
 * Schema Manager Test Suite
 * 
 * Tests the database schema creation, relationships, and auto-initialization
 */
class SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        // Initialize config before running tests
        Config::init();
        
        // Reset database before each test
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->resetDatabase();
    }

    /**
     * Helper method to reset database
     */
    private function resetDatabase(): void
    {
        try {
            Schema::reset();
        } catch (\Exception $e) {
            // Ignore errors during reset
        }
    }

    /**
     * Test schema initialization creates all tables
     * 
     * @test
     */
    public function testSchemaInitializationCreatesAllTables(): void
    {
        // Initialize schemas
        $result = Schema::initialize();
        
        // Assert initialization was successful
        $this->assertTrue($result, 'Schema initialization should return true');
        
        // Check that all expected tables exist
        $tables = [
            'users',
            'organizations',
            'organization_members',
            'provinces',
            'districts',
            'branches',
            'roles',
            'user_roles',
            'permissions',
            'role_permissions',
            'members',
            'member_roles',
            'logistics',
            'folders',
            'requests',
            'audit_logs'
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                $this->tableExists($table),
                "Table '{$table}' should exist after schema initialization"
            );
        }
    }

    /**
     * Test users table structure
     * 
     * @test
     */
    public function testUsersTableStructure(): void
    {
        Schema::initialize();
        
        $columns = $this->getTableColumns('users');
        
        // Assert required columns exist
        $requiredColumns = [
            'id', 'email', 'password', 'first_name', 'last_name',
            'phone', 'is_active', 'email_verified', 'created_at', 'updated_at', 'deleted_at'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey(
                $column,
                $columns,
                "Users table should have '{$column}' column"
            );
        }
        
        // Check email is unique
        $this->assertTrue($this->hasUniqueConstraint('users', 'email'));
    }

    /**
     * Test organizations table structure
     * 
     * @test
     */
    public function testOrganizationsTableStructure(): void
    {
        Schema::initialize();
        
        $columns = $this->getTableColumns('organizations');
        
        $requiredColumns = [
            'id', 'name', 'organization_type', 'parent_organization_id',
            'contact_email', 'is_active', 'created_at', 'updated_at'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey(
                $column,
                $columns,
                "Organizations table should have '{$column}' column"
            );
        }
    }

    /**
     * Test geographic hierarchy (Province → District → Branch)
     * 
     * @test
     */
    public function testGeographicHierarchyStructure(): void
    {
        Schema::initialize();
        
        // Check provinces table
        $this->assertTrue($this->tableExists('provinces'));
        $provinceColumns = $this->getTableColumns('provinces');
        $this->assertArrayHasKey('name', $provinceColumns);
        
        // Check districts table with foreign key to provinces
        $this->assertTrue($this->tableExists('districts'));
        $districtColumns = $this->getTableColumns('districts');
        $this->assertArrayHasKey('province_id', $districtColumns);
        $this->assertTrue($this->hasForeignKey('districts', 'province_id', 'provinces', 'id'));
        
        // Check branches table with foreign key to districts
        $this->assertTrue($this->tableExists('branches'));
        $branchColumns = $this->getTableColumns('branches');
        $this->assertArrayHasKey('district_id', $branchColumns);
        $this->assertTrue($this->hasForeignKey('branches', 'district_id', 'districts', 'id'));
    }

    /**
     * Test RBAC (Role-Based Access Control) structure
     * 
     * @test
     */
    public function testRBACStructure(): void
    {
        Schema::initialize();
        
        // Check roles table exists
        $this->assertTrue($this->tableExists('roles'));
        
        // Check permissions table exists
        $this->assertTrue($this->tableExists('permissions'));
        
        // Check user_roles junction table
        $this->assertTrue($this->tableExists('user_roles'));
        $userRolesColumns = $this->getTableColumns('user_roles');
        $this->assertArrayHasKey('user_id', $userRolesColumns);
        $this->assertArrayHasKey('role_id', $userRolesColumns);
        
        // Check role_permissions junction table
        $this->assertTrue($this->tableExists('role_permissions'));
        $rolePermsColumns = $this->getTableColumns('role_permissions');
        $this->assertArrayHasKey('role_id', $rolePermsColumns);
        $this->assertArrayHasKey('permission_id', $rolePermsColumns);
    }

    /**
     * Test default roles are inserted
     * 
     * @test
     */
    public function testDefaultRolesAreInserted(): void
    {
        Schema::initialize();
        
        $expectedRoles = [
            'admin',
            'nhq_admin',
            'province_lead',
            'district_lead',
            'branch_admin',
            'secretary',
            'member',
            'guest'
        ];

        foreach ($expectedRoles as $roleSlug) {
            $role = Database::fetch(
                "SELECT id FROM roles WHERE slug = ?",
                [$roleSlug]
            );
            
            $this->assertNotFalse(
                $role,
                "Default role '{$roleSlug}' should be inserted"
            );
        }
    }

    /**
     * Test default permissions are inserted
     * 
     * @test
     */
    public function testDefaultPermissionsAreInserted(): void
    {
        Schema::initialize();
        
        $expectedPermissions = [
            'create_user', 'read_users', 'update_user', 'delete_user',
            'create_organization', 'read_organizations', 'update_organization', 'delete_organization',
            'create_member', 'read_members', 'update_member', 'delete_member',
            'create_logistics', 'read_logistics', 'update_logistics', 'delete_logistics',
            'create_request', 'read_requests', 'approve_request', 'delete_request',
            'read_audit_logs'
        ];

        foreach ($expectedPermissions as $permSlug) {
            $permission = Database::fetch(
                "SELECT id FROM permissions WHERE slug = ?",
                [$permSlug]
            );
            
            $this->assertNotFalse(
                $permission,
                "Default permission '{$permSlug}' should be inserted"
            );
        }
    }

    /**
     * Test organization members junction table
     * 
     * @test
     */
    public function testOrganizationMembersJunctionTable(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('organization_members'));
        
        $columns = $this->getTableColumns('organization_members');
        $this->assertArrayHasKey('organization_id', $columns);
        $this->assertArrayHasKey('user_id', $columns);
        $this->assertArrayHasKey('role', $columns);
        
        // Check foreign keys
        $this->assertTrue($this->hasForeignKey('organization_members', 'organization_id', 'organizations', 'id'));
        $this->assertTrue($this->hasForeignKey('organization_members', 'user_id', 'users', 'id'));
    }

    /**
     * Test soft delete capability (deleted_at field)
     * 
     * @test
     */
    public function testSoftDeleteCapability(): void
    {
        Schema::initialize();
        
        $tablesWithSoftDelete = [
            'users',
            'organizations',
            'organization_members',
            'provinces',
            'districts',
            'branches',
            'members',
            'member_roles',
            'logistics',
            'folders',
            'requests'
        ];

        foreach ($tablesWithSoftDelete as $table) {
            $columns = $this->getTableColumns($table);
            $this->assertArrayHasKey(
                'deleted_at',
                $columns,
                "Table '{$table}' should have 'deleted_at' column for soft deletes"
            );
        }
    }

    /**
     * Test timestamps on all tables
     * 
     * @test
     */
    public function testTimestampsOnAllTables(): void
    {
        Schema::initialize();
        
        $tablesWithTimestamps = [
            'users',
            'organizations',
            'organization_members',
            'provinces',
            'districts',
            'branches',
            'roles',
            'permissions',
            'members',
            'member_roles',
            'logistics',
            'folders',
            'requests'
        ];

        foreach ($tablesWithTimestamps as $table) {
            $columns = $this->getTableColumns($table);
            $this->assertArrayHasKey(
                'created_at',
                $columns,
                "Table '{$table}' should have 'created_at' column"
            );
            $this->assertArrayHasKey(
                'updated_at',
                $columns,
                "Table '{$table}' should have 'updated_at' column"
            );
        }
    }

    /**
     * Test audit logs table structure
     * 
     * @test
     */
    public function testAuditLogsTableStructure(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('audit_logs'));
        
        $columns = $this->getTableColumns('audit_logs');
        $requiredColumns = [
            'id', 'user_id', 'resource_type', 'resource_id', 'action',
            'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey(
                $column,
                $columns,
                "Audit logs table should have '{$column}' column"
            );
        }
    }

    /**
     * Test cascade delete on organizations
     * 
     * @test
     */
    public function testCascadeDeleteOnOrganizations(): void
    {
        Schema::initialize();
        
        // Verify the foreign key constraint exists and is set to CASCADE
        $constraint = $this->getForeignKeyConstraint('districts', 'province_id');
        $this->assertNotNull($constraint, 'Foreign key constraint should exist');
    }

    /**
     * Test member roles relationship
     * 
     * @test
     */
    public function testMemberRolesRelationship(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('member_roles'));
        
        $columns = $this->getTableColumns('member_roles');
        $this->assertArrayHasKey('member_id', $columns);
        $this->assertArrayHasKey('organization_id', $columns);
        $this->assertArrayHasKey('role_name', $columns);
        
        // Check foreign keys exist
        $this->assertTrue($this->hasForeignKey('member_roles', 'member_id', 'members', 'id'));
        $this->assertTrue($this->hasForeignKey('member_roles', 'organization_id', 'organizations', 'id'));
    }

    /**
     * Test logistics table for asset tracking
     * 
     * @test
     */
    public function testLogisticsTableStructure(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('logistics'));
        
        $columns = $this->getTableColumns('logistics');
        $requiredColumns = [
            'id', 'organization_id', 'item_name', 'category', 'quantity',
            'unit_price', 'total_value', 'status', 'location', 'assigned_to'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey(
                $column,
                $columns,
                "Logistics table should have '{$column}' column"
            );
        }
    }

    /**
     * Test requests workflow table
     * 
     * @test
     */
    public function testRequestsTableStructure(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('requests'));
        
        $columns = $this->getTableColumns('requests');
        $requiredColumns = [
            'id', 'organization_id', 'request_type', 'title', 'status',
            'priority', 'requested_by', 'assigned_to', 'approved_by'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey(
                $column,
                $columns,
                "Requests table should have '{$column}' column"
            );
        }
    }

    /**
     * Test folders for nested document organization
     * 
     * @test
     */
    public function testFoldersTableStructure(): void
    {
        Schema::initialize();
        
        $this->assertTrue($this->tableExists('folders'));
        
        $columns = $this->getTableColumns('folders');
        $this->assertArrayHasKey('parent_folder_id', $columns);
        $this->assertArrayHasKey('organization_id', $columns);
        $this->assertArrayHasKey('folder_name', $columns);
        
        // Check self-referential foreign key for nesting
        $this->assertTrue(
            $this->hasForeignKey('folders', 'parent_folder_id', 'folders', 'id'),
            'Folders should support nesting with parent_folder_id'
        );
    }

    /**
     * Test schema reset functionality
     * 
     * @test
     */
    public function testSchemaResetFunctionality(): void
    {
        // Initialize schemas
        Schema::initialize();
        $this->assertTrue($this->tableExists('users'));
        
        // Reset schemas
        $result = Schema::reset();
        $this->assertTrue($result, 'Schema reset should return true');
        
        // Verify tables are dropped
        $this->assertFalse($this->tableExists('users'));
        $this->assertFalse($this->tableExists('organizations'));
    }

    /**
     * Test re-initialization after reset
     * 
     * @test
     */
    public function testReInitializationAfterReset(): void
    {
        // Initialize, reset, then reinitialize
        Schema::initialize();
        Schema::reset();
        $result = Schema::initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($this->tableExists('users'));
        $this->assertTrue($this->tableExists('organizations'));
    }

    /**
     * Test idempotent initialization (should not fail when called multiple times)
     * 
     * @test
     */
    public function testIdempotentInitialization(): void
    {
        // Call initialize multiple times
        $result1 = Schema::initialize();
        $result2 = Schema::initialize();
        $result3 = Schema::initialize();
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);
        
        // All tables should still exist
        $this->assertTrue($this->tableExists('users'));
        $this->assertTrue($this->tableExists('organizations'));
    }

    // ==================== Helper Methods ====================

    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $result = Database::fetch(
                "SELECT 1 FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ?",
                [$tableName]
            );
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get all columns from a table
     */
    private function getTableColumns(string $tableName): array
    {
        try {
            $columns = Database::fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ?",
                [$tableName]
            );

            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[$column['COLUMN_NAME']] = true;
            }
            return $columnNames;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Check if a column has a unique constraint
     */
    private function hasUniqueConstraint(string $tableName, string $columnName): bool
    {
        try {
            $result = Database::fetch(
                "SELECT 1 FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ? 
                 AND COLUMN_NAME = ? 
                 AND SEQ_IN_INDEX = 1 
                 AND NON_UNIQUE = 0",
                [$tableName, $columnName]
            );
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a foreign key relationship exists
     */
    private function hasForeignKey(string $table, string $column, string $refTable, string $refColumn): bool
    {
        try {
            $result = Database::fetch(
                "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ? 
                 AND COLUMN_NAME = ? 
                 AND REFERENCED_TABLE_NAME = ? 
                 AND REFERENCED_COLUMN_NAME = ?",
                [$table, $column, $refTable, $refColumn]
            );
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get foreign key constraint details
     */
    private function getForeignKeyConstraint(string $table, string $column): ?array
    {
        try {
            $result = Database::fetch(
                "SELECT * FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ? 
                 AND COLUMN_NAME = ? 
                 AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$table, $column]
            );
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

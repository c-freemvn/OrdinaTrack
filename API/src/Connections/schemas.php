<?php

namespace Ordinatrack\Api\Connections;

use PDOException;
use Exception;

/**
 * Database Schema Manager
 *
 * Manages database schema creation and migrations with full support for:
 * - Entity relationships (One-to-Many, Many-to-Many)
 * - Automatic table creation on demand
 * - Cascade operations (delete, update)
 * - Indexes for performance
 * - Timestamps and audit trails
 * - Soft deletes
 */
class Schema
{
    /**
     * Track created tables to avoid redundant checks
     * @var array
     */
    private static array $createdTables = [];

    /**
     * Initialize all schemas (create tables if they don't exist)
     * Call this during application bootstrap
     *
     * @return bool
     * @throws Exception
     */
    public static function initialize(): bool
    {
        try {
            self::createUsersTable();
            self::createOrganizationsTable();
            self::createOrganizationMembersTable();
            self::createProvinceTable();
            self::createDistrictTable();
            self::createBranchTable();
            self::createRolesTable();
            self::createUserRolesTable();
            self::createPermissionsTable();
            self::createRolePermissionsTable();
            self::createMembersTable();
            self::createMemberRolesTable();
            self::createLogisticsTable();
            self::createFoldersTable();
            self::createRequestsTable();
            self::createAuditLogsTable();

            // Create super admin user and assign all permissions
            self::createSuperAdminUser();

            error_log('All database schemas initialized successfully');
            return true;
        } catch (PDOException $e) {
            error_log('Schema initialization failed: ' . $e->getMessage());
            throw new Exception('Failed to initialize database schemas: ' . $e->getMessage());
        }
    }

    /**
     * Users table - Primary user accounts
     */
    private static function createUsersTable(): void
    {
        if (self::tableExists('users')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            profile_picture VARCHAR(500),
            is_active BOOLEAN DEFAULT true,
            email_verified BOOLEAN DEFAULT false,
            email_verified_at TIMESTAMP NULL,
            password_reset_token VARCHAR(255) UNIQUE,
            password_reset_expires_at TIMESTAMP NULL,
            last_login_at TIMESTAMP NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['users'] = true;
        error_log('Created users table');
    }

    /**
     * Organizations table - Church organizations
     */
    private static function createOrganizationsTable(): void
    {
        if (self::tableExists('organizations')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS organizations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            organization_type ENUM('NHQ', 'Province', 'District', 'Branch') NOT NULL,
            parent_organization_id BIGINT UNSIGNED,
            contact_email VARCHAR(255),
            contact_phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            logo_url VARCHAR(500),
            website VARCHAR(255),
            is_active BOOLEAN DEFAULT true,
            created_by BIGINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (parent_organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_organization_type (organization_type),
            INDEX idx_parent_organization_id (parent_organization_id),
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['organizations'] = true;
        error_log('Created organizations table');
    }

    /**
     * Organization members table - Links users to organizations
     */
    private static function createOrganizationMembersTable(): void
    {
        if (self::tableExists('organization_members')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS organization_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50),
            position VARCHAR(100),
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_org_user (organization_id, user_id),
            INDEX idx_organization_id (organization_id),
            INDEX idx_user_id (user_id),
            INDEX idx_is_active (is_active),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['organization_members'] = true;
        error_log('Created organization_members table');
    }

    /**
     * Provinces table - Geographic hierarchy
     */
    private static function createProvinceTable(): void
    {
        if (self::tableExists('provinces')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS provinces (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            code VARCHAR(10) UNIQUE,
            organization_id BIGINT UNSIGNED,
            description TEXT,
            administrator_id BIGINT UNSIGNED,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
            FOREIGN KEY (administrator_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_name (name),
            INDEX idx_is_active (is_active),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['provinces'] = true;
        error_log('Created provinces table');
    }

    /**
     * Districts table - Geographic hierarchy under provinces
     */
    private static function createDistrictTable(): void
    {
        if (self::tableExists('districts')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS districts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10),
            province_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED,
            description TEXT,
            administrator_id BIGINT UNSIGNED,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE CASCADE,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
            FOREIGN KEY (administrator_id) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_province_name (province_id, name),
            INDEX idx_province_id (province_id),
            INDEX idx_is_active (is_active),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['districts'] = true;
        error_log('Created districts table');
    }

    /**
     * Branches table - Geographic hierarchy under districts
     */
    private static function createBranchTable(): void
    {
        if (self::tableExists('branches')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS branches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10),
            district_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED,
            description TEXT,
            address TEXT,
            contact_person VARCHAR(100),
            contact_phone VARCHAR(20),
            contact_email VARCHAR(255),
            administrator_id BIGINT UNSIGNED,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
            FOREIGN KEY (administrator_id) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_district_name (district_id, name),
            INDEX idx_district_id (district_id),
            INDEX idx_is_active (is_active),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['branches'] = true;
        error_log('Created branches table');
    }

    /**
     * Roles table - Define system roles
     */
    private static function createRolesTable(): void
    {
        if (self::tableExists('roles')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(100) UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['roles'] = true;
        error_log('Created roles table');

        // Insert default roles if they don't exist
        self::insertDefaultRoles();
    }

    /**
     * User roles table - Many-to-Many relationship between users and roles
     */
    private static function createUserRolesTable(): void
    {
        if (self::tableExists('user_roles')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS user_roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id, role_id),
            INDEX idx_user_id (user_id),
            INDEX idx_role_id (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['user_roles'] = true;
        error_log('Created user_roles table');
    }

    /**
     * Permissions table - Define system permissions
     */
    private static function createPermissionsTable(): void
    {
        if (self::tableExists('permissions')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(100) UNIQUE,
            resource VARCHAR(100),
            action VARCHAR(100),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_slug (slug),
            INDEX idx_resource (resource)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['permissions'] = true;
        error_log('Created permissions table');

        // Insert default permissions if they don't exist
        self::insertDefaultPermissions();
    }

    /**
     * Role permissions table - Many-to-Many relationship between roles and permissions
     */
    private static function createRolePermissionsTable(): void
    {
        if (self::tableExists('role_permissions')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            UNIQUE KEY unique_role_permission (role_id, permission_id),
            INDEX idx_role_id (role_id),
            INDEX idx_permission_id (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['role_permissions'] = true;
        error_log('Created role_permissions table');
    }

    /**
     * Members table - Organization members/attendees
     */
    private static function createMembersTable(): void
    {
        if (self::tableExists('members')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(20),
            date_of_birth DATE,
            gender ENUM('Male', 'Female', 'Other'),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            postal_code VARCHAR(20),
            member_since DATE,
            is_active BOOLEAN DEFAULT true,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            INDEX idx_organization_id (organization_id),
            INDEX idx_email (email),
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['members'] = true;
        error_log('Created members table');
    }

    /**
     * Member roles table - Roles members hold in the organization
     */
    private static function createMemberRolesTable(): void
    {
        if (self::tableExists('member_roles')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS member_roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            role_name VARCHAR(100) NOT NULL,
            start_date DATE,
            end_date DATE,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            INDEX idx_member_id (member_id),
            INDEX idx_organization_id (organization_id),
            INDEX idx_is_active (is_active),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['member_roles'] = true;
        error_log('Created member_roles table');
    }

    /**
     * Logistics table - Track logistics/inventory
     */
    private static function createLogisticsTable(): void
    {
        if (self::tableExists('logistics')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS logistics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            quantity INT DEFAULT 0,
            unit_price DECIMAL(10, 2),
            total_value DECIMAL(15, 2),
            status ENUM('Available', 'In Use', 'Damaged', 'Archived') DEFAULT 'Available',
            location VARCHAR(255),
            assigned_to BIGINT UNSIGNED,
            received_date DATE,
            expiry_date DATE,
            notes TEXT,
            created_by BIGINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_organization_id (organization_id),
            INDEX idx_category (category),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['logistics'] = true;
        error_log('Created logistics table');
    }

    /**
     * Folders table - Document/file organization
     */
    private static function createFoldersTable(): void
    {
        if (self::tableExists('folders')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS folders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            parent_folder_id BIGINT UNSIGNED,
            folder_name VARCHAR(255) NOT NULL,
            description TEXT,
            created_by BIGINT UNSIGNED,
            is_public BOOLEAN DEFAULT false,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_folder_id) REFERENCES folders(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_organization_id (organization_id),
            INDEX idx_parent_folder_id (parent_folder_id),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['folders'] = true;
        error_log('Created folders table');
    }

    /**
     * Requests table - Track various requests (resources, approvals, etc.)
     */
    private static function createRequestsTable(): void
    {
        if (self::tableExists('requests')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            request_type VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            requested_by BIGINT UNSIGNED NOT NULL,
            assigned_to BIGINT UNSIGNED,
            status ENUM('Pending', 'Approved', 'Rejected', 'In Progress', 'Completed') DEFAULT 'Pending',
            priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
            due_date DATE,
            approved_by BIGINT UNSIGNED,
            approval_date TIMESTAMP NULL,
            rejection_reason TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_organization_id (organization_id),
            INDEX idx_request_type (request_type),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['requests'] = true;
        error_log('Created requests table');
    }

    /**
     * Audit logs table - Track all system activities for compliance
     */
    private static function createAuditLogsTable(): void
    {
        if (self::tableExists('audit_logs')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED,
            resource_type VARCHAR(100),
            resource_id BIGINT UNSIGNED,
            action VARCHAR(50),
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_resource_type (resource_type),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::$createdTables['audit_logs'] = true;
        error_log('Created audit_logs table');
    }

    /**
     * Check if a table exists
     *
     * @param string $tableName
     * @return bool
     */
    private static function tableExists(string $tableName): bool
    {
        if (isset(self::$createdTables[$tableName])) {
            return true;
        }

        try {
            $result = Database::fetch(
                "SELECT 1 FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ?",
                [$tableName]
            );
            return $result !== false;
        } catch (PDOException $e) {
            error_log("Table existence check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert default roles
     */
    private static function insertDefaultRoles(): void
    {
        $defaultRoles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'System administrator with full access'],
            ['name' => 'NHQ Admin', 'slug' => 'nhq_admin', 'description' => 'National headquarters administrator'],
            ['name' => 'Province Lead', 'slug' => 'province_lead', 'description' => 'Province administrator'],
            ['name' => 'District Lead', 'slug' => 'district_lead', 'description' => 'District administrator'],
            ['name' => 'Branch Admin', 'slug' => 'branch_admin', 'description' => 'Branch administrator'],
            ['name' => 'Secretary', 'slug' => 'secretary', 'description' => 'Organization secretary'],
            ['name' => 'Member', 'slug' => 'member', 'description' => 'Regular member'],
            ['name' => 'Guest', 'slug' => 'guest', 'description' => 'Guest user with limited access'],
        ];

        foreach ($defaultRoles as $role) {
            $exists = Database::fetch(
                "SELECT id FROM roles WHERE slug = ?",
                [$role['slug']]
            );

            if (!$exists) {
                Database::execute(
                    "INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)",
                    [$role['name'], $role['slug'], $role['description']]
                );
            }
        }
    }

    /**
     * Insert default permissions
     */
    private static function insertDefaultPermissions(): void
    {
        $defaultPermissions = [
            // User management
            ['name' => 'Create User', 'slug' => 'create_user', 'resource' => 'users', 'action' => 'create'],
            ['name' => 'Read Users', 'slug' => 'read_users', 'resource' => 'users', 'action' => 'read'],
            ['name' => 'Update User', 'slug' => 'update_user', 'resource' => 'users', 'action' => 'update'],
            ['name' => 'Delete User', 'slug' => 'delete_user', 'resource' => 'users', 'action' => 'delete'],

            // Organization management
            ['name' => 'Create Organization', 'slug' => 'create_organization', 'resource' => 'organizations', 'action' => 'create'],
            ['name' => 'Read Organizations', 'slug' => 'read_organizations', 'resource' => 'organizations', 'action' => 'read'],
            ['name' => 'Update Organization', 'slug' => 'update_organization', 'resource' => 'organizations', 'action' => 'update'],
            ['name' => 'Delete Organization', 'slug' => 'delete_organization', 'resource' => 'organizations', 'action' => 'delete'],

            // Member management
            ['name' => 'Create Member', 'slug' => 'create_member', 'resource' => 'members', 'action' => 'create'],
            ['name' => 'Read Members', 'slug' => 'read_members', 'resource' => 'members', 'action' => 'read'],
            ['name' => 'Update Member', 'slug' => 'update_member', 'resource' => 'members', 'action' => 'update'],
            ['name' => 'Delete Member', 'slug' => 'delete_member', 'resource' => 'members', 'action' => 'delete'],

            // Logistics management
            ['name' => 'Create Logistics', 'slug' => 'create_logistics', 'resource' => 'logistics', 'action' => 'create'],
            ['name' => 'Read Logistics', 'slug' => 'read_logistics', 'resource' => 'logistics', 'action' => 'read'],
            ['name' => 'Update Logistics', 'slug' => 'update_logistics', 'resource' => 'logistics', 'action' => 'update'],
            ['name' => 'Delete Logistics', 'slug' => 'delete_logistics', 'resource' => 'logistics', 'action' => 'delete'],

            // Request management
            ['name' => 'Create Request', 'slug' => 'create_request', 'resource' => 'requests', 'action' => 'create'],
            ['name' => 'Read Requests', 'slug' => 'read_requests', 'resource' => 'requests', 'action' => 'read'],
            ['name' => 'Approve Request', 'slug' => 'approve_request', 'resource' => 'requests', 'action' => 'approve'],
            ['name' => 'Delete Request', 'slug' => 'delete_request', 'resource' => 'requests', 'action' => 'delete'],

            // Audit logs
            ['name' => 'Read Audit Logs', 'slug' => 'read_audit_logs', 'resource' => 'audit_logs', 'action' => 'read'],
        ];

        foreach ($defaultPermissions as $permission) {
            $exists = Database::fetch(
                "SELECT id FROM permissions WHERE slug = ?",
                [$permission['slug']]
            );

            if (!$exists) {
                Database::execute(
                    "INSERT INTO permissions (name, slug, resource, action) VALUES (?, ?, ?, ?)",
                    [$permission['name'], $permission['slug'], $permission['resource'], $permission['action']]
                );
            }
        }
    }

    /**
     * Reset all schemas (for development/testing only)
     * WARNING: This will delete all data!
     *
     * @return bool
     */
    public static function reset(): bool
    {
        $tables = [
            'role_permissions',
            'user_roles',
            'member_roles',
            'organization_members',
            'audit_logs',
            'requests',
            'folders',
            'logistics',
            'members',
            'branches',
            'districts',
            'provinces',
            'permissions',
            'roles',
            'organizations',
            'users'
        ];

        try {
            Database::query("SET FOREIGN_KEY_CHECKS=0");

            foreach ($tables as $table) {
                Database::execute("DROP TABLE IF EXISTS $table");
            }

            Database::query("SET FOREIGN_KEY_CHECKS=1");

            self::$createdTables = [];
            error_log('All database schemas reset successfully');
            return true;
        } catch (PDOException $e) {
            error_log('Schema reset failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create super admin user with all permissions
     * Called during schema initialization
     */
    private static function createSuperAdminUser(): void
    {
        try {
            // Check if super admin already exists
            $superAdminExists = Database::fetch(
                "SELECT id FROM users WHERE email = 'super.admin@ordinatrack.com'"
            );

            if ($superAdminExists) {
                error_log('Super admin user already exists');
                return;
            }

            // Create super admin user
            $hashedPassword = password_hash('SuperAdmin@123!', PASSWORD_BCRYPT, ['cost' => 12]);
            
            Database::execute(
                "INSERT INTO users (email, password, first_name, last_name, is_active, email_verified, email_verified_at) 
                 VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
                [
                    'super.admin@ordinatrack.com',
                    $hashedPassword,
                    'Super',
                    'Admin',
                    true,
                    true
                ]
            );

            $superAdminId = Database::lastInsertId();
            error_log("Super admin user created with ID: {$superAdminId}");

            // Get or create Admin role
            $adminRole = Database::fetch("SELECT id FROM roles WHERE slug = 'admin'");
            
            if (!$adminRole) {
                Database::execute(
                    "INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)",
                    ['Admin', 'admin', 'System administrator with full access']
                );
                $adminRole = Database::fetch("SELECT id FROM roles WHERE slug = 'admin'");
            }

            if ($adminRole) {
                // Assign admin role to super admin
                Database::execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$superAdminId, $adminRole['id']]
                );

                // Get all permissions and assign them to admin role
                $permissions = Database::fetchAll("SELECT id FROM permissions");
                
                foreach ($permissions as $permission) {
                    // Check if already assigned
                    $exists = Database::fetch(
                        "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                        [$adminRole['id'], $permission['id']]
                    );

                    if (!$exists) {
                        Database::execute(
                            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                            [$adminRole['id'], $permission['id']]
                        );
                    }
                }

                error_log("All permissions assigned to admin role");
            }
        } catch (PDOException $e) {
            error_log('Create super admin user error: ' . $e->getMessage());
        }
    }
    /**
     * Private constructor to prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * Private clone to prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize to prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize schema manager');
    }
}

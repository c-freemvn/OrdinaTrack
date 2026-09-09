<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ordinatrack\Api\Connections\Schema;
use Ordinatrack\Api\Connections\Database;
use Ordinatrack\Api\Config\Config;

/**
 * Schema Integration Test Suite
 * 
 * Tests the schema manager with actual data operations
 */
class SchemaIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Config::init();
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        try {
            Schema::reset();
        } catch (\Exception $e) {
            // Ignore errors during reset
        }
    }

    /**
     * Test creating user and retrieving from database
     * 
     * @test
     */
    public function testInsertAndRetrieveUser(): void
    {
        Schema::initialize();
        
        // Insert a test user
        $result = Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            [
                'test@example.com',
                password_hash('password123', PASSWORD_BCRYPT),
                'John',
                'Doe'
            ]
        );
        
        $this->assertEquals(1, $result, 'Should insert one user');
        
        // Retrieve the user
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ?",
            ['test@example.com']
        );
        
        $this->assertNotFalse($user);
        $this->assertEquals('John', $user['first_name']);
        $this->assertEquals('Doe', $user['last_name']);
    }

    /**
     * Test organization hierarchy insertion
     * 
     * @test
     */
    public function testOrganizationHierarchyInsertion(): void
    {
        Schema::initialize();
        
        // Create parent organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['National HQ', 'NHQ']
        );
        
        $nhq = Database::fetch(
            "SELECT id FROM organizations WHERE name = ?",
            ['National HQ']
        );
        
        $this->assertNotFalse($nhq);
        
        // Create child organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type, parent_organization_id) 
             VALUES (?, ?, ?)",
            ['Lagos Province', 'Province', $nhq['id']]
        );
        
        $province = Database::fetch(
            "SELECT * FROM organizations WHERE name = ?",
            ['Lagos Province']
        );
        
        $this->assertNotFalse($province);
        $this->assertEquals($nhq['id'], $province['parent_organization_id']);
    }

    /**
     * Test geographic hierarchy creation
     * 
     * @test
     */
    public function testGeographicHierarchyCreation(): void
    {
        Schema::initialize();
        
        // Create province
        Database::execute(
            "INSERT INTO provinces (name, code) VALUES (?, ?)",
            ['Lagos', 'LG']
        );
        
        $province = Database::fetch(
            "SELECT id FROM provinces WHERE code = ?",
            ['LG']
        );
        
        $this->assertNotFalse($province);
        
        // Create district under province
        Database::execute(
            "INSERT INTO districts (name, code, province_id) VALUES (?, ?, ?)",
            ['Ikorodu', 'IK', $province['id']]
        );
        
        $district = Database::fetch(
            "SELECT * FROM districts WHERE code = ?",
            ['IK']
        );
        
        $this->assertNotFalse($district);
        $this->assertEquals($province['id'], $district['province_id']);
        
        // Create branch under district
        Database::execute(
            "INSERT INTO branches (name, code, district_id) VALUES (?, ?, ?)",
            ['Ikorodu Branch', 'IK-01', $district['id']]
        );
        
        $branch = Database::fetch(
            "SELECT * FROM branches WHERE code = ?",
            ['IK-01']
        );
        
        $this->assertNotFalse($branch);
        $this->assertEquals($district['id'], $branch['district_id']);
    }

    /**
     * Test user-organization relationship
     * 
     * @test
     */
    public function testUserOrganizationRelationship(): void
    {
        Schema::initialize();
        
        // Create user
        Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            ['secretary@church.com', password_hash('pass', PASSWORD_BCRYPT), 'Jane', 'Smith']
        );
        
        $user = Database::fetch("SELECT id FROM users WHERE email = ?", ['secretary@church.com']);
        
        // Create organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['Church Branch', 'Branch']
        );
        
        $org = Database::fetch("SELECT id FROM organizations WHERE name = ?", ['Church Branch']);
        
        // Link user to organization
        Database::execute(
            "INSERT INTO organization_members (organization_id, user_id, role) VALUES (?, ?, ?)",
            [$org['id'], $user['id'], 'Secretary']
        );
        
        // Verify relationship
        $member = Database::fetch(
            "SELECT * FROM organization_members WHERE user_id = ? AND organization_id = ?",
            [$user['id'], $org['id']]
        );
        
        $this->assertNotFalse($member);
        $this->assertEquals('Secretary', $member['role']);
    }

    /**
     * Test RBAC user-role assignment
     * 
     * @test
     */
    public function testRBACUserRoleAssignment(): void
    {
        Schema::initialize();
        
        // Create user
        Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            ['admin@church.com', password_hash('pass', PASSWORD_BCRYPT), 'Admin', 'User']
        );
        
        $user = Database::fetch("SELECT id FROM users WHERE email = ?", ['admin@church.com']);
        
        // Get admin role
        $adminRole = Database::fetch("SELECT id FROM roles WHERE slug = ?", ['admin']);
        
        // Assign role to user
        Database::execute(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            [$user['id'], $adminRole['id']]
        );
        
        // Verify user has role
        $userRole = Database::fetch(
            "SELECT * FROM user_roles WHERE user_id = ? AND role_id = ?",
            [$user['id'], $adminRole['id']]
        );
        
        $this->assertNotFalse($userRole);
    }

    /**
     * Test role-permission assignment
     * 
     * @test
     */
    public function testRolePermissionAssignment(): void
    {
        Schema::initialize();
        
        // Get admin role
        $adminRole = Database::fetch("SELECT id FROM roles WHERE slug = ?", ['admin']);
        
        // Get create_user permission
        $permission = Database::fetch(
            "SELECT id FROM permissions WHERE slug = ?",
            ['create_user']
        );
        
        // Assign permission to role
        Database::execute(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [$adminRole['id'], $permission['id']]
        );
        
        // Verify permission is assigned
        $rolePermission = Database::fetch(
            "SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$adminRole['id'], $permission['id']]
        );
        
        $this->assertNotFalse($rolePermission);
    }

    /**
     * Test member creation and roles
     * 
     * @test
     */
    public function testMemberCreationWithRoles(): void
    {
        Schema::initialize();
        
        // Create organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['Test Church', 'Branch']
        );
        
        $org = Database::fetch("SELECT id FROM organizations WHERE name = ?", ['Test Church']);
        
        // Create member
        Database::execute(
            "INSERT INTO members (organization_id, first_name, last_name, email) 
             VALUES (?, ?, ?, ?)",
            [$org['id'], 'John', 'Member', 'john@church.com']
        );
        
        $member = Database::fetch(
            "SELECT id FROM members WHERE email = ?",
            ['john@church.com']
        );
        
        $this->assertNotFalse($member);
        
        // Assign role to member
        Database::execute(
            "INSERT INTO member_roles (member_id, organization_id, role_name) 
             VALUES (?, ?, ?)",
            [$member['id'], $org['id'], 'Elder']
        );
        
        // Verify role assignment
        $memberRole = Database::fetch(
            "SELECT * FROM member_roles WHERE member_id = ? AND organization_id = ?",
            [$member['id'], $org['id']]
        );
        
        $this->assertNotFalse($memberRole);
        $this->assertEquals('Elder', $memberRole['role_name']);
    }

    /**
     * Test logistics item tracking
     * 
     * @test
     */
    public function testLogisticsItemTracking(): void
    {
        Schema::initialize();
        
        // Create organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['Test Church', 'Branch']
        );
        
        $org = Database::fetch("SELECT id FROM organizations WHERE name = ?", ['Test Church']);
        
        // Create logistics item
        Database::execute(
            "INSERT INTO logistics (organization_id, item_name, category, quantity, unit_price, status) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$org['id'], 'Chairs', 'Furniture', 50, 2500.00, 'Available']
        );
        
        // Retrieve item
        $item = Database::fetch(
            "SELECT * FROM logistics WHERE item_name = ?",
            ['Chairs']
        );
        
        $this->assertNotFalse($item);
        $this->assertEquals(50, $item['quantity']);
        $this->assertEquals('Available', $item['status']);
    }

    /**
     * Test folder hierarchy for documents
     * 
     * @test
     */
    public function testFolderHierarchy(): void
    {
        Schema::initialize();
        
        // Create organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['Test Church', 'Branch']
        );
        
        $org = Database::fetch("SELECT id FROM organizations WHERE name = ?", ['Test Church']);
        
        // Create root folder
        Database::execute(
            "INSERT INTO folders (organization_id, folder_name) VALUES (?, ?)",
            [$org['id'], 'Documents']
        );
        
        $rootFolder = Database::fetch(
            "SELECT id FROM folders WHERE folder_name = ?",
            ['Documents']
        );
        
        // Create subfolder
        Database::execute(
            "INSERT INTO folders (organization_id, parent_folder_id, folder_name) 
             VALUES (?, ?, ?)",
            [$org['id'], $rootFolder['id'], 'Minutes']
        );
        
        // Verify hierarchy
        $subFolder = Database::fetch(
            "SELECT * FROM folders WHERE folder_name = ? AND parent_folder_id = ?",
            ['Minutes', $rootFolder['id']]
        );
        
        $this->assertNotFalse($subFolder);
        $this->assertEquals($rootFolder['id'], $subFolder['parent_folder_id']);
    }

    /**
     * Test requests workflow
     * 
     * @test
     */
    public function testRequestsWorkflow(): void
    {
        Schema::initialize();
        
        // Create users
        Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            ['requester@church.com', password_hash('pass', PASSWORD_BCRYPT), 'Requester', 'User']
        );
        
        $requester = Database::fetch(
            "SELECT id FROM users WHERE email = ?",
            ['requester@church.com']
        );
        
        // Create organization
        Database::execute(
            "INSERT INTO organizations (name, organization_type) VALUES (?, ?)",
            ['Test Church', 'Branch']
        );
        
        $org = Database::fetch("SELECT id FROM organizations WHERE name = ?", ['Test Church']);
        
        // Create request
        Database::execute(
            "INSERT INTO requests (organization_id, request_type, title, requested_by, status, priority) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$org['id'], 'Resource', 'Request Chairs', $requester['id'], 'Pending', 'High']
        );
        
        // Verify request
        $request = Database::fetch(
            "SELECT * FROM requests WHERE title = ?",
            ['Request Chairs']
        );
        
        $this->assertNotFalse($request);
        $this->assertEquals('Pending', $request['status']);
        $this->assertEquals('High', $request['priority']);
        
        // Update request status
        Database::execute(
            "UPDATE requests SET status = ? WHERE id = ?",
            ['Approved', $request['id']]
        );
        
        // Verify update
        $updatedRequest = Database::fetch(
            "SELECT * FROM requests WHERE id = ?",
            [$request['id']]
        );
        
        $this->assertEquals('Approved', $updatedRequest['status']);
    }

    /**
     * Test soft delete functionality
     * 
     * @test
     */
    public function testSoftDeleteFunctionality(): void
    {
        Schema::initialize();
        
        // Create user
        Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            ['delete@test.com', password_hash('pass', PASSWORD_BCRYPT), 'Delete', 'Me']
        );
        
        $user = Database::fetch("SELECT id FROM users WHERE email = ?", ['delete@test.com']);
        
        $this->assertNotFalse($user);
        $this->assertNull($user['deleted_at']);
        
        // Soft delete user
        Database::execute(
            "UPDATE users SET deleted_at = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Verify soft delete
        $softDeletedUser = Database::fetch(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NOT NULL",
            [$user['id']]
        );
        
        $this->assertNotFalse($softDeletedUser);
        
        // User still exists in database (not hard deleted)
        $stillExists = Database::fetch(
            "SELECT * FROM users WHERE id = ?",
            [$user['id']]
        );
        
        $this->assertNotFalse($stillExists);
    }

    /**
     * Test audit logs recording
     * 
     * @test
     */
    public function testAuditLogsRecording(): void
    {
        Schema::initialize();
        
        // Create user
        Database::execute(
            "INSERT INTO users (email, password, first_name, last_name) 
             VALUES (?, ?, ?, ?)",
            ['user@test.com', password_hash('pass', PASSWORD_BCRYPT), 'Test', 'User']
        );
        
        $user = Database::fetch("SELECT id FROM users WHERE email = ?", ['user@test.com']);
        
        // Record audit log
        $oldValues = json_encode(['first_name' => 'Old Name']);
        $newValues = json_encode(['first_name' => 'New Name']);
        
        Database::execute(
            "INSERT INTO audit_logs (user_id, resource_type, resource_id, action, old_values, new_values, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $user['id'],
                'users',
                $user['id'],
                'update',
                $oldValues,
                $newValues,
                '127.0.0.1'
            ]
        );
        
        // Verify audit log
        $auditLog = Database::fetch(
            "SELECT * FROM audit_logs WHERE resource_id = ? AND resource_type = ?",
            [$user['id'], 'users']
        );
        
        $this->assertNotFalse($auditLog);
        $this->assertEquals('update', $auditLog['action']);
    }
}

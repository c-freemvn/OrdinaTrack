# Super Admin Module - Testing Guide

## Overview
This document provides comprehensive testing procedures for the Super Admin module of OrdinaTrack.

## System Initialization

### Super Admin Account Creation
- **Email**: `super.admin@ordinatrack.com`
- **Default Password**: `SuperAdmin@123!`
- **Created**: Automatically on first database schema initialization
- **Assigned Role**: Admin (with all permissions)

The super admin user and role are created during the `Schema::initialize()` call in `/API/index.php`.

## Backend Testing

### 1. Super Admin User Creation

**Expected Behavior**:
- When the API is first accessed, the database schema is initialized
- A super admin user is created automatically with email `super.admin@ordinatrack.com`
- The user has a bcrypt-hashed password: `SuperAdmin@123!`
- The super admin is assigned the "Admin" role
- All permissions are assigned to the Admin role

**Testing Steps**:
1. Clear the database (drop ordinatrack database)
2. Access `/OrdinaTrack/API/index.php` in browser
3. Check database for `users` table and verify super admin exists:
   ```sql
   SELECT id, email, is_active, email_verified FROM users WHERE email = 'super.admin@ordinatrack.com';
   ```
4. Verify Admin role exists and has all permissions:
   ```sql
   SELECT r.id, r.name, COUNT(rp.id) as permission_count 
   FROM roles r 
   LEFT JOIN role_permissions rp ON r.id = rp.role_id 
   WHERE r.slug = 'admin' 
   GROUP BY r.id, r.name;
   ```

### 2. Authentication Endpoint

**Test Super Admin Login**:

```bash
curl -X POST http://localhost:8888/OrdinaTrack/API/index.php/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "super.admin@ordinatrack.com",
    "password": "SuperAdmin@123!"
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "id": 1,
      "email": "super.admin@ordinatrack.com",
      "first_name": "Super",
      "last_name": "Admin",
      "role": "admin"
    }
  }
}
```

### 3. Super Admin API Endpoints

#### Test Get All Roles

```bash
curl -X GET http://localhost:8888/OrdinaTrack/API/index.php/admin/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response**: Array of all roles with permission counts

#### Test Get All Permissions

```bash
curl -X GET http://localhost:8888/OrdinaTrack/API/index.php/admin/permissions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response**: Array of all permissions

#### Test Get System Statistics

```bash
curl -X GET http://localhost:8888/OrdinaTrack/API/index.php/admin/stats \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "total_users": 1,
    "active_users": 1,
    "total_roles": 8,
    "total_permissions": 20,
    "total_organizations": 0
  }
}
```

#### Test Create New Role

```bash
curl -X POST http://localhost:8888/OrdinaTrack/API/index.php/admin/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Manager",
    "slug": "manager",
    "description": "Manager role"
  }'
```

**Expected Response**: New role object with ID

#### Test Assign Permissions to Role

```bash
curl -X POST http://localhost:8888/OrdinaTrack/API/index.php/admin/roles/2/permissions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permission_ids": [1, 2, 3, 4]
  }'
```

**Expected Response**: Role with assigned permissions

#### Test Get All Users

```bash
curl -X GET "http://localhost:8888/OrdinaTrack/API/index.php/admin/users?limit=10&offset=0" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response**: User list with pagination info

#### Test Assign Roles to User

```bash
curl -X PUT http://localhost:8888/OrdinaTrack/API/index.php/admin/users/2/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role_ids": [1, 2]
  }'
```

**Expected Response**: User with updated roles

#### Test Update User Status

```bash
curl -X PUT http://localhost:8888/OrdinaTrack/API/index.php/admin/users/2/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "is_active": false
  }'
```

**Expected Response**: User with updated status

### 4. Authorization Middleware Testing

#### Test Non-Super Admin Access

**Create a regular user**:
```bash
curl -X POST http://localhost:8888/OrdinaTrack/API/index.php/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "Test@1234",
    "first_name": "Test",
    "last_name": "User",
    "role": "Branch"
  }'
```

**Try to access admin endpoint with regular user token**:
```bash
curl -X GET http://localhost:8888/OrdinaTrack/API/index.php/admin/roles \
  -H "Authorization: Bearer REGULAR_USER_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response**:
```json
{
  "success": false,
  "message": "Unauthorized: Only super admin can access this endpoint",
  "statuscode": 403
}
```

### 5. Audit Logging

**Verify access is logged**:
```sql
SELECT * FROM audit_logs 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 10;
```

## Frontend Testing

### 1. Super Admin Dashboard Access

1. Open browser to `http://localhost:8888/OrdinaTrack/App/dashboard/admin/`
2. You should be redirected to login if not authenticated
3. Login with super admin credentials:
   - Email: `super.admin@ordinatrack.com`
   - Password: `SuperAdmin@123!`

### 2. Dashboard Features

#### Dashboard Section
- [ ] Statistics display (total users, active users, roles, permissions)
- [ ] Recent activity log appears
- [ ] Numbers update in real-time

#### Users Section
- [ ] Users list displays all users
- [ ] User status shows Active/Inactive badges
- [ ] Roles display correctly for each user
- [ ] Edit button opens user modal
- [ ] Can update user status
- [ ] Can assign/remove roles
- [ ] Delete button works (except for super admin)
- [ ] Changes are saved and reflected

#### Roles Section
- [ ] All roles display with permission counts
- [ ] Create Role button opens modal
- [ ] Can create new role with name, slug, description
- [ ] Can select permissions for role
- [ ] Edit existing role works
- [ ] Protected roles (admin, nhq_admin) cannot be deleted
- [ ] Changes are reflected immediately

#### Permissions Section
- [ ] All permissions display
- [ ] Shows resource and action for each permission
- [ ] Create Permission button opens modal
- [ ] Can create new permission
- [ ] Can delete permissions
- [ ] Changes reflected in roles that use them

#### Activity Logs Section
- [ ] Shows recent activity
- [ ] Displays user, action, resource, and timestamp
- [ ] Log entries sorted by newest first
- [ ] Pagination works if many logs

### 3. Modal Testing

#### User Modal
- [ ] Opens with user email and name (disabled)
- [ ] Status dropdown shows current value
- [ ] Roles checkboxes show current assignments
- [ ] Save button updates data
- [ ] Cancel closes modal

#### Role Modal
- [ ] Name, slug, description fields work
- [ ] Permission checkboxes display all permissions
- [ ] Can select/deselect permissions
- [ ] Save creates or updates role
- [ ] Cancel closes modal

#### Permission Modal
- [ ] All fields can be filled
- [ ] Save creates permission
- [ ] Cancel closes modal

### 4. Responsive Design

- [ ] Dashboard works on desktop (1920px+)
- [ ] Dashboard works on tablet (768px-1024px)
- [ ] Dashboard works on mobile (375px-767px)
- [ ] Sidebar collapses on mobile
- [ ] Tables scroll on smaller screens

## Integration Testing

### 1. Complete User Management Workflow

1. **Register new user** via `/auth/register`
2. **Login as super admin**
3. **Access admin dashboard**
4. **Go to Users section**
5. **Find newly created user**
6. **Edit user and assign roles**
7. **Change user status to inactive**
8. **Save changes**
9. **Verify changes in database**
10. **Delete user**
11. **Verify user is soft-deleted**

### 2. Complete Role Management Workflow

1. **Login as super admin**
2. **Go to Roles section**
3. **Create new role** "Auditor"
4. **Assign specific permissions** to the role
5. **Create new user** and assign "Auditor" role
6. **Verify user has only assigned permissions**
7. **Edit role** and add/remove permissions
8. **Verify user permissions update**
9. **Try to delete protected role** (should fail)
10. **Delete custom role** (should succeed)

### 3. Complete Permission Management Workflow

1. **Go to Permissions section**
2. **Create new permission** "Export Reports"
3. **Assign permission** to a role
4. **Create user** with that role
5. **Verify user has permission**
6. **Delete permission**
7. **Verify permission removed** from role

## Error Handling Testing

### 1. Invalid Requests

- [ ] Missing required fields returns 400
- [ ] Invalid role ID returns 404
- [ ] Invalid permission ID returns 404
- [ ] Non-existent user returns 404

### 2. Authorization Errors

- [ ] Non-super admin cannot access admin endpoints
- [ ] Regular user cannot modify roles/permissions
- [ ] Regular user cannot manage other users
- [ ] Expired token returns 99 (unauthorized)

### 3. Validation

- [ ] Duplicate role slug rejected
- [ ] Duplicate permission slug rejected
- [ ] Empty name/slug rejected
- [ ] Invalid role assignment rejected

## Performance Testing

### 1. Large Dataset Handling

- [ ] Dashboard loads with 1000+ users
- [ ] Pagination works correctly
- [ ] Filtering/searching works
- [ ] No console errors

### 2. Response Times

- [ ] Get all roles: < 500ms
- [ ] Get all permissions: < 500ms
- [ ] Get all users: < 1000ms
- [ ] Create role: < 1000ms
- [ ] Assign permissions: < 1000ms

## Security Testing

### 1. SQL Injection

- [ ] Test SQL injection in role name: `'; DROP TABLE roles; --`
- [ ] Verify table still exists
- [ ] Test in permission slug field
- [ ] Verify all inputs are properly escaped

### 2. XSS Prevention

- [ ] Create role with HTML: `<img src=x onerror="alert('xss')">`
- [ ] Verify HTML is escaped in display
- [ ] No alert appears
- [ ] HTML displays as text

### 3. CSRF Protection

- [ ] All state-changing operations use POST/PUT/DELETE
- [ ] Sessions have proper timeout
- [ ] Tokens are validated

## Troubleshooting

### Super Admin Not Created

**Issue**: Super admin user not appearing in database

**Solutions**:
1. Verify schema initialization ran: Check error logs
2. Manually create super admin:
   ```sql
   INSERT INTO users (email, password, first_name, last_name, is_active, email_verified, email_verified_at)
   VALUES ('super.admin@ordinatrack.com', '$2y$12$...', 'Super', 'Admin', 1, 1, NOW());
   ```
3. Assign admin role and permissions

### Admin Endpoints Return 403

**Issue**: Super admin getting permission denied

**Solutions**:
1. Verify user email is exactly `super.admin@ordinatrack.com`
2. Clear browser cache and re-login
3. Check that token is valid and not expired
4. Verify user is active in database

### Dashboard Not Loading

**Issue**: Dashboard shows loading state forever

**Solutions**:
1. Check browser console for errors
2. Verify API token is stored in localStorage
3. Check network tab for failed requests
4. Verify `/admin/stats` endpoint returns data
5. Check that API base URL is correct

### Changes Not Saving

**Issue**: User changes or role assignments not persisting

**Solutions**:
1. Check browser console for API errors
2. Verify database connection is working
3. Check that user has write permissions
4. Verify all required fields are filled
5. Look for validation errors in response

## Checklist for Completion

- [ ] Super admin user created automatically
- [ ] Super admin can login successfully
- [ ] All API endpoints accessible to super admin
- [ ] Non-super admin cannot access admin endpoints
- [ ] Dashboard displays all sections
- [ ] Users can be managed (create, read, update, delete)
- [ ] Roles can be managed (create, read, update, delete)
- [ ] Permissions can be managed (create, read, update, delete)
- [ ] Role-permission assignments work
- [ ] User-role assignments work
- [ ] Activity logging works
- [ ] Responsive design verified
- [ ] Error handling tested
- [ ] Security measures verified
- [ ] Performance acceptable

## Documentation References

- Backend API: `/API/src/Controller/SuperAdminController.php`
- Database Model: `/API/src/Model/SuperAdminModel.php`
- Routes: `/API/src/Routes/admin.route.php`
- Middleware: `/API/src/Middleware/SuperAdminMiddleware.php`
- Frontend Dashboard: `/App/dashboard/admin/index.html`
- Frontend Logic: `/App/Assets/js/super-admin.js`

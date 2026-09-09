# Super Admin Module - Setup & Quick Start

## What Was Created

### Backend Components

1. **SuperAdminModel** (`/API/src/Model/SuperAdminModel.php`)
   - CRUD operations for roles, permissions, and users
   - Permission assignment to roles
   - User role assignment
   - System statistics retrieval
   - Activity logging queries

2. **SuperAdminController** (`/API/src/Controller/SuperAdminController.php`)
   - API endpoint handlers for all admin operations
   - Input validation
   - Response formatting
   - Error handling

3. **SuperAdminMiddleware** (`/API/src/Middleware/SuperAdminMiddleware.php`)
   - Super admin verification
   - Permission checking (single, multiple)
   - Role verification
   - Access control for resources
   - Audit logging
   - IP address detection

4. **Admin Routes** (`/API/src/Routes/admin.route.php`)
   - REST endpoint definitions
   - Route matching and dispatch
   - Middleware integration
   - Authorization checks

5. **Schema Updates** (`/API/src/Connections/schemas.php`)
   - Auto-creates super admin user on initialization
   - Email: `super.admin@ordinatrack.com`
   - Password: `SuperAdmin@123!` (bcrypt hashed)
   - Assigns all permissions to Admin role

### Frontend Components

1. **Super Admin Dashboard** (`/App/dashboard/admin/index.html`)
   - Professional Tailwind CSS UI
   - Responsive design (mobile, tablet, desktop)
   - Sections: Dashboard, Users, Roles, Permissions, Activity Logs
   - Modal-based forms for CRUD operations
   - Real-time statistics

2. **Admin JavaScript** (`/App/Assets/js/super-admin.js`)
   - API integration layer
   - Dashboard data loading
   - CRUD operation handlers
   - Form validation
   - Error handling and alerts
   - Session management

## Quick Start

### Step 1: Initial Setup

1. Clear your database (optional, for fresh start):
   ```bash
   mysql -u root -proot ordinatrack -e "DROP DATABASE ordinatrack;"
   ```

2. Access the API to trigger schema initialization:
   ```
   http://localhost:8888/OrdinaTrack/API/index.php
   ```

3. Check that super admin was created:
   - Email: `super.admin@ordinatrack.com`
   - Password: `SuperAdmin@123!`

### Step 2: Access Super Admin Dashboard

1. Navigate to: `http://localhost:8888/OrdinaTrack/App/dashboard/admin/`
2. You'll be redirected to login if not authenticated
3. Login with:
   - **Email**: `super.admin@ordinatrack.com`
   - **Password**: `SuperAdmin@123!`

### Step 3: Verify Components

#### Dashboard
- View system statistics (users, roles, permissions, organizations)
- See recent activity log
- All data should load without errors

#### Users Management
- See list of all users
- Edit user status (active/inactive)
- Assign/remove roles for users
- Delete users (except super admin)

#### Roles Management
- See all system roles
- Create new roles with custom permissions
- Edit existing roles
- Delete custom roles (protected roles cannot be deleted)

#### Permissions Management
- See all system permissions
- Create new permissions
- Delete permissions (if not in use)
- Organized by resource type

#### Activity Logs
- View recent system activity
- See who did what and when
- Sorted by most recent

## API Endpoints

All endpoints require authentication and super admin access.

### Roles
- `GET /admin/roles` - Get all roles
- `GET /admin/roles/:id` - Get role with permissions
- `POST /admin/roles` - Create role
- `PUT /admin/roles/:id` - Update role
- `DELETE /admin/roles/:id` - Delete role
- `POST /admin/roles/:id/permissions` - Assign permissions to role

### Permissions
- `GET /admin/permissions` - Get all permissions
- `GET /admin/permissions/by-resource` - Get grouped by resource
- `POST /admin/permissions` - Create permission
- `PUT /admin/permissions/:id` - Update permission
- `DELETE /admin/permissions/:id` - Delete permission

### Users
- `GET /admin/users` - Get all users (with pagination)
- `GET /admin/users/:id` - Get user with permissions
- `PUT /admin/users/:id/roles` - Assign roles to user
- `PUT /admin/users/:id/status` - Update user status
- `DELETE /admin/users/:id` - Delete user

### System
- `GET /admin/stats` - Get system statistics
- `GET /admin/activity` - Get recent activity logs

## Default Roles

The following roles are created automatically:

1. **Admin** - Full system access
2. **NHQ Admin** - National HQ administrator
3. **Province Lead** - Province level administrator
4. **District Lead** - District level administrator
5. **Branch Admin** - Branch level administrator
6. **Secretary** - Organization secretary
7. **Member** - Regular member
8. **Guest** - Guest user with limited access

## Default Permissions

Permissions are organized by resource and action:

**Users**: create_user, read_users, update_user, delete_user
**Organizations**: create_organization, read_organizations, update_organization, delete_organization
**Members**: create_member, read_members, update_member, delete_member
**Logistics**: create_logistics, read_logistics, update_logistics, delete_logistics
**Requests**: create_request, read_requests, approve_request, delete_request
**Audit Logs**: read_audit_logs

## Database Structure

### Key Tables

- **users** - User accounts
- **roles** - System roles
- **permissions** - System permissions
- **user_roles** - Maps users to roles (many-to-many)
- **role_permissions** - Maps roles to permissions (many-to-many)
- **audit_logs** - Activity logging

## Security Features

1. **Authentication Required** - All endpoints require valid JWT token
2. **Super Admin Only** - Admin endpoints only accessible to `super.admin@ordinatrack.com`
3. **Authorization Middleware** - Comprehensive permission checking
4. **Audit Logging** - All admin actions logged with user and IP
5. **Soft Deletes** - Users are soft-deleted, not permanently removed
6. **Password Hashing** - Bcrypt with cost=12
7. **Session Security** - HTTPS-only, httponly, sameSite cookies
8. **SQL Injection Prevention** - Parameterized queries throughout
9. **XSS Prevention** - HTML escaping in frontend

## Testing

See `SUPER_ADMIN_TESTING.md` for comprehensive testing guide including:
- API endpoint testing
- Authorization testing
- Frontend testing
- Integration testing
- Error handling
- Security testing

## Common Tasks

### Create a New Role with Permissions

1. Go to Roles section in dashboard
2. Click "+ Create Role"
3. Fill in:
   - Role Name: e.g., "Auditor"
   - Slug: e.g., "auditor"
   - Description: e.g., "Read-only access for auditing"
4. Select permissions from checklist
5. Click "Create Role"

### Assign Role to User

1. Go to Users section
2. Find user and click "Edit"
3. Check desired roles in the roles list
4. Click "Save Changes"

### Create Custom Permission

1. Go to Permissions section
2. Click "+ Create Permission"
3. Fill in:
   - Permission Name: e.g., "Export Reports"
   - Slug: e.g., "export_reports"
   - Resource: e.g., "reports"
   - Action: e.g., "export"
   - Description: e.g., "Export reports to CSV"
4. Click "Create Permission"

### Change User Status

1. Go to Users section
2. Find user and click "Edit"
3. Change status to Active/Inactive
4. Click "Save Changes"

## File Locations

```
/Applications/MAMP/htdocs/OrdinaTrack/
├── API/
│   ├── src/
│   │   ├── Model/SuperAdminModel.php
│   │   ├── Controller/SuperAdminController.php
│   │   ├── Middleware/SuperAdminMiddleware.php
│   │   ├── Routes/admin.route.php
│   │   └── Connections/schemas.php (updated)
│   ├── SUPER_ADMIN_TESTING.md
│   └── index.php (no changes needed)
└── App/
    ├── dashboard/admin/index.html
    └── Assets/js/super-admin.js
```

## Troubleshooting

### Super Admin Account Not Created
- Clear database and reinitialize
- Check PHP error logs
- Verify database connection

### Cannot Login
- Ensure email is exactly: `super.admin@ordinatrack.com`
- Password is: `SuperAdmin@123!`
- Check user is_active = 1

### Admin Dashboard Shows 403
- Verify token is valid
- Check that email in token matches super.admin@ordinatrack.com
- Clear browser cache and re-login

### API Returns "Route not found"
- Verify endpoint format is correct
- Check that admin routes are registered in index.php
- Ensure ALLOWED_ROUTES includes 'admin'

## Next Steps

1. **Customize Roles** - Create roles specific to your organization
2. **Customize Permissions** - Add permissions for your features
3. **Onboard Users** - Create user accounts and assign roles
4. **Monitor Activity** - Review audit logs regularly
5. **Maintain Security** - Keep super admin credentials secure

## Support

For issues or questions:
1. Check SUPER_ADMIN_TESTING.md for troubleshooting
2. Review error logs in PHP error log
3. Check browser console for JavaScript errors
4. Verify database connection and permissions
5. Check that all files were created correctly

## Version

- **Version**: 1.0.0
- **Created**: September 2026
- **Last Updated**: September 2026
- **Status**: Production Ready

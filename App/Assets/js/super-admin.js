/**
 * Super Admin Dashboard Module
 * 
 * Handles all super admin dashboard operations including:
 * - Role management
 * - Permission management
 * - User management
 * - System statistics
 * - Activity logging
 */

// ==================== Configuration ====================

const ADMIN_CONFIG = {
    API_BASE: window.location.origin + "/OrdinaTrack/API/index.php",
    TOKEN_KEY: "auth_token",
};

// Current data stores
let currentData = {
    users: [],
    roles: [],
    permissions: [],
    activity: [],
    stats: {}
};

let currentEditingUser = null;
let currentEditingRole = null;

// ==================== Initialization ====================

document.addEventListener('DOMContentLoaded', function () {
    initializeDashboard();
    loadDashboardData();
    bindSidebarEvents();
});

function initializeDashboard() {
    // Verify authentication
    const token = localStorage.getItem(ADMIN_CONFIG.TOKEN_KEY);
    if (!token) {
        window.location.href = '/OrdinaTrack/App/pages/signin.html';
        return;
    }

    // Bind modal close buttons
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
}

// ==================== Sidebar Navigation ====================

function bindSidebarEvents() {
    document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
        item.addEventListener('click', function () {
            const section = this.dataset.section;
            switchSection(section);
        });
    });

    // Logout button
    document.getElementById('logoutBtn').addEventListener('click', logout);
}

function switchSection(section) {
    // Hide all sections
    document.querySelectorAll('.section-content').forEach(s => s.classList.add('hidden'));

    // Show selected section
    const sectionEl = document.getElementById(`${section}-section`);
    if (sectionEl) {
        sectionEl.classList.remove('hidden');
    }

    // Update sidebar active state
    document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
        item.classList.toggle('active', item.dataset.section === section);
    });

    // Load section data
    switch (section) {
        case 'users':
            loadUsers();
            break;
        case 'roles':
            loadRoles();
            break;
        case 'permissions':
            loadPermissions();
            break;
        case 'activity':
            loadActivityLogs();
            break;
    }
}

// ==================== Dashboard Data Loading ====================

async function loadDashboardData() {
    try {
        // Load statistics
        const statsResponse = await apiCall('/admin/stats', 'GET');
        if (statsResponse.success) {
            currentData.stats = statsResponse.data;
            displayStats();
        }

        // Load recent activity
        const activityResponse = await apiCall('/admin/activity?limit=5', 'GET');
        if (activityResponse.success) {
            currentData.activity = activityResponse.data;
            displayRecentActivity();
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

function displayStats() {
    const stats = currentData.stats;
    document.getElementById('stat-total-users').textContent = stats.total_users || 0;
    document.getElementById('stat-active-users').textContent = stats.active_users || 0;
    document.getElementById('stat-total-roles').textContent = stats.total_roles || 0;
    document.getElementById('stat-total-permissions').textContent = stats.total_permissions || 0;
}

function displayRecentActivity() {
    const container = document.getElementById('activity-container');
    
    if (currentData.activity.length === 0) {
        container.innerHTML = '<div class="py-8 text-center text-gray-500">No recent activity</div>';
        return;
    }

    const html = `
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">User</th>
                        <th class="text-left">Action</th>
                        <th class="text-left">Resource</th>
                        <th class="text-left">Time</th>
                    </tr>
                </thead>
                <tbody>
                    ${currentData.activity.map(activity => `
                        <tr>
                            <td>${escapeHtml(activity.first_name || activity.email)} ${activity.last_name || ''}</td>
                            <td><span class="badge badge-info">${escapeHtml(activity.action)}</span></td>
                            <td>${escapeHtml(activity.resource_type)}</td>
                            <td class="text-sm text-gray-500">${formatDate(activity.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

// ==================== Users Management ====================

async function loadUsers() {
    const container = document.getElementById('users-table-container');
    container.innerHTML = '<div class="p-8 text-center"><div class="loading mx-auto"></div></div>';

    try {
        const response = await apiCall('/admin/users', 'GET');
        
        if (!response.success) {
            container.innerHTML = '<div class="p-8 text-center text-red-600">Failed to load users</div>';
            return;
        }

        currentData.users = response.data;
        displayUsersTable();
    } catch (error) {
        console.error('Error loading users:', error);
        container.innerHTML = '<div class="p-8 text-center text-red-600">Error loading users</div>';
    }
}

function displayUsersTable() {
    const container = document.getElementById('users-table-container');
    
    if (currentData.users.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No users found</div>';
        return;
    }

    const html = `
        <table class="w-full">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Roles</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${currentData.users.map(user => `
                    <tr>
                        <td>${escapeHtml(user.email)}</td>
                        <td>${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</td>
                        <td>
                            <span class="badge ${user.is_active ? 'badge-success' : 'badge-danger'}">
                                ${user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="text-sm">
                            ${user.roles && user.roles.length > 0 
                                ? user.roles.map(r => `<span class="badge badge-info text-xs">${escapeHtml(r.name)}</span>`).join(' ')
                                : '<span class="text-gray-400">No roles</span>'
                            }
                        </td>
                        <td class="text-sm text-gray-500">${formatDate(user.created_at)}</td>
                        <td class="text-sm">
                            <button class="btn-primary text-xs" onclick="editUser(${user.id})">Edit</button>
                            ${user.email !== 'super.admin@ordinatrack.com' 
                                ? `<button class="btn-danger text-xs ml-2" onclick="deleteUser(${user.id})">Delete</button>`
                                : ''
                            }
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

async function editUser(userId) {
    try {
        const response = await apiCall(`/admin/users/${userId}`, 'GET');
        
        if (!response.success) {
            alert('Failed to load user');
            return;
        }

        currentEditingUser = response.data;
        populateUserModal(response.data);
        document.getElementById('user-modal').classList.add('active');

        // Load roles for selection
        const rolesResponse = await apiCall('/admin/roles', 'GET');
        if (rolesResponse.success) {
            displayRolesInUserModal(rolesResponse.data, currentEditingUser.roles);
        }
    } catch (error) {
        console.error('Error editing user:', error);
        alert('Error loading user');
    }
}

function populateUserModal(user) {
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-name').value = `${user.first_name} ${user.last_name}`;
    document.getElementById('user-status').value = user.is_active ? '1' : '0';
}

function displayRolesInUserModal(allRoles, userRoles) {
    const container = document.getElementById('user-roles-container');
    const userRoleIds = userRoles.map(r => r.id);

    const html = allRoles.map(role => `
        <label class="flex items-center">
            <input type="checkbox" value="${role.id}" ${userRoleIds.includes(role.id) ? 'checked' : ''} 
                   class="user-role-checkbox w-4 h-4">
            <span class="ml-2">${escapeHtml(role.name)}</span>
        </label>
    `).join('');

    container.innerHTML = html;
}

function openUserModal() {
    currentEditingUser = null;
    document.getElementById('user-modal').classList.add('active');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.remove('active');
}

// ==================== Roles Management ====================

async function loadRoles() {
    const container = document.getElementById('roles-table-container');
    container.innerHTML = '<div class="p-8 text-center"><div class="loading mx-auto"></div></div>';

    try {
        const response = await apiCall('/admin/roles', 'GET');
        
        if (!response.success) {
            container.innerHTML = '<div class="p-8 text-center text-red-600">Failed to load roles</div>';
            return;
        }

        currentData.roles = response.data;
        displayRolesTable();
    } catch (error) {
        console.error('Error loading roles:', error);
        container.innerHTML = '<div class="p-8 text-center text-red-600">Error loading roles</div>';
    }
}

function displayRolesTable() {
    const container = document.getElementById('roles-table-container');
    
    if (currentData.roles.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No roles found</div>';
        return;
    }

    const html = `
        <table class="w-full">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Permissions</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${currentData.roles.map(role => `
                    <tr>
                        <td class="font-medium">${escapeHtml(role.name)}</td>
                        <td><code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(role.slug)}</code></td>
                        <td class="text-sm text-gray-600">${role.permission_count || 0} permissions</td>
                        <td class="text-sm text-gray-600">${escapeHtml(role.description || '')}</td>
                        <td class="text-sm">
                            <button class="btn-primary text-xs" onclick="editRole(${role.id})">Edit</button>
                            ${!['admin', 'nhq_admin'].includes(role.slug)
                                ? `<button class="btn-danger text-xs ml-2" onclick="deleteRole(${role.id})">Delete</button>`
                                : ''
                            }
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

async function editRole(roleId) {
    try {
        const response = await apiCall(`/admin/roles/${roleId}`, 'GET');
        
        if (!response.success) {
            alert('Failed to load role');
            return;
        }

        currentEditingRole = response.data;
        document.getElementById('role-name').value = response.data.name;
        document.getElementById('role-slug').value = response.data.slug;
        document.getElementById('role-description').value = response.data.description || '';

        // Load permissions
        const permsResponse = await apiCall('/admin/permissions', 'GET');
        if (permsResponse.success) {
            displayPermissionsInRoleModal(permsResponse.data, response.data.permissions);
        }

        document.getElementById('role-modal').classList.add('active');
    } catch (error) {
        console.error('Error editing role:', error);
        alert('Error loading role');
    }
}

function displayPermissionsInRoleModal(allPermissions, rolePermissions) {
    const container = document.getElementById('role-permissions-container');
    const rolePermIds = rolePermissions.map(p => p.id);

    const html = allPermissions.map(perm => `
        <label class="flex items-center">
            <input type="checkbox" value="${perm.id}" ${rolePermIds.includes(perm.id) ? 'checked' : ''} 
                   class="role-permission-checkbox w-4 h-4">
            <span class="ml-2 text-sm">${escapeHtml(perm.name)}</span>
        </label>
    `).join('');

    container.innerHTML = html;
}

function openRoleModal() {
    currentEditingRole = null;
    document.getElementById('role-name').value = '';
    document.getElementById('role-slug').value = '';
    document.getElementById('role-description').value = '';
    document.getElementById('role-permissions-container').innerHTML = '';
    document.getElementById('role-modal').classList.add('active');
}

function closeRoleModal() {
    document.getElementById('role-modal').classList.remove('active');
}

// ==================== Permissions Management ====================

async function loadPermissions() {
    const container = document.getElementById('permissions-table-container');
    container.innerHTML = '<div class="p-8 text-center"><div class="loading mx-auto"></div></div>';

    try {
        const response = await apiCall('/admin/permissions', 'GET');
        
        if (!response.success) {
            container.innerHTML = '<div class="p-8 text-center text-red-600">Failed to load permissions</div>';
            return;
        }

        currentData.permissions = response.data;
        displayPermissionsTable();
    } catch (error) {
        console.error('Error loading permissions:', error);
        container.innerHTML = '<div class="p-8 text-center text-red-600">Error loading permissions</div>';
    }
}

function displayPermissionsTable() {
    const container = document.getElementById('permissions-table-container');
    
    if (currentData.permissions.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No permissions found</div>';
        return;
    }

    const html = `
        <table class="w-full">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Resource</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${currentData.permissions.map(perm => `
                    <tr>
                        <td class="font-medium">${escapeHtml(perm.name)}</td>
                        <td><code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(perm.slug)}</code></td>
                        <td class="text-sm">${escapeHtml(perm.resource || '-')}</td>
                        <td class="text-sm"><span class="badge badge-info">${escapeHtml(perm.action || '-')}</span></td>
                        <td class="text-sm text-gray-600">${escapeHtml(perm.description || '')}</td>
                        <td class="text-sm">
                            <button class="btn-danger text-xs" onclick="deletePermission(${perm.id})">Delete</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

function openPermissionModal() {
    document.getElementById('permission-name').value = '';
    document.getElementById('permission-slug').value = '';
    document.getElementById('permission-resource').value = '';
    document.getElementById('permission-action').value = '';
    document.getElementById('permission-description').value = '';
    document.getElementById('permission-modal').classList.add('active');
}

function closePermissionModal() {
    document.getElementById('permission-modal').classList.remove('active');
}

// ==================== Activity Logs ====================

async function loadActivityLogs() {
    const container = document.getElementById('activity-log-container');
    container.innerHTML = '<div class="p-8 text-center"><div class="loading mx-auto"></div></div>';

    try {
        const response = await apiCall('/admin/activity?limit=50', 'GET');
        
        if (!response.success) {
            container.innerHTML = '<div class="p-8 text-center text-red-600">Failed to load activity logs</div>';
            return;
        }

        currentData.activity = response.data;
        displayActivityLogsTable();
    } catch (error) {
        console.error('Error loading activity logs:', error);
        container.innerHTML = '<div class="p-8 text-center text-red-600">Error loading activity logs</div>';
    }
}

function displayActivityLogsTable() {
    const container = document.getElementById('activity-log-container');
    
    if (currentData.activity.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No activity logs</div>';
        return;
    }

    const html = `
        <table class="w-full">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Resource</th>
                    <th>Resource ID</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                ${currentData.activity.map(log => `
                    <tr>
                        <td>${escapeHtml(log.first_name || log.email)} ${log.last_name || ''}</td>
                        <td><span class="badge badge-info">${escapeHtml(log.action)}</span></td>
                        <td>${escapeHtml(log.resource_type)}</td>
                        <td class="text-sm text-gray-600">${log.resource_id || '-'}</td>
                        <td class="text-sm text-gray-600">${escapeHtml(log.ip_address || '-')}</td>
                        <td class="text-sm text-gray-500">${formatDate(log.created_at)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

// ==================== Form Submissions ====================

document.addEventListener('DOMContentLoaded', function () {
    // User form
    const userForm = document.getElementById('user-form');
    if (userForm) {
        userForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!currentEditingUser) return;

            const isActive = document.getElementById('user-status').value === '1';
            const roleCheckboxes = document.querySelectorAll('.user-role-checkbox:checked');
            const roleIds = Array.from(roleCheckboxes).map(cb => parseInt(cb.value));

            try {
                // Update status
                await apiCall(`/admin/users/${currentEditingUser.id}/status`, 'PUT', {
                    is_active: isActive
                });

                // Assign roles
                if (roleIds.length > 0) {
                    await apiCall(`/admin/users/${currentEditingUser.id}/roles`, 'PUT', {
                        role_ids: roleIds
                    });
                }

                showAlert('User updated successfully', 'success');
                closeUserModal();
                loadUsers();
            } catch (error) {
                showAlert('Error updating user', 'danger');
            }
        });
    }

    // Role form
    const roleForm = document.getElementById('role-form');
    if (roleForm) {
        roleForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const name = document.getElementById('role-name').value.trim();
            const slug = document.getElementById('role-slug').value.trim();
            const description = document.getElementById('role-description').value.trim();
            const permCheckboxes = document.querySelectorAll('.role-permission-checkbox:checked');
            const permissionIds = Array.from(permCheckboxes).map(cb => parseInt(cb.value));

            if (!name || !slug) {
                showAlert('Please fill in all required fields', 'danger');
                return;
            }

            try {
                let roleId = currentEditingRole?.id;

                if (!roleId) {
                    // Create new role
                    const createResponse = await apiCall('/admin/roles', 'POST', {
                        name,
                        slug,
                        description
                    });
                    roleId = createResponse.data.id;
                } else {
                    // Update existing role
                    await apiCall(`/admin/roles/${roleId}`, 'PUT', {
                        name,
                        slug,
                        description
                    });
                }

                // Assign permissions
                if (permissionIds.length > 0) {
                    await apiCall(`/admin/roles/${roleId}/permissions`, 'POST', {
                        permission_ids: permissionIds
                    });
                }

                showAlert('Role saved successfully', 'success');
                closeRoleModal();
                loadRoles();
            } catch (error) {
                showAlert('Error saving role', 'danger');
            }
        });
    }

    // Permission form
    const permForm = document.getElementById('permission-form');
    if (permForm) {
        permForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const name = document.getElementById('permission-name').value.trim();
            const slug = document.getElementById('permission-slug').value.trim();
            const resource = document.getElementById('permission-resource').value.trim();
            const action = document.getElementById('permission-action').value.trim();
            const description = document.getElementById('permission-description').value.trim();

            if (!name || !slug) {
                showAlert('Please fill in all required fields', 'danger');
                return;
            }

            try {
                await apiCall('/admin/permissions', 'POST', {
                    name,
                    slug,
                    resource,
                    action,
                    description
                });

                showAlert('Permission created successfully', 'success');
                closePermissionModal();
                loadPermissions();
            } catch (error) {
                showAlert('Error creating permission', 'danger');
            }
        });
    }
});

// ==================== API Calls ====================

async function apiCall(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem(ADMIN_CONFIG.TOKEN_KEY);
    
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        }
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(ADMIN_CONFIG.API_BASE + endpoint, options);
    const data = await response.json();

    if (!data.success && data.statuscode === 99) {
        // Token expired
        localStorage.removeItem(ADMIN_CONFIG.TOKEN_KEY);
        window.location.href = '/OrdinaTrack/App/pages/signin.html';
    }

    return data;
}

// ==================== Delete Operations ====================

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;

    try {
        await apiCall(`/admin/users/${userId}`, 'DELETE');
        showAlert('User deleted successfully', 'success');
        loadUsers();
    } catch (error) {
        showAlert('Error deleting user', 'danger');
    }
}

async function deleteRole(roleId) {
    if (!confirm('Are you sure you want to delete this role?')) return;

    try {
        await apiCall(`/admin/roles/${roleId}`, 'DELETE');
        showAlert('Role deleted successfully', 'success');
        loadRoles();
    } catch (error) {
        showAlert('Error deleting role', 'danger');
    }
}

async function deletePermission(permId) {
    if (!confirm('Are you sure you want to delete this permission?')) return;

    try {
        await apiCall(`/admin/permissions/${permId}`, 'DELETE');
        showAlert('Permission deleted successfully', 'success');
        loadPermissions();
    } catch (error) {
        showAlert('Error deleting permission', 'danger');
    }
}

// ==================== Utility Functions ====================

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function showAlert(message, type = 'info') {
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${type} fixed top-4 right-4 max-w-xs z-50`;
    alertEl.textContent = message;
    document.body.appendChild(alertEl);

    setTimeout(() => {
        alertEl.remove();
    }, 3000);
}

function logout() {
    localStorage.removeItem(ADMIN_CONFIG.TOKEN_KEY);
    window.location.href = '/OrdinaTrack/App/pages/signin.html';
}

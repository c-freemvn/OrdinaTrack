/**
 * Super Admin Dashboard Module
 *
 * Handles all super admin dashboard operations including:
 * - Role management (create/edit/delete, grouped permission picker)
 * - Permission management
 * - User management (create/edit, roles, status, password reset, delete)
 * - System statistics
 * - Activity logging
 */

// ==================== Configuration ====================

const ADMIN_CONFIG = {
  API_BASE: window.location.origin + "/OrdinaTrack/API/index.php",
  TOKEN_KEY: "auth_token",
  USER_INFO_KEY: "user_info",
  SIGNIN_URL: "/OrdinaTrack/App/pages/signin.html",
  SYSTEM_ADMIN_EMAIL: "super.admin@ordinatrack.com",
  USERS_PAGE_SIZE: 20,
};

const state = {
  roles: [],
  permissions: [],
  users: [],
  usersTotal: 0,
  usersOffset: 0,
  userSearch: "",
  editingUser: null,
  editingRole: null,
  editingPermission: null,
  locType: "provinces",
  locFilters: { province_id: "", district_id: "" },
  locSearch: "",
  locations: [],
  allProvinces: [],
  allDistricts: [],
  editingLocation: null,
};

// ==================== Initialization ====================

document.addEventListener("DOMContentLoaded", function () {
  if (!localStorage.getItem(ADMIN_CONFIG.TOKEN_KEY)) {
    window.location.href = ADMIN_CONFIG.SIGNIN_URL;
    return;
  }

  showCurrentUser();
  bindEvents();
  loadDashboardData();
});

function currentUser() {
  try {
    return JSON.parse(
      localStorage.getItem(ADMIN_CONFIG.USER_INFO_KEY) || "null",
    );
  } catch (e) {
    return null;
  }
}

function showCurrentUser() {
  const user = currentUser();
  if (!user) return;
  document.getElementById("current-user-name").textContent = fullName(user);
  document.getElementById("current-user-email").textContent = user.email || "";
}

function bindEvents() {
  document.querySelectorAll(".sidebar-item[data-section]").forEach((item) => {
    item.addEventListener("click", () => switchSection(item.dataset.section));
  });
  document.getElementById("logoutBtn").addEventListener("click", logout);

  // Close modals on backdrop click or Escape (the access-denied screen stays)
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal && modal.id !== "access-denied")
        closeModal(modal.id);
    });
  });
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    document.querySelectorAll(".modal.active").forEach((modal) => {
      if (modal.id !== "access-denied") closeModal(modal.id);
    });
  });

  document
    .getElementById("user-form")
    .addEventListener("submit", submitUserForm);
  document
    .getElementById("role-form")
    .addEventListener("submit", submitRoleForm);
  document
    .getElementById("permission-form")
    .addEventListener("submit", submitPermissionForm);
  document
    .getElementById("role-permissions-container")
    .addEventListener("change", onRolePermissionChange);

  // Users: debounced search + pagination
  let searchTimer;
  document.getElementById("user-search").addEventListener("input", (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.userSearch = e.target.value.trim();
      state.usersOffset = 0;
      loadUsers();
    }, 300);
  });
  document.getElementById("users-prev").addEventListener("click", () => {
    state.usersOffset = Math.max(
      0,
      state.usersOffset - ADMIN_CONFIG.USERS_PAGE_SIZE,
    );
    loadUsers();
  });
  document.getElementById("users-next").addEventListener("click", () => {
    state.usersOffset += ADMIN_CONFIG.USERS_PAGE_SIZE;
    loadUsers();
  });

  // Locations: tabs, filters, debounced search, modal
  document.querySelectorAll(".loc-tab").forEach((tab) => {
    tab.addEventListener("click", () => {
      state.locType = tab.dataset.type;
      loadLocations();
    });
  });
  document
    .getElementById("loc-filter-province")
    .addEventListener("change", (e) => {
      state.locFilters = { province_id: e.target.value, district_id: "" };
      loadLocations();
    });
  document
    .getElementById("loc-filter-district")
    .addEventListener("change", (e) => {
      state.locFilters.district_id = e.target.value;
      loadLocations();
    });
  let locSearchTimer;
  document.getElementById("loc-search").addEventListener("input", (e) => {
    clearTimeout(locSearchTimer);
    locSearchTimer = setTimeout(() => {
      state.locSearch = e.target.value.trim();
      loadLocations();
    }, 300);
  });
  document
    .getElementById("location-add-btn")
    .addEventListener("click", () => openLocationModal());
  document
    .getElementById("location-form")
    .addEventListener("submit", submitLocationForm);
  document
    .getElementById("loc-province")
    .addEventListener("change", (e) => fillDistrictOptions(e.target.value));
}

// ==================== Sidebar Navigation ====================

function switchSection(section) {
  document
    .querySelectorAll(".section-content")
    .forEach((s) => s.classList.add("hidden"));
  document.getElementById(`${section}-section`)?.classList.remove("hidden");

  document.querySelectorAll(".sidebar-item[data-section]").forEach((item) => {
    item.classList.toggle("active", item.dataset.section === section);
  });

  switch (section) {
    case "dashboard":
      loadDashboardData();
      break;
    case "users":
      loadUsers();
      break;
    case "roles":
      loadRoles();
      break;
    case "permissions":
      loadPermissions();
      break;
    case "locations":
      loadLocations();
      break;
    case "activity":
      loadActivityLogs();
      break;
  }
}

// ==================== API Calls ====================

async function apiCall(endpoint, method = "GET", body = null) {
  const options = {
    method,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${localStorage.getItem(ADMIN_CONFIG.TOKEN_KEY)}`,
    },
  };
  if (body !== null) {
    options.body = JSON.stringify(body);
  }

  let data;
  try {
    const response = await fetch(ADMIN_CONFIG.API_BASE + endpoint, options);
    data = await response.json();
  } catch (error) {
    console.error("API error:", error);
    return {
      success: false,
      message: "Network error. Please check your connection.",
    };
  }

  // Token missing/expired, or the account was deactivated/deleted
  if (data.statuscode === 99) {
    logout();
  }
  // Signed in, but not a super admin
  if (data.statuscode === 403) {
    openModal("access-denied");
  }

  return data;
}

/**
 * Run a change request and report the outcome as a toast
 */
async function mutate(endpoint, method, body, successMessage) {
  const res = await apiCall(endpoint, method, body);
  if (res.success) {
    showToast(successMessage || res.message, "success");
  } else if (res.statuscode !== 99 && res.statuscode !== 403) {
    showToast(res.message || "Something went wrong", "danger");
  }
  return res;
}

// ==================== Dashboard ====================

async function loadDashboardData() {
  const [stats, activity] = await Promise.all([
    apiCall("/admin/stats"),
    apiCall("/admin/activity?limit=8"),
  ]);

  if (stats.success) {
    document.getElementById("stat-total-users").textContent =
      stats.data.total_users;
    document.getElementById("stat-active-users").textContent =
      stats.data.active_users;
    document.getElementById("stat-total-roles").textContent =
      stats.data.total_roles;
    document.getElementById("stat-total-permissions").textContent =
      stats.data.total_permissions;
  }

  renderActivity("activity-container", activity, false);
}

// ==================== Users Management ====================

async function loadUsers() {
  const container = document.getElementById("users-table-container");
  container.innerHTML = loadingHtml();

  const params = new URLSearchParams({
    limit: ADMIN_CONFIG.USERS_PAGE_SIZE,
    offset: state.usersOffset,
  });
  if (state.userSearch) params.set("search", state.userSearch);

  const res = await apiCall(`/admin/users?${params}`);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load users");
    return;
  }

  state.users = res.data;
  state.usersTotal = res.pagination.total;
  renderUsers();
}

function renderUsers() {
  const container = document.getElementById("users-table-container");
  const me = currentUser();
  const { users, usersOffset, usersTotal } = state;

  const from = usersTotal === 0 ? 0 : usersOffset + 1;
  document.getElementById("users-pagination-info").textContent =
    `Showing ${from}–${usersOffset + users.length} of ${usersTotal}`;
  document.getElementById("users-prev").disabled = usersOffset === 0;
  document.getElementById("users-next").disabled =
    usersOffset + users.length >= usersTotal;

  if (users.length === 0) {
    container.innerHTML = emptyHtml(
      state.userSearch ? "No users match your search" : "No users found",
    );
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>User</th><th>Roles</th><th>Status</th><th>Last login</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${users
          .map((user) => {
            // The backend refuses these too; hiding them just avoids dead-end clicks
            const locked =
              user.email === ADMIN_CONFIG.SYSTEM_ADMIN_EMAIL ||
              (me && me.id === user.id);
            return `
            <tr>
              <td>
                <div class="font-medium text-gray-900">${escapeHtml(fullName(user))}
                  ${user.is_super_admin ? '<span class="badge badge-warning ml-1">Super Admin</span>' : ""}
                </div>
                <div class="text-xs text-gray-500">${escapeHtml(user.email)}</div>
              </td>
              <td>
                ${
                  user.roles.length
                    ? user.roles
                        .map(
                          (r) =>
                            `<span class="badge badge-info mr-1 mb-1">${escapeHtml(r.name)}</span>`,
                        )
                        .join("")
                    : '<span class="text-gray-400 text-sm">No roles</span>'
                }
              </td>
              <td>
                <span class="badge ${user.is_active ? "badge-success" : "badge-danger"}">${user.is_active ? "Active" : "Inactive"}</span>
              </td>
              <td class="text-gray-500 whitespace-nowrap">${formatDate(user.last_login_at)}</td>
              <td class="text-gray-500 whitespace-nowrap">${formatDate(user.created_at)}</td>
              <td class="whitespace-nowrap">
                <button class="btn-link" onclick="openUserModal(${user.id})">Edit</button>
                ${
                  locked
                    ? ""
                    : `
                  <button class="btn-link ml-3" onclick="toggleUserStatus(${user.id}, ${!user.is_active})">${user.is_active ? "Deactivate" : "Activate"}</button>
                  <button class="btn-link danger ml-3" onclick="deleteUser(${user.id})">Delete</button>`
                }
              </td>
            </tr>`;
          })
          .join("")}
      </tbody>
    </table>`;
}

async function openUserModal(userId = null) {
  const isEdit = userId !== null;
  state.editingUser = null;
  document.getElementById("user-form").reset();
  document.getElementById("user-email").disabled = false;
  document.getElementById("user-modal-title").textContent = isEdit
    ? "Edit User"
    : "Add User";
  document.getElementById("user-submit-btn").textContent = isEdit
    ? "Save Changes"
    : "Create User";
  document.getElementById("user-password-label").textContent = isEdit
    ? "New password"
    : "Password *";
  document.getElementById("user-password-hint").textContent =
    (isEdit ? "Leave blank to keep the current password. " : "") +
    "At least 8 characters with uppercase, lowercase, number and special character.";

  const [rolesRes, userRes] = await Promise.all([
    apiCall("/admin/roles"),
    isEdit ? apiCall(`/admin/users/${userId}`) : Promise.resolve(null),
  ]);
  if (!rolesRes.success || (userRes && !userRes.success)) {
    showToast(
      (userRes && userRes.message) || rolesRes.message || "Failed to load user",
      "danger",
    );
    return;
  }
  state.roles = rolesRes.data;

  let userRoleIds = [];
  if (isEdit) {
    const user = userRes.data;
    state.editingUser = user;
    document.getElementById("user-first-name").value = user.first_name || "";
    document.getElementById("user-last-name").value = user.last_name || "";
    document.getElementById("user-email").value = user.email;
    document.getElementById("user-email").disabled =
      user.email === ADMIN_CONFIG.SYSTEM_ADMIN_EMAIL;
    document.getElementById("user-phone").value = user.phone || "";
    document.getElementById("user-status").value = user.is_active ? "1" : "0";
    userRoleIds = user.roles.map((r) => r.id);
  }

  document.getElementById("user-roles-container").innerHTML = state.roles
    .map(
      (role) => `
    <label class="flex items-start gap-2 text-sm cursor-pointer">
      <input type="checkbox" value="${role.id}" class="user-role-checkbox w-4 h-4 mt-0.5" ${userRoleIds.includes(role.id) ? "checked" : ""}>
      <span>${escapeHtml(role.name)} <span class="text-xs text-gray-400">${escapeHtml(role.slug)}</span></span>
    </label>`,
    )
    .join("");

  openModal("user-modal");
  document.getElementById("user-first-name").focus();
}

async function submitUserForm(e) {
  e.preventDefault();

  const profile = {
    first_name: document.getElementById("user-first-name").value.trim(),
    last_name: document.getElementById("user-last-name").value.trim(),
    email: document.getElementById("user-email").value.trim().toLowerCase(),
    phone: document.getElementById("user-phone").value.trim(),
  };
  const password = document.getElementById("user-password").value;
  const isActive = document.getElementById("user-status").value === "1";
  const roleIds = Array.from(
    document.querySelectorAll(".user-role-checkbox:checked"),
  ).map((cb) => parseInt(cb.value, 10));

  if (!profile.first_name || !profile.last_name || !profile.email) {
    showToast("First name, last name and email are required", "danger");
    return;
  }
  if (!state.editingUser && !password) {
    showToast("Password is required", "danger");
    return;
  }

  const btn = document.getElementById("user-submit-btn");
  setBusy(btn, true);
  try {
    if (!state.editingUser) {
      const res = await mutate(
        "/admin/users",
        "POST",
        { ...profile, password, is_active: isActive, role_ids: roleIds },
        "User created",
      );
      if (!res.success) return;
    } else {
      const id = state.editingUser.id;
      // Sequential so the first failure stops the rest and is reported
      const steps = [
        [`/admin/users/${id}`, profile],
        [`/admin/users/${id}/roles`, { role_ids: roleIds }],
        [`/admin/users/${id}/status`, { is_active: isActive }],
      ];
      if (password) steps.push([`/admin/users/${id}/password`, { password }]);

      for (const [endpoint, body] of steps) {
        const res = await apiCall(endpoint, "PUT", body);
        if (!res.success) {
          showToast(res.message || "Failed to save user", "danger");
          return;
        }
      }
      showToast("User updated", "success");
    }

    closeModal("user-modal");
    loadUsers();
  } finally {
    setBusy(btn, false);
  }
}

async function toggleUserStatus(userId, activate) {
  const user = state.users.find((u) => u.id === userId);
  if (
    !activate &&
    !confirm(
      `Deactivate ${fullName(user)}? They will be signed out and unable to log in.`,
    )
  )
    return;

  const res = await mutate(`/admin/users/${userId}/status`, "PUT", {
    is_active: activate,
  });
  if (res.success) loadUsers();
}

async function deleteUser(userId) {
  const user = state.users.find((u) => u.id === userId);
  if (
    !confirm(
      `Delete ${fullName(user)} (${user.email})? This cannot be undone from the dashboard.`,
    )
  )
    return;

  const res = await mutate(
    `/admin/users/${userId}`,
    "DELETE",
    null,
    "User deleted",
  );
  if (res.success) loadUsers();
}

// ==================== Roles Management ====================

async function loadRoles() {
  const container = document.getElementById("roles-table-container");
  container.innerHTML = loadingHtml();

  const res = await apiCall("/admin/roles");
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load roles");
    return;
  }

  state.roles = res.data;
  renderRoles();
}

function renderRoles() {
  const container = document.getElementById("roles-table-container");
  if (state.roles.length === 0) {
    container.innerHTML = emptyHtml("No roles found");
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>Role</th><th>Slug</th><th>Permissions</th><th>Users</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${state.roles
          .map(
            (role) => `
          <tr>
            <td>
              <div class="font-medium text-gray-900">${escapeHtml(role.name)}
                ${role.is_protected ? '<span class="badge badge-muted ml-1">System</span>' : ""}
              </div>
              <div class="text-xs text-gray-500">${escapeHtml(role.description || "")}</div>
            </td>
            <td><code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(role.slug)}</code></td>
            <td class="text-gray-600">${role.slug === "super_admin" ? "All (implicit)" : role.permission_count}</td>
            <td class="text-gray-600">${role.user_count}</td>
            <td class="whitespace-nowrap">
              <button class="btn-link" onclick="openRoleModal(${role.id})">Edit</button>
              ${role.is_protected ? "" : `<button class="btn-link danger ml-3" onclick="deleteRole(${role.id})">Delete</button>`}
            </td>
          </tr>`,
          )
          .join("")}
      </tbody>
    </table>`;
}

async function openRoleModal(roleId = null) {
  const isEdit = roleId !== null;
  state.editingRole = null;
  document.getElementById("role-form").reset();
  document.getElementById("role-slug").disabled = false;
  document.getElementById("role-permissions-note").classList.add("hidden");
  document.getElementById("role-modal-title").textContent = isEdit
    ? "Edit Role"
    : "Create Role";
  document.getElementById("role-submit-btn").textContent = isEdit
    ? "Save Changes"
    : "Create Role";

  const [permsRes, roleRes] = await Promise.all([
    apiCall("/admin/permissions"),
    isEdit ? apiCall(`/admin/roles/${roleId}`) : Promise.resolve(null),
  ]);
  if (!permsRes.success || (roleRes && !roleRes.success)) {
    showToast(
      (roleRes && roleRes.message) || permsRes.message || "Failed to load role",
      "danger",
    );
    return;
  }
  state.permissions = permsRes.data;

  let selected = [];
  if (isEdit) {
    const role = roleRes.data;
    state.editingRole = role;
    document.getElementById("role-name").value = role.name;
    document.getElementById("role-slug").value = role.slug;
    document.getElementById("role-slug").disabled = role.is_protected;
    document.getElementById("role-description").value = role.description || "";
    document
      .getElementById("role-permissions-note")
      .classList.toggle("hidden", role.slug !== "super_admin");
    selected = role.permissions.map((p) => p.id);
  }

  renderPermissionPicker(state.permissions, selected);
  openModal("role-modal");
  document.getElementById("role-name").focus();
}

function renderPermissionPicker(permissions, selectedIds) {
  const groups = {};
  permissions.forEach((p) => {
    (groups[p.resource || "other"] ||= []).push(p);
  });

  const container = document.getElementById("role-permissions-container");
  container.innerHTML =
    Object.entries(groups)
      .map(
        ([resource, perms]) => `
    <div class="perm-group">
      <label class="flex items-center gap-2 font-semibold text-sm text-gray-800 capitalize mb-2 cursor-pointer">
        <input type="checkbox" class="perm-group-toggle w-4 h-4" data-group="${escapeHtml(resource)}">
        ${escapeHtml(resource.replace(/_/g, " "))}
      </label>
      <div class="space-y-1 pl-6">
        ${perms
          .map(
            (p) => `
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" class="role-permission-checkbox w-4 h-4" data-group="${escapeHtml(resource)}" value="${p.id}" ${selectedIds.includes(p.id) ? "checked" : ""}>
            <span>${escapeHtml(p.name)}</span>
          </label>`,
          )
          .join("")}
      </div>
    </div>`,
      )
      .join("") ||
    emptyHtml("No permissions yet. Create some in the Permissions section.");

  syncPermissionGroups();
}

function onRolePermissionChange(e) {
  const target = e.target;
  if (target.classList.contains("perm-group-toggle")) {
    document
      .querySelectorAll(
        `.role-permission-checkbox[data-group="${CSS.escape(target.dataset.group)}"]`,
      )
      .forEach((cb) => (cb.checked = target.checked));
  }
  syncPermissionGroups();
}

/**
 * Keep each group's "select all" box and the selected counter in step with the checkboxes
 */
function syncPermissionGroups() {
  document.querySelectorAll(".perm-group-toggle").forEach((toggle) => {
    const boxes = Array.from(
      document.querySelectorAll(
        `.role-permission-checkbox[data-group="${CSS.escape(toggle.dataset.group)}"]`,
      ),
    );
    const checked = boxes.filter((cb) => cb.checked).length;
    toggle.checked = checked === boxes.length && checked > 0;
    toggle.indeterminate = checked > 0 && checked < boxes.length;
  });

  const all = document.querySelectorAll(".role-permission-checkbox").length;
  const checked = document.querySelectorAll(
    ".role-permission-checkbox:checked",
  ).length;
  document.getElementById("role-permissions-count").textContent = all
    ? `${checked} of ${all} selected`
    : "";
}

async function submitRoleForm(e) {
  e.preventDefault();

  const body = {
    name: document.getElementById("role-name").value.trim(),
    description: document.getElementById("role-description").value.trim(),
    permission_ids: Array.from(
      document.querySelectorAll(".role-permission-checkbox:checked"),
    ).map((cb) => parseInt(cb.value, 10)),
  };
  const slug = document.getElementById("role-slug").value.trim();
  if (slug) body.slug = slug;

  if (!body.name) {
    showToast("Role name is required", "danger");
    return;
  }

  const btn = document.getElementById("role-submit-btn");
  setBusy(btn, true);
  try {
    const res = state.editingRole
      ? await mutate(
          `/admin/roles/${state.editingRole.id}`,
          "PUT",
          body,
          "Role updated",
        )
      : await mutate("/admin/roles", "POST", body, "Role created");
    if (!res.success) return;

    closeModal("role-modal");
    loadRoles();
  } finally {
    setBusy(btn, false);
  }
}

async function deleteRole(roleId) {
  const role = state.roles.find((r) => r.id === roleId);
  const usersNote = role.user_count
    ? ` ${role.user_count} user(s) will lose this role.`
    : "";
  if (!confirm(`Delete the "${role.name}" role?${usersNote}`)) return;

  const res = await mutate(
    `/admin/roles/${roleId}`,
    "DELETE",
    null,
    "Role deleted",
  );
  if (res.success) loadRoles();
}

// ==================== Permissions Management ====================

async function loadPermissions() {
  const container = document.getElementById("permissions-table-container");
  container.innerHTML = loadingHtml();

  const res = await apiCall("/admin/permissions");
  if (!res.success) {
    container.innerHTML = errorHtml(
      res.message || "Failed to load permissions",
    );
    return;
  }

  state.permissions = res.data;
  renderPermissions();
}

function renderPermissions() {
  const container = document.getElementById("permissions-table-container");
  if (state.permissions.length === 0) {
    container.innerHTML = emptyHtml("No permissions found");
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>Permission</th><th>Slug</th><th>Resource</th><th>Action</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${state.permissions
          .map(
            (perm) => `
          <tr>
            <td>
              <div class="font-medium text-gray-900">${escapeHtml(perm.name)}</div>
              <div class="text-xs text-gray-500">${escapeHtml(perm.description || "")}</div>
            </td>
            <td><code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(perm.slug)}</code></td>
            <td class="text-gray-600">${escapeHtml(perm.resource || "-")}</td>
            <td>${perm.action ? `<span class="badge badge-info">${escapeHtml(perm.action)}</span>` : "-"}</td>
            <td class="whitespace-nowrap">
              <button class="btn-link" onclick="openPermissionModal(${perm.id})">Edit</button>
              <button class="btn-link danger ml-3" onclick="deletePermission(${perm.id})">Delete</button>
            </td>
          </tr>`,
          )
          .join("")}
      </tbody>
    </table>`;
}

async function openPermissionModal(permId = null) {
  if (state.permissions.length === 0) {
    const res = await apiCall("/admin/permissions");
    if (res.success) state.permissions = res.data;
  }

  const perm =
    permId !== null ? state.permissions.find((p) => p.id === permId) : null;
  state.editingPermission = perm;
  document.getElementById("permission-form").reset();
  document.getElementById("permission-modal-title").textContent = perm
    ? "Edit Permission"
    : "Create Permission";
  document.getElementById("permission-submit-btn").textContent = perm
    ? "Save Changes"
    : "Create Permission";

  // Suggest existing resources so new permissions group with related ones
  const resources = [
    ...new Set(state.permissions.map((p) => p.resource).filter(Boolean)),
  ];
  document.getElementById("permission-resources").innerHTML = resources
    .map((r) => `<option value="${escapeHtml(r)}"></option>`)
    .join("");

  if (perm) {
    document.getElementById("permission-name").value = perm.name;
    document.getElementById("permission-slug").value = perm.slug;
    document.getElementById("permission-resource").value = perm.resource || "";
    document.getElementById("permission-action").value = perm.action || "";
    document.getElementById("permission-description").value =
      perm.description || "";
  }

  openModal("permission-modal");
  document.getElementById("permission-name").focus();
}

async function submitPermissionForm(e) {
  e.preventDefault();

  const body = {
    name: document.getElementById("permission-name").value.trim(),
    resource: document.getElementById("permission-resource").value.trim(),
    action: document.getElementById("permission-action").value.trim(),
    description: document.getElementById("permission-description").value.trim(),
  };
  const slug = document.getElementById("permission-slug").value.trim();
  if (slug) body.slug = slug;

  if (!body.name) {
    showToast("Permission name is required", "danger");
    return;
  }

  const btn = document.getElementById("permission-submit-btn");
  setBusy(btn, true);
  try {
    const res = state.editingPermission
      ? await mutate(
          `/admin/permissions/${state.editingPermission.id}`,
          "PUT",
          body,
          "Permission updated",
        )
      : await mutate("/admin/permissions", "POST", body, "Permission created");
    if (!res.success) return;

    closeModal("permission-modal");
    loadPermissions();
  } finally {
    setBusy(btn, false);
  }
}

async function deletePermission(permId) {
  const perm = state.permissions.find((p) => p.id === permId);
  if (
    !confirm(
      `Delete the "${perm.name}" permission? Roles that have it will lose it.`,
    )
  )
    return;

  const res = await mutate(
    `/admin/permissions/${permId}`,
    "DELETE",
    null,
    "Permission deleted",
  );
  if (res.success) loadPermissions();
}

// ==================== Locations (provinces / districts / branches) ====================

const LOCATION_LABELS = {
  provinces: "Province",
  districts: "District",
  branches: "Branch",
};

/**
 * Jump to a locations tab, optionally filtered (used by the child-count links)
 */
function showLocations(type, filters = {}) {
  state.locType = type;
  state.locFilters = {
    province_id: filters.province_id || "",
    district_id: filters.district_id || "",
  };
  state.locSearch = "";
  document.getElementById("loc-search").value = "";
  switchSection("locations");
}

async function loadLocations() {
  const container = document.getElementById("locations-table-container");
  container.innerHTML = loadingHtml();
  const type = state.locType;

  const params = new URLSearchParams();
  if (state.locSearch) params.set("search", state.locSearch);
  if (type !== "provinces" && state.locFilters.province_id)
    params.set("province_id", state.locFilters.province_id);
  if (type === "branches" && state.locFilters.district_id)
    params.set("district_id", state.locFilters.district_id);

  // Full province/district lists feed the filters, tab counts and parent pickers
  const [provinces, districts, list] = await Promise.all([
    apiCall("/admin/provinces"),
    apiCall("/admin/districts"),
    apiCall(`/admin/${type}?${params}`),
  ]);
  if (!provinces.success || !districts.success || !list.success) {
    container.innerHTML = errorHtml(
      list.message ||
        provinces.message ||
        districts.message ||
        "Failed to load locations",
    );
    return;
  }

  state.allProvinces = provinces.data;
  state.allDistricts = districts.data;
  state.locations = list.data;
  renderLocationControls();
  renderLocations();
}

function renderLocationControls() {
  const type = state.locType;
  document
    .querySelectorAll(".loc-tab")
    .forEach((tab) =>
      tab.classList.toggle("active", tab.dataset.type === type),
    );
  document.getElementById("location-add-btn").textContent =
    `+ Add ${LOCATION_LABELS[type]}`;

  // Every province row carries its district and branch totals
  const total = (key) =>
    state.allProvinces.reduce((sum, p) => sum + Number(p[key] || 0), 0);
  document.getElementById("loc-count-provinces").textContent =
    state.allProvinces.length;
  document.getElementById("loc-count-districts").textContent =
    total("district_count");
  document.getElementById("loc-count-branches").textContent =
    total("branch_count");

  const provinceFilter = document.getElementById("loc-filter-province");
  provinceFilter.classList.toggle("hidden", type === "provinces");
  provinceFilter.innerHTML =
    '<option value="">All provinces</option>' +
    optionsHtml(state.allProvinces, state.locFilters.province_id);

  const districtFilter = document.getElementById("loc-filter-district");
  districtFilter.classList.toggle("hidden", type !== "branches");
  districtFilter.innerHTML =
    '<option value="">All districts</option>' +
    optionsHtml(
      districtsOf(state.locFilters.province_id, true),
      state.locFilters.district_id,
    );
}

function districtsOf(provinceId, allWhenEmpty = false) {
  if (!provinceId) return allWhenEmpty ? state.allDistricts : [];
  return state.allDistricts.filter(
    (d) => String(d.province_id) === String(provinceId),
  );
}

function optionsHtml(items, selectedId) {
  return items
    .map(
      (item) => `
    <option value="${item.id}" ${String(item.id) === String(selectedId) ? "selected" : ""}>
      ${escapeHtml(item.name)}${item.is_active ? "" : " (inactive)"}
    </option>`,
    )
    .join("");
}

function renderLocations() {
  const container = document.getElementById("locations-table-container");
  const type = state.locType;

  if (state.locations.length === 0) {
    const filtered =
      state.locSearch ||
      (type !== "provinces" &&
        (state.locFilters.province_id || state.locFilters.district_id));
    container.innerHTML = emptyHtml(
      filtered
        ? "Nothing matches these filters"
        : `No ${type} yet. Use "+ Add ${LOCATION_LABELS[type]}" to create one.`,
    );
    return;
  }

  const name = (item, sub) =>
    `<div class="font-medium text-gray-900">${escapeHtml(item.name)}</div>` +
    (sub ? `<div class="text-xs text-gray-500">${escapeHtml(sub)}</div>` : "");
  const code = (item) =>
    item.code
      ? `<code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(item.code)}</code>`
      : "-";
  const admin = (item) =>
    item.administrator_name
      ? escapeHtml(item.administrator_name)
      : '<span class="text-gray-400">-</span>';
  const status = (item) =>
    `<span class="badge ${item.is_active ? "badge-success" : "badge-danger"}">${item.is_active ? "Active" : "Inactive"}</span>`;
  // Child counts open the next tab filtered to this location (filters are numeric ids only)
  const drill = (count, target, filters) =>
    `<button class="btn-link" onclick='showLocations("${target}", ${JSON.stringify(filters)})'>${count}</button>`;

  const table = {
    provinces: {
      head: [
        "Province",
        "Code",
        "Districts",
        "Branches",
        "Administrator",
        "Status",
      ],
      cells: (p) => [
        name(p, p.description),
        code(p),
        drill(p.district_count, "districts", { province_id: p.id }),
        drill(p.branch_count, "branches", { province_id: p.id }),
        admin(p),
        status(p),
      ],
    },
    districts: {
      head: [
        "District",
        "Province",
        "Code",
        "Branches",
        "Administrator",
        "Status",
      ],
      cells: (d) => [
        name(d, d.description),
        escapeHtml(d.province_name),
        code(d),
        drill(d.branch_count, "branches", {
          province_id: d.province_id,
          district_id: d.id,
        }),
        admin(d),
        status(d),
      ],
    },
    branches: {
      head: [
        "Branch",
        "District / Province",
        "Code",
        "Contact",
        "Administrator",
        "Status",
      ],
      cells: (b) => [
        name(b, b.address),
        `${escapeHtml(b.district_name)}<div class="text-xs text-gray-500">${escapeHtml(b.province_name)}</div>`,
        code(b),
        [b.contact_person, b.contact_phone, b.contact_email]
          .filter(Boolean)
          .map(escapeHtml)
          .join("<br>") || "-",
        admin(b),
        status(b),
      ],
    },
  }[type];

  container.innerHTML = `
    <table>
      <thead><tr>${table.head.map((h) => `<th>${h}</th>`).join("")}<th>Actions</th></tr></thead>
      <tbody>
        ${state.locations
          .map(
            (item) => `
          <tr>
            ${table
              .cells(item)
              .map((cell) => `<td>${cell}</td>`)
              .join("")}
            <td class="whitespace-nowrap">
              <button class="btn-link" onclick="openLocationModal(${item.id})">Edit</button>
              <button class="btn-link danger ml-3" onclick="deleteLocation(${item.id})">Delete</button>
            </td>
          </tr>`,
          )
          .join("")}
      </tbody>
    </table>`;
}

async function openLocationModal(id = null) {
  const type = state.locType;
  const label = LOCATION_LABELS[type];
  const item = id !== null ? state.locations.find((l) => l.id === id) : null;
  state.editingLocation = item;

  document.getElementById("location-form").reset();
  document.getElementById("location-modal-title").textContent = item
    ? `Edit ${label}`
    : `Add ${label}`;
  document.getElementById("location-submit-btn").textContent = item
    ? "Save Changes"
    : `Create ${label}`;
  document
    .getElementById("loc-province-field")
    .classList.toggle("hidden", type === "provinces");
  document
    .getElementById("loc-district-field")
    .classList.toggle("hidden", type !== "branches");
  document
    .getElementById("loc-branch-fields")
    .classList.toggle("hidden", type !== "branches");

  // Administrator picker; keep the current administrator even if outside the first page of users
  const usersRes = await apiCall("/admin/users?limit=200");
  const users = usersRes.success ? usersRes.data : [];
  if (
    item &&
    item.administrator_id &&
    !users.some((u) => u.id === item.administrator_id)
  ) {
    users.unshift({
      id: item.administrator_id,
      first_name: item.administrator_name,
      last_name: "",
      email: "current",
    });
  }
  document.getElementById("loc-administrator").innerHTML =
    '<option value="">None</option>' +
    users
      .map(
        (u) =>
          `<option value="${u.id}">${escapeHtml(fullName(u))} (${escapeHtml(u.email)})</option>`,
      )
      .join("");

  // Parent pickers: the location's own parents when editing, the active filters when adding
  const provinceId = item ? item.province_id : state.locFilters.province_id;
  document.getElementById("loc-province").innerHTML =
    '<option value="">Select province...</option>' +
    optionsHtml(state.allProvinces, provinceId);
  fillDistrictOptions(
    provinceId,
    item ? item.district_id : state.locFilters.district_id,
  );

  if (item) {
    const set = (fieldId, value) =>
      (document.getElementById(fieldId).value = value ?? "");
    set("loc-name", item.name);
    set("loc-code", item.code);
    set("loc-description", item.description);
    set("loc-administrator", item.administrator_id);
    set("loc-status", item.is_active ? "1" : "0");
    if (type === "branches") {
      set("loc-address", item.address);
      set("loc-contact-person", item.contact_person);
      set("loc-contact-phone", item.contact_phone);
      set("loc-contact-email", item.contact_email);
    }
  }

  openModal("location-modal");
  document.getElementById("loc-name").focus();
}

function fillDistrictOptions(provinceId, selectedId = "") {
  document.getElementById("loc-district").innerHTML =
    `<option value="">${provinceId ? "Select district..." : "Select a province first"}</option>` +
    optionsHtml(districtsOf(provinceId), selectedId);
}

async function submitLocationForm(e) {
  e.preventDefault();

  const type = state.locType;
  const value = (fieldId) => document.getElementById(fieldId).value.trim();
  const body = {
    name: value("loc-name"),
    code: value("loc-code"),
    description: value("loc-description"),
    administrator_id: value("loc-administrator") || null,
    is_active: value("loc-status") === "1",
  };
  if (type === "districts") {
    body.province_id = value("loc-province") || null;
  }
  if (type === "branches") {
    Object.assign(body, {
      district_id: value("loc-district") || null,
      address: value("loc-address"),
      contact_person: value("loc-contact-person"),
      contact_phone: value("loc-contact-phone"),
      contact_email: value("loc-contact-email"),
    });
  }

  if (!body.name) return showToast("Name is required", "danger");
  if (type === "districts" && !body.province_id)
    return showToast("Select a province", "danger");
  if (type === "branches" && !body.district_id)
    return showToast("Select a district", "danger");

  const label = LOCATION_LABELS[type];
  const btn = document.getElementById("location-submit-btn");
  setBusy(btn, true);
  try {
    const res = state.editingLocation
      ? await mutate(
          `/admin/${type}/${state.editingLocation.id}`,
          "PUT",
          body,
          `${label} updated`,
        )
      : await mutate(`/admin/${type}`, "POST", body, `${label} created`);
    if (!res.success) return;

    closeModal("location-modal");
    loadLocations();
  } finally {
    setBusy(btn, false);
  }
}

async function deleteLocation(id) {
  const type = state.locType;
  const label = LOCATION_LABELS[type];
  const item = state.locations.find((l) => l.id === id);
  if (!confirm(`Delete ${label.toLowerCase()} "${item.name}"?`)) return;

  const res = await mutate(
    `/admin/${type}/${id}`,
    "DELETE",
    null,
    `${label} deleted`,
  );
  if (res.success) loadLocations();
}

// ==================== Activity Logs ====================

async function loadActivityLogs() {
  document.getElementById("activity-log-container").innerHTML = loadingHtml();
  renderActivity(
    "activity-log-container",
    await apiCall("/admin/activity?limit=100"),
    true,
  );
}

function renderActivity(containerId, res, detailed) {
  const container = document.getElementById(containerId);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load activity");
    return;
  }
  if (res.data.length === 0) {
    container.innerHTML = emptyHtml("No activity yet");
    return;
  }

  const badge = {
    CREATE: "badge-success",
    UPDATE: "badge-info",
    DELETE: "badge-danger",
  };
  container.innerHTML = `
    <div class="overflow-x-auto">
      <table>
        <thead>
          <tr><th>User</th><th>Action</th><th>Resource</th>${detailed ? "<th>Details</th><th>IP Address</th>" : ""}<th>Time</th></tr>
        </thead>
        <tbody>
          ${res.data
            .map(
              (log) => `
            <tr>
              <td>${escapeHtml(fullName(log) || log.email || "System")}</td>
              <td><span class="badge ${badge[log.action] || "badge-muted"}">${escapeHtml(log.action)}</span></td>
              <td class="whitespace-nowrap">${escapeHtml(log.resource_type)}${log.resource_id ? ` #${log.resource_id}` : ""}</td>
              ${
                detailed
                  ? `
                <td class="text-xs text-gray-600">${escapeHtml(summarizeChanges(log.new_values))}</td>
                <td class="text-gray-500">${escapeHtml(log.ip_address || "-")}</td>`
                  : ""
              }
              <td class="text-gray-500 whitespace-nowrap">${formatDate(log.created_at)}</td>
            </tr>`,
            )
            .join("")}
        </tbody>
      </table>
    </div>`;
}

/**
 * '{"name":"Auditor","permission_ids":[1,2]}' -> 'name: Auditor, permission_ids: [1, 2]'
 */
function summarizeChanges(json) {
  if (!json) return "-";
  try {
    const text = Object.entries(JSON.parse(json))
      .map(
        ([key, value]) =>
          `${key}: ${Array.isArray(value) ? `[${value.join(", ")}]` : value}`,
      )
      .join(", ");
    return text.length > 140 ? text.slice(0, 137) + "..." : text || "-";
  } catch (e) {
    return "-";
  }
}

// ==================== Utility Functions ====================

function openModal(id) {
  document.getElementById(id).classList.add("active");
}

function closeModal(id) {
  document.getElementById(id).classList.remove("active");
}

function setBusy(button, busy) {
  if (busy) {
    button.dataset.label = button.textContent;
    button.textContent = "Saving...";
  } else if (button.dataset.label) {
    button.textContent = button.dataset.label;
  }
  button.disabled = busy;
}

function fullName(person) {
  if (!person) return "";
  return `${person.first_name || ""} ${person.last_name || ""}`.trim();
}

function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return String(text ?? "").replace(/[&<>"']/g, (m) => map[m]);
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(String(dateString).replace(" ", "T"));
  if (isNaN(date)) return "-";
  return (
    date.toLocaleDateString() +
    " " +
    date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  );
}

function loadingHtml() {
  return '<div class="p-8 text-center"><div class="loading mx-auto"></div></div>';
}

function errorHtml(message) {
  return `<div class="p-8 text-center text-red-600">${escapeHtml(message)}</div>`;
}

function emptyHtml(message) {
  return `<div class="p-8 text-center text-gray-500">${escapeHtml(message)}</div>`;
}

function showToast(message, type = "success") {
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), type === "danger" ? 5000 : 3000);
}

function logout() {
  ["auth_token", "user_info", "refresh_token", "token_expiry"].forEach((key) =>
    localStorage.removeItem(key),
  );
  window.location.href = ADMIN_CONFIG.SIGNIN_URL;
}

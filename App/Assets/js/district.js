/**
 * District Dashboard Module
 *
 * Drives all four district pages against the /district API, which always resolves the
 * district the signed-in user administers:
 * - Overview: headline numbers and recent activity
 * - Branch Management: list, search, create, edit and delete the district's branches
 * - Members: every member across the district's branches (read-only; branch
 *   administrators add and edit their own)
 * - Activities: the audit trail for the district's administrators
 *
 * Each page sets data-page on <body>. Requires dashboard-common.js.
 */

const state = {
  district: null,
  branches: [],
  branchesTotal: 0,
  branchesOffset: 0,
  branchFilters: { search: "", status: "" },
  editingBranch: null,
  membersOffset: 0,
  memberFilters: { search: "", status: "", role: "", branch_id: "" },
  activity: [],
};

// ==================== Initialization ====================

document.addEventListener("DOMContentLoaded", init);

async function init() {
  if (!requireToken()) return;

  bindShell();
  bindPage();

  const res = await apiCall("/district/info");
  if (!res.success) {
    showUnavailable(res, "NO_DISTRICT", ["branches-table", "members-table", "activity-table", "activity-list"]);
    return;
  }

  state.district = res.data;
  showDistrict();
  loadPage();
}

function showDistrict() {
  const { name, province_name: province } = state.district;
  document.querySelectorAll("[data-district-name]").forEach((el) => (el.textContent = name));
  document.querySelectorAll("[data-province-name]").forEach((el) => (el.textContent = province));
  document.title = `${name} - OrdinaTrack`;
}

function bindPage() {
  switch (page()) {
    case "district-branches":
      bindBranchesPage();
      break;
    case "district-members":
      bindMembersPage();
      break;
    case "district-activities":
      document.getElementById("activity-filter")?.addEventListener("change", (e) => {
        renderActivityTable("activity-table", state.activity, e.target.value);
      });
      break;
  }
}

function loadPage() {
  switch (page()) {
    case "district-overview":
      renderStats();
      loadActivity(8);
      break;
    case "district-branches":
      loadBranches();
      break;
    case "district-members":
      loadBranchOptions();
      loadMembers();
      break;
    case "district-activities":
      loadActivity(100);
      break;
  }
}

// ==================== Overview ====================

function renderStats() {
  const { branches, members } = state.district.stats;

  setText("stat-branches", branches.total);
  setText("stat-branches-sub", branches.total === 0 ? "None yet" : `${branches.active} active`);
  setText("stat-members", members.total);
  setText("stat-members-sub", members.total === 0 ? "None yet" : "Across all branches");
  setText("stat-new-members", members.new_this_month);
  setText("stat-with-admin", `${branches.with_admin}/${branches.total}`);
  setText(
    "stat-with-admin-sub",
    branches.total && branches.with_admin < branches.total
      ? `${branches.total - branches.with_admin} still need one`
      : "All covered",
  );
}

// ==================== Branches ====================

function bindBranchesPage() {
  bindSearch("branch-search", (search) => {
    state.branchFilters.search = search;
    state.branchesOffset = 0;
    loadBranches();
  });
  document.getElementById("branch-status")?.addEventListener("change", (e) => {
    state.branchFilters.status = e.target.value;
    state.branchesOffset = 0;
    loadBranches();
  });
  document.getElementById("branches-prev")?.addEventListener("click", () => {
    state.branchesOffset = Math.max(0, state.branchesOffset - DASHBOARD_CONFIG.PAGE_SIZE);
    loadBranches();
  });
  document.getElementById("branches-next")?.addEventListener("click", () => {
    state.branchesOffset += DASHBOARD_CONFIG.PAGE_SIZE;
    loadBranches();
  });
  document.getElementById("add-branch-btn")?.addEventListener("click", () => openBranchModal());
  document.getElementById("branch-form")?.addEventListener("submit", submitBranchForm);
}

async function loadBranches() {
  const container = document.getElementById("branches-table");
  container.innerHTML = loadingHtml();

  const params = new URLSearchParams({ limit: DASHBOARD_CONFIG.PAGE_SIZE, offset: state.branchesOffset });
  if (state.branchFilters.search) params.set("search", state.branchFilters.search);
  if (state.branchFilters.status) params.set("status", state.branchFilters.status);

  const res = await apiCall(`/district/branches?${params}`);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load branches");
    return;
  }

  state.branches = res.data;
  state.branchesTotal = res.total;
  renderBranches();
}

function renderBranches() {
  const container = document.getElementById("branches-table");
  const { branches, branchesOffset: offset, branchesTotal: total } = state;
  renderPager("branches", offset, branches.length, total, "branches");

  if (branches.length === 0) {
    const filtered = state.branchFilters.search || state.branchFilters.status;
    container.innerHTML = emptyHtml(
      filtered ? "No branches match these filters" : 'No branches yet. Use "+ Add Branch" to create the first one.',
    );
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>Branch</th><th>Code</th><th>Contact</th><th>Members</th><th>Administrator</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${branches.map((branch) => `
          <tr>
            <td>
              <div class="font-medium text-gray-900">${escapeHtml(branch.name)}</div>
              ${branch.address ? `<div class="text-xs text-gray-500">${escapeHtml(branch.address)}</div>` : ""}
            </td>
            <td>${codeBadge(branch.code)}</td>
            <td class="text-sm">
              ${[branch.contact_person, branch.contact_phone, branch.contact_email].filter(Boolean).map(escapeHtml).join("<br>") || "-"}
            </td>
            <td>${branch.member_count}</td>
            <td>${branch.administrator_name ? escapeHtml(branch.administrator_name) : '<span class="text-gray-400">Not assigned</span>'}</td>
            <td>${statusBadge(branch.is_active)}</td>
            <td class="whitespace-nowrap">
              <button class="btn-link" onclick="openBranchModal(${branch.id})">Edit</button>
              <button class="btn-link danger ml-3" onclick="deleteBranch(${branch.id})">Delete</button>
            </td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

function openBranchModal(id = null) {
  const branch = id !== null ? state.branches.find((b) => b.id === id) : null;
  state.editingBranch = branch;

  document.getElementById("branch-form").reset();
  setText("branch-modal-title", branch ? "Edit Branch" : "Add Branch");
  setText("branch-submit-btn", branch ? "Save Changes" : "Create Branch");

  // Branch administrators are assigned by the super admin under Locations
  setText(
    "branch-admin-note",
    branch && branch.administrator_name
      ? `Administrator: ${branch.administrator_name}`
      : "An administrator is assigned by your super admin.",
  );

  if (branch) {
    setValue("branch-name", branch.name);
    setValue("branch-code", branch.code);
    setValue("branch-address", branch.address);
    setValue("branch-contact-person", branch.contact_person);
    setValue("branch-contact-phone", branch.contact_phone);
    setValue("branch-contact-email", branch.contact_email);
    setValue("branch-active", branch.is_active ? "1" : "0");
  }

  openModal("branch-modal");
  document.getElementById("branch-name").focus();
}

async function submitBranchForm(e) {
  e.preventDefault();

  const body = {
    name: value("branch-name"),
    code: value("branch-code"),
    address: value("branch-address"),
    contact_person: value("branch-contact-person"),
    contact_phone: value("branch-contact-phone"),
    contact_email: value("branch-contact-email"),
    is_active: value("branch-active") === "1",
  };

  if (!body.name) {
    showToast("Branch name is required", "danger");
    return;
  }

  const btn = document.getElementById("branch-submit-btn");
  setBusy(btn, true);
  try {
    const res = state.editingBranch
      ? await mutate(`/district/branches/${state.editingBranch.id}`, "PUT", body, "Branch updated")
      : await mutate("/district/branches", "POST", body, "Branch created");
    if (!res.success) return;

    closeModal("branch-modal");
    loadBranches();
  } finally {
    setBusy(btn, false);
  }
}

async function deleteBranch(id) {
  const branch = state.branches.find((b) => b.id === id);
  if (!confirm(`Delete "${branch.name}"?`)) return;

  const res = await mutate(`/district/branches/${id}`, "DELETE", null, "Branch deleted");
  if (res.success) loadBranches();
}

// ==================== Members (read-only) ====================

function bindMembersPage() {
  bindSearch("member-search", (search) => {
    state.memberFilters.search = search;
    state.membersOffset = 0;
    loadMembers();
  });

  [["member-branch", "branch_id"], ["member-role", "role"], ["member-status", "status"]].forEach(([id, key]) => {
    document.getElementById(id)?.addEventListener("change", (e) => {
      state.memberFilters[key] = e.target.value;
      state.membersOffset = 0;
      loadMembers();
    });
  });

  document.getElementById("members-prev")?.addEventListener("click", () => {
    state.membersOffset = Math.max(0, state.membersOffset - DASHBOARD_CONFIG.PAGE_SIZE);
    loadMembers();
  });
  document.getElementById("members-next")?.addEventListener("click", () => {
    state.membersOffset += DASHBOARD_CONFIG.PAGE_SIZE;
    loadMembers();
  });
}

async function loadBranchOptions() {
  const select = document.getElementById("member-branch");
  if (!select) return;

  const res = await apiCall("/district/branches?limit=100");
  if (!res.success) return;

  select.innerHTML = '<option value="">All branches</option>' +
    res.data.map((branch) => `<option value="${branch.id}">${escapeHtml(branch.name)}</option>`).join("");
}

async function loadMembers() {
  const container = document.getElementById("members-table");
  container.innerHTML = loadingHtml();

  const params = new URLSearchParams({ limit: DASHBOARD_CONFIG.PAGE_SIZE, offset: state.membersOffset });
  Object.entries(state.memberFilters).forEach(([key, val]) => {
    if (val) params.set(key, val);
  });

  const res = await apiCall(`/district/members?${params}`);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load members");
    return;
  }

  renderPager("members", state.membersOffset, res.data.length, res.total, "members");

  if (res.data.length === 0) {
    const filtered = Object.values(state.memberFilters).some(Boolean);
    container.innerHTML = emptyHtml(
      filtered ? "No members match these filters" : "No members yet. Branch administrators add members from their own dashboard.",
    );
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>Member</th><th>Branch</th><th>Role</th><th>Contact</th><th>Joined</th><th>Status</th></tr>
      </thead>
      <tbody>
        ${res.data.map((member) => `
          <tr>
            <td class="font-medium text-gray-900">${escapeHtml(fullName(member))}</td>
            <td>${escapeHtml(member.branch_name)}</td>
            <td><span class="badge badge-info">${escapeHtml(member.role)}</span></td>
            <td class="text-sm">${[member.email, member.phone].filter(Boolean).map(escapeHtml).join("<br>") || "-"}</td>
            <td class="text-gray-500 whitespace-nowrap">${formatDate(member.member_since)}</td>
            <td>${statusBadge(member.is_active)}</td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

// ==================== Activity ====================

async function loadActivity(limit) {
  const containerId = page() === "district-overview" ? "activity-list" : "activity-table";
  document.getElementById(containerId).innerHTML = loadingHtml();

  const res = await apiCall(`/district/activity?limit=${limit}`);
  if (!res.success) {
    document.getElementById(containerId).innerHTML = errorHtml(res.message || "Failed to load activity");
    return;
  }

  state.activity = res.data;
  if (page() === "district-overview") {
    renderActivityList(containerId, state.activity);
  } else {
    renderActivityTable(containerId, state.activity, value("activity-filter"));
  }
}

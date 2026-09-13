/**
 * Province Dashboard Module
 *
 * Drives all four province pages against the /province API, which always resolves the
 * province the signed-in user administers:
 * - Overview: headline numbers and recent activity
 * - District Management: list, search, create, edit and delete the province's districts,
 *   and look through each district's branches
 * - Members: every member across the province (read-only; branch administrators add and
 *   edit their own)
 * - Reports: leadership coverage, a per-district breakdown and the audit trail
 *
 * Branches are read-only here: district leads manage them from the district dashboard.
 * Each page sets data-page on <body>. Requires dashboard-common.js.
 */

const state = {
  province: null,
  districts: [],
  districtsTotal: 0,
  districtsOffset: 0,
  districtFilters: { search: "", status: "" },
  editingDistrict: null,
  membersOffset: 0,
  memberFilters: { search: "", status: "", role: "", district_id: "", branch_id: "" },
  activity: [],
};

// ==================== Initialization ====================

document.addEventListener("DOMContentLoaded", init);

async function init() {
  if (!requireToken()) return;

  bindShell();
  bindPage();

  const res = await apiCall("/province/info");
  if (!res.success) {
    showUnavailable(res, "NO_PROVINCE", ["districts-table", "members-table", "breakdown-table", "activity-table", "activity-list"]);
    return;
  }

  state.province = res.data;
  showProvince();
  loadPage();
}

function showProvince() {
  document.querySelectorAll("[data-province-name]").forEach((el) => (el.textContent = state.province.name));
  document.title = `${state.province.name} - OrdinaTrack`;
}

function bindPage() {
  switch (page()) {
    case "province-districts":
      bindDistrictsPage();
      break;
    case "province-members":
      bindMembersPage();
      break;
    case "province-reports":
      document.getElementById("activity-filter")?.addEventListener("change", (e) => {
        renderActivityTable("activity-table", state.activity, e.target.value);
      });
      break;
  }
}

function loadPage() {
  switch (page()) {
    case "province-overview":
      renderStats();
      loadActivity("activity-list", 8);
      break;
    case "province-districts":
      loadDistricts();
      break;
    case "province-members":
      loadDistrictOptions();
      loadBranchOptions("");
      loadMembers();
      break;
    case "province-reports":
      renderCoverage();
      loadBreakdown();
      loadActivity("activity-table", 100);
      break;
  }
}

// ==================== Overview ====================

function renderStats() {
  const { districts, branches, members } = state.province.stats;

  setText("stat-districts", districts.total);
  setText("stat-districts-sub", districts.total === 0 ? "None yet" : `${districts.active} active`);
  setText("stat-branches", branches.total);
  setText("stat-branches-sub", branches.total === 0 ? "None yet" : `${branches.active} active`);
  setText("stat-members", members.total);
  setText("stat-members-sub", members.total === 0 ? "None yet" : "Across all branches");
  setText("stat-new-members", members.new_this_month);
}

// ==================== Districts ====================

function bindDistrictsPage() {
  bindSearch("district-search", (search) => {
    state.districtFilters.search = search;
    state.districtsOffset = 0;
    loadDistricts();
  });
  document.getElementById("district-status")?.addEventListener("change", (e) => {
    state.districtFilters.status = e.target.value;
    state.districtsOffset = 0;
    loadDistricts();
  });
  document.getElementById("districts-prev")?.addEventListener("click", () => {
    state.districtsOffset = Math.max(0, state.districtsOffset - DASHBOARD_CONFIG.PAGE_SIZE);
    loadDistricts();
  });
  document.getElementById("districts-next")?.addEventListener("click", () => {
    state.districtsOffset += DASHBOARD_CONFIG.PAGE_SIZE;
    loadDistricts();
  });
  document.getElementById("add-district-btn")?.addEventListener("click", () => openDistrictModal());
  document.getElementById("district-form")?.addEventListener("submit", submitDistrictForm);
}

async function loadDistricts() {
  const container = document.getElementById("districts-table");
  container.innerHTML = loadingHtml();

  const params = new URLSearchParams({ limit: DASHBOARD_CONFIG.PAGE_SIZE, offset: state.districtsOffset });
  if (state.districtFilters.search) params.set("search", state.districtFilters.search);
  if (state.districtFilters.status) params.set("status", state.districtFilters.status);

  const res = await apiCall(`/province/districts?${params}`);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load districts");
    return;
  }

  state.districts = res.data;
  state.districtsTotal = res.total;
  renderDistricts();
}

function renderDistricts() {
  const container = document.getElementById("districts-table");
  const { districts, districtsOffset: offset, districtsTotal: total } = state;
  renderPager("districts", offset, districts.length, total, "districts");

  if (districts.length === 0) {
    const filtered = state.districtFilters.search || state.districtFilters.status;
    container.innerHTML = emptyHtml(
      filtered ? "No districts match these filters" : 'No districts yet. Use "+ Add District" to create the first one.',
    );
    return;
  }

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>District</th><th>Code</th><th>Branches</th><th>Members</th><th>District Lead</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${districts.map((district) => `
          <tr>
            <td>
              <div class="font-medium text-gray-900">${escapeHtml(district.name)}</div>
              ${district.description ? `<div class="text-xs text-gray-500">${escapeHtml(district.description)}</div>` : ""}
            </td>
            <td>${codeBadge(district.code)}</td>
            <td>
              ${district.branch_count
                ? `<button class="btn-link" onclick="openBranchesModal(${district.id})">${district.branch_count} ${district.branch_count === 1 ? "branch" : "branches"}</button>`
                : '<span class="text-gray-400">None</span>'}
            </td>
            <td>${district.member_count}</td>
            <td>${district.administrator_name ? escapeHtml(district.administrator_name) : '<span class="text-gray-400">Not assigned</span>'}</td>
            <td>${statusBadge(district.is_active)}</td>
            <td class="whitespace-nowrap">
              <button class="btn-link" onclick="openDistrictModal(${district.id})">Edit</button>
              <button class="btn-link danger ml-3" onclick="deleteDistrict(${district.id})">Delete</button>
            </td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

function openDistrictModal(id = null) {
  const district = id !== null ? state.districts.find((d) => d.id === id) : null;
  state.editingDistrict = district;

  document.getElementById("district-form").reset();
  setText("district-modal-title", district ? "Edit District" : "Add District");
  setText("district-submit-btn", district ? "Save Changes" : "Create District");

  // District leads are assigned by the super admin under Locations
  setText(
    "district-admin-note",
    district && district.administrator_name
      ? `District lead: ${district.administrator_name}`
      : "A district lead is assigned by your super admin.",
  );

  if (district) {
    setValue("district-name", district.name);
    setValue("district-code", district.code);
    setValue("district-description", district.description);
    setValue("district-active", district.is_active ? "1" : "0");
  }

  openModal("district-modal");
  document.getElementById("district-name").focus();
}

async function submitDistrictForm(e) {
  e.preventDefault();

  const body = {
    name: value("district-name"),
    code: value("district-code"),
    description: value("district-description"),
    is_active: value("district-active") === "1",
  };

  if (!body.name) {
    showToast("District name is required", "danger");
    return;
  }

  const btn = document.getElementById("district-submit-btn");
  setBusy(btn, true);
  try {
    const res = state.editingDistrict
      ? await mutate(`/province/districts/${state.editingDistrict.id}`, "PUT", body, "District updated")
      : await mutate("/province/districts", "POST", body, "District created");
    if (!res.success) return;

    closeModal("district-modal");
    loadDistricts();
  } finally {
    setBusy(btn, false);
  }
}

async function deleteDistrict(id) {
  const district = state.districts.find((d) => d.id === id);
  if (!confirm(`Delete "${district.name}"?`)) return;

  const res = await mutate(`/province/districts/${id}`, "DELETE", null, "District deleted");
  if (res.success) loadDistricts();
}

/**
 * Read-only list of one district's branches
 */
async function openBranchesModal(districtId) {
  const district = state.districts.find((d) => d.id === districtId);
  setText("branches-modal-title", `Branches in ${district ? district.name : "this district"}`);
  const list = document.getElementById("branches-modal-list");
  list.innerHTML = loadingHtml();
  openModal("branches-modal");

  const res = await apiCall(`/province/branches?district_id=${districtId}&limit=100`);
  if (!res.success) {
    list.innerHTML = errorHtml(res.message || "Failed to load branches");
    return;
  }
  if (res.data.length === 0) {
    list.innerHTML = emptyHtml("No branches in this district");
    return;
  }

  list.innerHTML = `
    <table>
      <thead><tr><th>Branch</th><th>Members</th><th>Administrator</th><th>Status</th></tr></thead>
      <tbody>
        ${res.data.map((branch) => `
          <tr>
            <td>
              <div class="font-medium text-gray-900">${escapeHtml(branch.name)}</div>
              ${branch.address ? `<div class="text-xs text-gray-500">${escapeHtml(branch.address)}</div>` : ""}
            </td>
            <td>${branch.member_count}</td>
            <td>${branch.administrator_name ? escapeHtml(branch.administrator_name) : '<span class="text-gray-400">Not assigned</span>'}</td>
            <td>${statusBadge(branch.is_active)}</td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

// ==================== Members (read-only) ====================

function bindMembersPage() {
  bindSearch("member-search", (search) => {
    state.memberFilters.search = search;
    state.membersOffset = 0;
    loadMembers();
  });

  // Choosing a district narrows the branch list to that district
  document.getElementById("member-district")?.addEventListener("change", (e) => {
    state.memberFilters.district_id = e.target.value;
    state.memberFilters.branch_id = "";
    state.membersOffset = 0;
    loadBranchOptions(e.target.value);
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

async function loadDistrictOptions() {
  const select = document.getElementById("member-district");
  if (!select) return;

  const res = await apiCall("/province/districts?limit=100");
  if (!res.success) return;

  select.innerHTML = '<option value="">All districts</option>' +
    res.data.map((district) => `<option value="${district.id}">${escapeHtml(district.name)}</option>`).join("");
}

async function loadBranchOptions(districtId) {
  const select = document.getElementById("member-branch");
  if (!select) return;

  const params = new URLSearchParams({ limit: 100 });
  if (districtId) params.set("district_id", districtId);

  const res = await apiCall(`/province/branches?${params}`);
  if (!res.success) return;

  select.innerHTML = '<option value="">All branches</option>' +
    res.data.map((branch) => `<option value="${branch.id}">${escapeHtml(branch.name)}${districtId ? "" : ` (${escapeHtml(branch.district_name)})`}</option>`).join("");
}

async function loadMembers() {
  const container = document.getElementById("members-table");
  container.innerHTML = loadingHtml();

  const params = new URLSearchParams({ limit: DASHBOARD_CONFIG.PAGE_SIZE, offset: state.membersOffset });
  Object.entries(state.memberFilters).forEach(([key, val]) => {
    if (val) params.set(key, val);
  });

  const res = await apiCall(`/province/members?${params}`);
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
        <tr><th>Member</th><th>Branch / District</th><th>Role</th><th>Contact</th><th>Joined</th><th>Status</th></tr>
      </thead>
      <tbody>
        ${res.data.map((member) => `
          <tr>
            <td class="font-medium text-gray-900">${escapeHtml(fullName(member))}</td>
            <td>
              ${escapeHtml(member.branch_name)}
              <div class="text-xs text-gray-500">${escapeHtml(member.district_name)}</div>
            </td>
            <td><span class="badge badge-info">${escapeHtml(member.role)}</span></td>
            <td class="text-sm">${[member.email, member.phone].filter(Boolean).map(escapeHtml).join("<br>") || "-"}</td>
            <td class="text-gray-500 whitespace-nowrap">${formatDate(member.member_since)}</td>
            <td>${statusBadge(member.is_active)}</td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

// ==================== Reports ====================

function renderCoverage() {
  const { districts, branches, members } = state.province.stats;
  const share = (part, whole) => (whole ? `${Math.round((part / whole) * 100)}%` : "-");

  setText("report-district-leads", `${districts.with_admin}/${districts.total}`);
  setText("report-district-leads-sub", districts.total ? `${share(districts.with_admin, districts.total)} of districts have a lead` : "No districts yet");
  setText("report-branch-admins", `${branches.with_admin}/${branches.total}`);
  setText("report-branch-admins-sub", branches.total ? `${share(branches.with_admin, branches.total)} of branches have an administrator` : "No branches yet");
  setText("report-new-members", members.new_this_month);
  setText("report-new-members-sub", `of ${members.total} members joined this month`);
}

async function loadBreakdown() {
  const container = document.getElementById("breakdown-table");
  container.innerHTML = loadingHtml();

  const res = await apiCall("/province/breakdown");
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load the breakdown");
    return;
  }
  if (res.data.length === 0) {
    container.innerHTML = emptyHtml("No districts yet");
    return;
  }

  const totalMembers = res.data.reduce((sum, row) => sum + row.member_count, 0);

  container.innerHTML = `
    <table>
      <thead>
        <tr><th>District</th><th>Branches</th><th>Members</th><th>Share of Members</th><th>New This Month</th><th>District Lead</th><th>Status</th></tr>
      </thead>
      <tbody>
        ${res.data.map((row) => {
          const pct = totalMembers ? Math.round((row.member_count / totalMembers) * 100) : 0;
          return `
            <tr>
              <td class="font-medium text-gray-900">${escapeHtml(row.name)}</td>
              <td>${row.branch_count}</td>
              <td>${row.member_count}</td>
              <td class="min-w-[140px]">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-2 rounded-full bg-blue-600" style="width: ${pct}%"></div>
                  </div>
                  <span class="text-xs text-gray-600 w-9 text-right">${pct}%</span>
                </div>
              </td>
              <td>${row.new_members}</td>
              <td>${row.administrator_name ? escapeHtml(row.administrator_name) : '<span class="text-gray-400">Not assigned</span>'}</td>
              <td>${statusBadge(row.is_active)}</td>
            </tr>`;
        }).join("")}
      </tbody>
    </table>`;
}

// ==================== Activity ====================

async function loadActivity(containerId, limit) {
  const container = document.getElementById(containerId);
  container.innerHTML = loadingHtml();

  const res = await apiCall(`/province/activity?limit=${limit}`);
  if (!res.success) {
    container.innerHTML = errorHtml(res.message || "Failed to load activity");
    return;
  }

  state.activity = res.data;
  if (containerId === "activity-list") {
    renderActivityList(containerId, state.activity);
  } else {
    renderActivityTable(containerId, state.activity, value("activity-filter"));
  }
}

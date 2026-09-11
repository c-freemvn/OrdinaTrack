/**
 * Branch Members Page
 *
 * Lists, filters, adds, edits and removes the members of the branch the
 * signed-in user administers, through the /branch API. The server scopes
 * every request to that branch.
 */

// ==================== Configuration ====================

const MEMBERS_CONFIG = {
  API_BASE: window.location.origin + "/OrdinaTrack/API/index.php",
  TOKEN_KEY: "auth_token",
  SIGNIN_URL: "/OrdinaTrack/App/pages/signin.html",
  PAGE_SIZE: 20,
};

const AVATAR_GRADIENTS = [
  "from-blue-400 to-blue-600",
  "from-green-400 to-green-600",
  "from-yellow-400 to-yellow-600",
  "from-purple-400 to-purple-600",
  "from-pink-400 to-pink-600",
  "from-indigo-400 to-indigo-600",
];

const state = {
  branch: null,
  roles: [],
  members: [],
  total: 0,
  offset: 0,
  filters: { search: "", status: "", role: "" },
  request: 0,
  editing: null,
  removing: null,
  returnFocus: null,
  toastTimer: null,
};

// ==================== Initialization ====================

document.addEventListener("DOMContentLoaded", () => {
  if (!localStorage.getItem(MEMBERS_CONFIG.TOKEN_KEY)) {
    window.location.href = MEMBERS_CONFIG.SIGNIN_URL;
    return;
  }

  bindEvents();
  loadBranch();
});

function bindEvents() {
  byId("addMemberBtn").addEventListener("click", () => openMemberModal());

  // Filters apply on the button/Enter, when a select changes, and as you type
  byId("memberFilters").addEventListener("submit", (e) => {
    e.preventDefault();
    applyFilters();
  });
  byId("statusFilter").addEventListener("change", applyFilters);
  byId("roleFilter").addEventListener("change", applyFilters);
  let searchTimer;
  byId("memberSearch").addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
  });

  byId("prevPage").addEventListener("click", () => changePage(-1));
  byId("nextPage").addEventListener("click", () => changePage(1));

  // Edit / Remove buttons in the table rows
  byId("membersBody").addEventListener("click", (e) => {
    const button = e.target.closest("button[data-action]");
    if (!button) return;
    const member = state.members.find((m) => m.id === Number(button.dataset.id));
    if (!member) return;
    if (button.dataset.action === "edit") openMemberModal(member);
    else openRemoveModal(member);
  });

  byId("memberForm").addEventListener("submit", submitMemberForm);
  byId("confirmRemoveBtn").addEventListener("click", confirmRemove);

  // Modals close on Cancel, backdrop click or Escape; Tab stays inside them
  document.querySelectorAll(".modal-backdrop").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal || e.target.closest("[data-close-modal]")) {
        closeModal(modal.id);
      }
    });
  });
  document.addEventListener("keydown", (e) => {
    const modal = document.querySelector(".modal-backdrop:not([hidden])");
    if (!modal) return;
    if (e.key === "Escape") closeModal(modal.id);
    if (e.key === "Tab") trapFocus(modal, e);
  });
}

// ==================== Data Loading ====================

async function loadBranch() {
  const res = await apiCall("/branch/info");
  if (!res.success) {
    if (res.code === "NO_BRANCH") return showNoBranch(res.message);
    renderTableMessage(res.message || "Couldn't load your branch.", [
      "Try again",
      loadBranch,
    ]);
    return;
  }

  state.branch = res.data;
  state.roles = res.data.member_roles || [];
  byId("branchSubtitle").textContent = [res.data.name, res.data.district_name]
    .filter(Boolean)
    .join(" · ");
  fillOptions(byId("roleFilter"), state.roles, "All Roles");
  fillOptions(byId("memberRole"), state.roles);
  byId("addMemberBtn").disabled = false;
  loadMembers();
}

async function loadMembers() {
  const request = ++state.request;
  renderTableMessage("Loading members…");

  const params = new URLSearchParams({
    limit: MEMBERS_CONFIG.PAGE_SIZE,
    offset: state.offset,
  });
  Object.entries(state.filters).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });

  const res = await apiCall(`/branch/members?${params}`);
  if (request !== state.request) return; // a newer search or page replaced this one

  if (!res.success) {
    if (res.code === "NO_BRANCH") return showNoBranch(res.message);
    state.members = [];
    renderTableMessage(res.message || "Couldn't load members.", [
      "Try again",
      loadMembers,
    ]);
    updatePagination();
    return;
  }

  state.members = res.data;
  state.total = res.total;

  // Removing the last member on a page leaves it empty: step back a page
  if (!state.members.length && state.offset > 0) {
    state.offset = Math.max(0, state.offset - MEMBERS_CONFIG.PAGE_SIZE);
    loadMembers();
    return;
  }

  renderMembers();
  updatePagination();
}

function applyFilters() {
  state.filters = {
    search: byId("memberSearch").value.trim(),
    status: byId("statusFilter").value,
    role: byId("roleFilter").value,
  };
  state.offset = 0;
  if (state.branch) loadMembers();
}

function clearFilters() {
  byId("memberFilters").reset();
  applyFilters();
}

function changePage(step) {
  state.offset = Math.max(0, state.offset + step * MEMBERS_CONFIG.PAGE_SIZE);
  loadMembers();
}

// ==================== Rendering ====================

function renderMembers() {
  if (!state.members.length) {
    const filtered = Object.values(state.filters).some(Boolean);
    renderTableMessage(
      filtered
        ? "No members match these filters."
        : "No members yet. Add your first member to get started.",
      filtered ? ["Clear filters", clearFilters] : ["+ Add Member", () => openMemberModal()],
    );
    return;
  }

  byId("membersBody").innerHTML = state.members.map(memberRow).join("");
}

function memberRow(member) {
  const name = `${member.first_name} ${member.last_name}`;
  const dash = '<span class="text-gray-400">—</span>';

  return `
    <tr>
      <td>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br ${avatarGradient(name)} flex items-center justify-center text-white text-sm font-bold" aria-hidden="true">${escapeHtml(initials(member))}</div>
          <span class="font-medium text-gray-900">${escapeHtml(name)}</span>
        </div>
      </td>
      <td>${member.email ? escapeHtml(member.email) : dash}</td>
      <td>${member.phone ? escapeHtml(member.phone) : dash}</td>
      <td>${escapeHtml(member.role || "Member")}</td>
      <td><span class="badge ${member.is_active ? "badge-active" : "badge-inactive"}">${member.is_active ? "Active" : "Inactive"}</span></td>
      <td>${formatDate(member.member_since)}</td>
      <td>
        <div class="flex gap-2">
          <button type="button" class="text-blue-600 hover:underline text-sm" data-action="edit" data-id="${Number(member.id)}" aria-label="Edit ${escapeHtml(name)}">Edit</button>
          <button type="button" class="text-red-600 hover:underline text-sm" data-action="remove" data-id="${Number(member.id)}" aria-label="Remove ${escapeHtml(name)}">Remove</button>
        </div>
      </td>
    </tr>`;
}

/**
 * Replace the table body with a single message row, optionally with a button
 */
function renderTableMessage(message, [label, onClick] = []) {
  const cell = document.createElement("td");
  cell.colSpan = 7;
  cell.className = "text-center text-gray-500 py-10";
  cell.textContent = message;

  if (label) {
    const button = document.createElement("button");
    button.type = "button";
    button.className =
      "block mx-auto mt-3 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700";
    button.textContent = label;
    button.addEventListener("click", onClick);
    cell.appendChild(button);
  }

  const row = document.createElement("tr");
  row.className = "table-message";
  row.appendChild(cell);
  byId("membersBody").replaceChildren(row);
}

function updatePagination() {
  const count = state.members.length;
  const to = state.offset + count;

  byId("membersSummary").textContent = count
    ? `Showing ${state.offset + 1} to ${to} of ${state.total} ${state.total === 1 ? "member" : "members"}`
    : "";
  byId("prevPage").disabled = state.offset === 0;
  byId("nextPage").disabled = !count || to >= state.total;
}

function showNoBranch(message) {
  byId("membersArea").hidden = true;
  byId("noBranchNotice").hidden = false;
  byId("noBranchMessage").textContent = message;
  byId("addMemberBtn").disabled = true;
}

// ==================== Add / Edit ====================

function openMemberModal(member = null) {
  state.editing = member;
  byId("memberForm").reset();
  showFormError("");
  byId("memberModalTitle").textContent = member ? "Edit member" : "Add member";
  byId("memberSaveBtn").textContent = member ? "Save changes" : "Add member";

  byId("memberFirstName").value = member?.first_name || "";
  byId("memberLastName").value = member?.last_name || "";
  byId("memberEmail").value = member?.email || "";
  byId("memberPhone").value = member?.phone || "";
  byId("memberRole").value = member?.role || state.roles[0] || "";
  byId("memberStatus").value = !member || member.is_active ? "1" : "0";
  byId("memberSince").value = member?.member_since || today();
  byId("memberSince").max = today();

  openModal("memberModal", "memberFirstName");
}

async function submitMemberForm(e) {
  e.preventDefault();

  const payload = {
    first_name: byId("memberFirstName").value.trim(),
    last_name: byId("memberLastName").value.trim(),
    email: byId("memberEmail").value.trim(),
    phone: byId("memberPhone").value.trim(),
    role: byId("memberRole").value,
    is_active: byId("memberStatus").value === "1",
    member_since: byId("memberSince").value,
  };
  if (!payload.first_name || !payload.last_name) {
    showFormError("Please enter the member's first and last name.");
    return;
  }

  const editing = state.editing;
  const button = byId("memberSaveBtn");
  const label = button.textContent;
  button.disabled = true;
  button.textContent = "Saving…";

  const res = editing
    ? await apiCall(`/branch/members/${editing.id}`, "PUT", payload)
    : await apiCall("/branch/members", "POST", payload);

  button.disabled = false;
  button.textContent = label;

  if (!res.success) {
    showFormError(res.message || "Couldn't save this member. Please try again.");
    return;
  }

  closeModal("memberModal");
  showToast(editing ? "Member updated" : "Member added");
  loadMembers();
}

function showFormError(message) {
  const error = byId("memberFormError");
  error.textContent = message;
  error.hidden = !message;
}

// ==================== Remove ====================

function openRemoveModal(member) {
  state.removing = member;
  byId("removeMessage").textContent =
    `${member.first_name} ${member.last_name} will be removed from your branch's member list.`;
  openModal("removeModal", "cancelRemoveBtn");
}

async function confirmRemove() {
  const member = state.removing;
  if (!member) return;

  const button = byId("confirmRemoveBtn");
  button.disabled = true;
  button.textContent = "Removing…";
  const res = await apiCall(`/branch/members/${member.id}`, "DELETE");
  button.disabled = false;
  button.textContent = "Remove";
  closeModal("removeModal");

  if (!res.success) {
    showToast(res.message || "Couldn't remove this member.", "error");
    return;
  }

  showToast(`${member.first_name} ${member.last_name} removed`);
  loadMembers();
}

// ==================== Modals & Toast ====================

function openModal(id, focusId) {
  state.returnFocus = document.activeElement;
  byId(id).hidden = false;
  byId(focusId)?.focus();
}

function closeModal(id) {
  byId(id).hidden = true;
  if (state.returnFocus?.isConnected) state.returnFocus.focus();
}

function trapFocus(modal, e) {
  const focusable = [
    ...modal.querySelectorAll("button, input, select, textarea, a[href]"),
  ].filter((el) => !el.disabled);
  if (!focusable.length) return;

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}

function showToast(message, type = "success") {
  const toast = byId("toast");
  toast.textContent = message;
  toast.className = `toast toast-${type} is-visible`;
  clearTimeout(state.toastTimer);
  state.toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 3000);
}

// ==================== API ====================

async function apiCall(endpoint, method = "GET", body = null) {
  const options = {
    method,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${localStorage.getItem(MEMBERS_CONFIG.TOKEN_KEY)}`,
    },
  };
  if (body) options.body = JSON.stringify(body);

  let data;
  try {
    const response = await fetch(MEMBERS_CONFIG.API_BASE + endpoint, options);
    data = await response.json();
  } catch (error) {
    console.error("API error:", error);
    return {
      success: false,
      message: "Couldn't reach the server. Check your connection and try again.",
    };
  }

  // Missing or expired session
  if (!data.success && data.statuscode === 99) {
    localStorage.removeItem(MEMBERS_CONFIG.TOKEN_KEY);
    window.location.href = MEMBERS_CONFIG.SIGNIN_URL;
  }
  return data;
}

// ==================== Utilities ====================

function byId(id) {
  return document.getElementById(id);
}

function fillOptions(select, values, allLabel = null) {
  const options = allLabel ? [new Option(allLabel, "")] : [];
  values.forEach((value) => options.push(new Option(value, value)));
  select.replaceChildren(...options);
}

function initials(member) {
  return `${member.first_name?.[0] || ""}${member.last_name?.[0] || ""}`.toUpperCase();
}

function avatarGradient(name) {
  let hash = 0;
  for (const char of name) hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
  return AVATAR_GRADIENTS[hash % AVATAR_GRADIENTS.length];
}

// "2024-01-15" -> "Jan 15, 2024" (parsed as a local date, so it never shifts a day)
function formatDate(value) {
  const [year, month, day] = String(value || "").split("-").map(Number);
  if (!year || !month || !day) return "—";
  return new Date(year, month - 1, day).toLocaleDateString("en-US", {
    month: "short",
    day: "2-digit",
    year: "numeric",
  });
}

function today() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function escapeHtml(text) {
  const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
  return String(text ?? "").replace(/[&<>"']/g, (c) => map[c]);
}

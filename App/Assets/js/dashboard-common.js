/**
 * Shared helpers for the role dashboards (district, province)
 *
 * API calls with the stored token, the page shell (logout, mobile menu, modals), toasts,
 * formatting and the activity feed renderers. Page modules such as district.js and
 * province.js load after this file.
 */

// ==================== Configuration ====================

const DASHBOARD_CONFIG = {
  API_BASE: window.location.origin + "/OrdinaTrack/API/index.php",
  TOKEN_KEY: "auth_token",
  USER_INFO_KEY: "user_info",
  SIGNIN_URL: "/OrdinaTrack/App/pages/signin.html",
  PAGE_SIZE: 20,
};

// ==================== Shell ====================

function page() {
  return document.body.dataset.page || "";
}

/**
 * Send signed-out visitors to the sign-in page. Returns false when redirecting.
 */
function requireToken() {
  if (localStorage.getItem(DASHBOARD_CONFIG.TOKEN_KEY)) return true;
  window.location.href = DASHBOARD_CONFIG.SIGNIN_URL;
  return false;
}

function bindShell() {
  document.querySelectorAll('[data-action="logout"]').forEach((btn) => btn.addEventListener("click", logout));

  document.getElementById("menu-toggle")?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.toggle("hidden");
  });

  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal(modal.id);
    });
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.active").forEach((modal) => closeModal(modal.id));
    }
  });

  const user = currentUser();
  if (user) {
    document.querySelectorAll("[data-lead-name]").forEach((el) => (el.textContent = fullName(user) || el.textContent));
  }
}

function currentUser() {
  try {
    return JSON.parse(localStorage.getItem(DASHBOARD_CONFIG.USER_INFO_KEY) || "null");
  } catch (e) {
    return null;
  }
}

/**
 * The user runs nothing at this level (or the API is unreachable): explain it once at the
 * top and disable the page's actions instead of showing empty tables.
 *
 * @param {object} res The failed /info response
 * @param {string} noScopeCode e.g. "NO_DISTRICT", which is expected and needs no toast
 * @param {string[]} containerIds Content areas to clear
 */
function showUnavailable(res, noScopeCode, containerIds) {
  const notice = document.getElementById("scope-notice");
  if (notice) {
    notice.textContent = res.message || "Could not load your dashboard.";
    notice.classList.remove("hidden");
  }

  document.querySelectorAll("[data-needs-scope]").forEach((el) => (el.disabled = true));
  containerIds.forEach((id) => {
    const container = document.getElementById(id);
    if (container) container.innerHTML = emptyHtml("Nothing to show yet");
  });
  if (res.code !== noScopeCode) {
    showToast(res.message || "Could not load your dashboard", "danger");
  }
}

// ==================== API ====================

async function apiCall(endpoint, method = "GET", body = null) {
  const options = {
    method,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${localStorage.getItem(DASHBOARD_CONFIG.TOKEN_KEY)}`,
    },
  };
  if (body !== null) {
    options.body = JSON.stringify(body);
  }

  let data;
  try {
    const response = await fetch(DASHBOARD_CONFIG.API_BASE + endpoint, options);
    data = await response.json();
  } catch (error) {
    console.error("API error:", error);
    return { success: false, message: "Network error. Please check your connection." };
  }

  // Token missing/expired, or the account was deactivated
  if (data.statuscode === 99) {
    logout();
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
  } else if (res.statuscode !== 99) {
    showToast(res.message || "Something went wrong", "danger");
  }
  return res;
}

// ==================== Lists ====================

/**
 * Wire a search box to reload after typing pauses
 */
function bindSearch(inputId, onSearch) {
  let timer;
  document.getElementById(inputId)?.addEventListener("input", (e) => {
    clearTimeout(timer);
    timer = setTimeout(() => onSearch(e.target.value.trim()), 300);
  });
}

/**
 * Update "Showing x-y of z" and the prev/next buttons for a list with id prefix
 * (e.g. "members" -> #members-info, #members-prev, #members-next)
 */
function renderPager(prefix, offset, shown, total, noun) {
  setText(`${prefix}-info`, total === 0 ? `No ${noun}` : `Showing ${offset + 1}-${offset + shown} of ${total}`);
  const prev = document.getElementById(`${prefix}-prev`);
  const next = document.getElementById(`${prefix}-next`);
  if (prev) prev.disabled = offset === 0;
  if (next) next.disabled = offset + shown >= total;
}

function statusBadge(isActive) {
  return `<span class="badge ${isActive ? "badge-active" : "badge-inactive"}">${isActive ? "Active" : "Inactive"}</span>`;
}

function codeBadge(code) {
  return code ? `<code class="bg-gray-100 px-2 py-1 rounded text-xs">${escapeHtml(code)}</code>` : "-";
}

// ==================== Activity ====================

const ACTION_BADGES = { CREATE: "badge-success", UPDATE: "badge-info", DELETE: "badge-danger" };

/**
 * Compact feed for overview pages
 */
function renderActivityList(containerId, rows) {
  const container = document.getElementById(containerId);
  if (!container) return;
  if (rows.length === 0) {
    container.innerHTML = emptyHtml("No activity yet");
    return;
  }

  container.innerHTML = rows.map((log) => `
    <div class="flex items-center gap-4 pb-3 border-b border-gray-200 last:border-0">
      <span class="badge ${ACTION_BADGES[log.action] || "badge-muted"}">${escapeHtml(log.action)}</span>
      <div class="flex-1">
        <p class="text-sm font-medium text-gray-900">${escapeHtml(describeActivity(log))}</p>
        <p class="text-xs text-gray-600">${escapeHtml(fullName(log) || log.email || "System")} - ${formatDate(log.created_at)}</p>
      </div>
    </div>`).join("");
}

/**
 * Full audit table, optionally limited to one resource type
 */
function renderActivityTable(containerId, rows, resourceFilter = "") {
  const container = document.getElementById(containerId);
  if (!container) return;
  const shown = resourceFilter ? rows.filter((log) => log.resource_type === resourceFilter) : rows;

  if (shown.length === 0) {
    container.innerHTML = emptyHtml(resourceFilter ? "No activity of this kind yet" : "No activity yet");
    return;
  }

  container.innerHTML = `
    <table>
      <thead><tr><th>Who</th><th>Action</th><th>What</th><th>Details</th><th>When</th></tr></thead>
      <tbody>
        ${shown.map((log) => `
          <tr>
            <td>${escapeHtml(fullName(log) || log.email || "System")}</td>
            <td><span class="badge ${ACTION_BADGES[log.action] || "badge-muted"}">${escapeHtml(log.action)}</span></td>
            <td class="whitespace-nowrap">${escapeHtml(log.resource_type)}${log.resource_id ? ` #${log.resource_id}` : ""}</td>
            <td class="text-xs text-gray-600">${escapeHtml(summarizeChanges(log.new_values))}</td>
            <td class="text-gray-500 whitespace-nowrap">${formatDate(log.created_at)}</td>
          </tr>`).join("")}
      </tbody>
    </table>`;
}

/**
 * 'CREATE' + 'branches' -> 'Branch created'
 */
function describeActivity(log) {
  const nouns = { provinces: "Province", districts: "District", branches: "Branch", members: "Member", users: "User", roles: "Role" };
  const verbs = { CREATE: "created", UPDATE: "updated", DELETE: "deleted" };
  const noun = nouns[log.resource_type] || log.resource_type;
  return `${noun} ${verbs[log.action] || String(log.action).toLowerCase()}`;
}

/**
 * '{"name":"Lekki","is_active":true}' -> 'name: Lekki, is_active: true'
 */
function summarizeChanges(json) {
  if (!json) return "-";
  try {
    const text = Object.entries(JSON.parse(json))
      .map(([key, val]) => `${key}: ${Array.isArray(val) ? `[${val.join(", ")}]` : val}`)
      .join(", ");
    return text.length > 120 ? text.slice(0, 117) + "..." : text || "-";
  } catch (e) {
    return "-";
  }
}

// ==================== Utilities ====================

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function value(id) {
  return document.getElementById(id)?.value.trim() ?? "";
}

function setValue(id, val) {
  const el = document.getElementById(id);
  if (el) el.value = val ?? "";
}

function openModal(id) {
  document.getElementById(id)?.classList.add("active");
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove("active");
}

function setBusy(button, busy) {
  if (!button) return;
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
  const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
  return String(text ?? "").replace(/[&<>"']/g, (m) => map[m]);
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(String(dateString).replace(" ", "T"));
  if (isNaN(date)) return "-";
  return date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
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
  ["auth_token", "user_info", "refresh_token", "token_expiry"].forEach((key) => localStorage.removeItem(key));
  window.location.href = DASHBOARD_CONFIG.SIGNIN_URL;
}

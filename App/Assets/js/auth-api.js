/**
 * OrdinaTrack Authentication Module
 *
 * Comprehensive authentication handler for:
 * - User login via API
 * - User registration via API (2-step process)
 * - Password reset via email
 * - Session management
 * - Token storage and validation
 * - Tab switching and UI management
 */

// ==================== Configuration ====================

const AUTH_CONFIG = {
  TOKEN_KEY: "auth_token",
  USER_INFO_KEY: "user_info",
  REFRESH_TOKEN_KEY: "refresh_token",
  TOKEN_EXPIRY_KEY: "token_expiry",
  REDIRECT_DELAY: 1500,
  API_BASE: window.location.origin + "/OrdinaTrack/API/index.php",
  DASHBAORD_REDIRECT: window.location.origin + "/OrdinaTrack/App/dashboard/",
};

// API endpoint helper
const API = {
  login: () => `/auth/login`,
  register: () => `/auth/register`,
  requestPasswordReset: () => `/auth/request-password-reset`,

  async post(endpoint, data, includeToken = false) {
    try {
      const fullUrl = AUTH_CONFIG.API_BASE + endpoint;
      const headers = {
        "Content-Type": "application/json",
      };

      // Add token to Authorization header for authenticated requests
      if (includeToken) {
        const token = localStorage.getItem(AUTH_CONFIG.TOKEN_KEY);
        if (token) {
          headers["Authorization"] = `Bearer ${token}`;
        }
      }

      const response = await fetch(fullUrl, {
        method: "POST",
        headers: headers,
        body: JSON.stringify(data),
      });
      return await response.json();
    } catch (error) {
      console.error("API error:", error);
      return {
        success: false,
        message: "Network error. Please check your connection.",
      };
    }
  },
};

// ==================== Utility Functions (Defined Early) ====================

function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

function isStrongPassword(password) {
  const hasUpper = /[A-Z]/.test(password);
  const hasLower = /[a-z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

  return hasUpper && hasLower && hasNumber && hasSpecial;
}

function showAuthStatus(message, type = "info") {
  const status = document.getElementById("authStatus");
  if (!status) return;

  status.className = `alert alert-${type} mt-3`;
  status.textContent = message;
  status.classList.remove("d-none");

  // Auto-hide success messages
  if (type === "success") {
    setTimeout(() => {
      status.classList.add("d-none");
    }, 5000);
  }
}

function clearAuthStatus() {
  const status = document.getElementById("authStatus");
  if (status) {
    status.className = "alert d-none mt-3";
    status.textContent = "";
  }
}

// ==================== Initialization ====================

document.addEventListener("DOMContentLoaded", function () {
  // Only run on auth pages
  if (document.body.dataset.page !== "auth") return;

  initializeAuthPage();
});

function initializeAuthPage() {
  // Bind event handlers
  bindTabSwitching();
  bindSigninFlow();
  bindSignupFlow();
  bindForgotPasswordFlow();

  // Handle hash navigation to signup
  if (window.location.hash === "#signup") {
    activateSignupPanel();
  }

  // Check if user already logged in
  checkExistingSession();
}

// ==================== Tab Switching ====================

function bindTabSwitching() {
  document.querySelectorAll("[data-auth-target]").forEach((tab) => {
    tab.addEventListener("click", handleTabClick);
  });
}

function handleTabClick(event) {
  const targetPanel = event.target.dataset.authTarget;
  activatePanel(targetPanel);
}

function activatePanel(panelId) {
  // Update tab active state
  document.querySelectorAll("[data-auth-target]").forEach((tab) => {
    tab.classList.toggle("active", tab.dataset.authTarget === panelId);
  });

  // Show/hide panels
  document.querySelectorAll("section[id*='Panel']").forEach((panel) => {
    if (panel.id === panelId) {
      panel.classList.remove("hidden");
    } else {
      panel.classList.add("hidden");
    }
  });

  // Clear messages
  clearAuthStatus();

  // Update URL hash if signup
  if (panelId === "signupPanel") {
    window.location.hash = "signup";
  } else {
    history.replaceState(null, "", window.location.pathname);
  }
}

function activateSignupPanel() {
  activatePanel("signupPanel");
}

// ==================== Sign In Flow ====================

function bindSigninFlow() {
  const form = document.getElementById("signinForm");
  if (form) {
    form.addEventListener("submit", handleSigninSubmit);
  }
}

async function handleSigninSubmit(event) {
  event.preventDefault();

  const email = document
    .getElementById("signinEmail")
    ?.value.trim()
    .toLowerCase();
  const password = document.getElementById("signinPassword")?.value;

  // Validate input
  if (!email || !password) {
    showAuthStatus("Please enter both email and password", "danger");
    return;
  }

  if (!isValidEmail(email)) {
    showAuthStatus("Please enter a valid email address", "danger");
    return;
  }

  // Disable button
  const btn = document.getElementById("signinBtn");
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = "Signing in...";

  try {
    const response = await API.post(API.login(), {
      email: email,
      password: password,
    });

    if (response.success && response.data?.token) {
      // Store auth data
      storeAuthToken(response.data);

      showAuthStatus("Login successful! Redirecting...", "success");

      // Redirect after delay
      setTimeout(() => {
        redirectToDashboard(response.data.user);
      }, AUTH_CONFIG.REDIRECT_DELAY);
    } else {
      showAuthStatus(
        response.message || "Login failed. Please check your credentials.",
        "danger",
      );
      btn.disabled = false;
      btn.textContent = originalText;
    }
  } catch (error) {
    console.error("Login error:", error);
    showAuthStatus(
      "An error occurred during login. Please try again.",
      "danger",
    );
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

// ==================== Sign Up Flow ====================

function bindSignupFlow() {
  // Form submission
  const form = document.getElementById("signupForm");
  if (form) {
    form.addEventListener("submit", handleSignupSubmit);
  }

  // Step navigation
  const nextBtn = document.getElementById("nextSignupStep");
  if (nextBtn) {
    nextBtn.addEventListener("click", goToPasswordStep);
  }

  const backBtn = document.getElementById("backSignupStep");
  if (backBtn) {
    backBtn.addEventListener("click", goToDetailsStep);
  }

  // Role change handler
  const roleSelect = document.getElementById("signupRole");
  if (roleSelect) {
    roleSelect.addEventListener("change", handleRoleChange);
  }

  // Province/District change handlers
  const provinceSelect = document.getElementById("provinceSelect");
  if (provinceSelect) {
    provinceSelect.addEventListener("change", handleProvinceChange);
  }

  const districtSelect = document.getElementById("districtSelect");
  if (districtSelect) {
    districtSelect.addEventListener("change", updateOfficeSummary);
  }

  const branchNameInput = document.getElementById("branchName");
  if (branchNameInput) {
    branchNameInput.addEventListener("input", updateOfficeSummary);
  }
}

function handleRoleChange() {
  const role = document.getElementById("signupRole").value;

  // Show/hide province field
  const provinceField = document.getElementById("provinceField");
  const showProvince = ["Province", "District", "Branch"].includes(role);
  provinceField.classList.toggle("hidden", !showProvince);

  // Show/hide district field
  const districtField = document.getElementById("districtField");
  const showDistrict = ["District", "Branch"].includes(role);
  districtField.classList.toggle("hidden", !showDistrict);

  // Show/hide branch name field
  const branchNameField = document.getElementById("branchNameField");
  branchNameField.classList.toggle("hidden", role !== "Branch");

  // Show/hide office summary field
  const officeSummaryField = document.getElementById("officeSummaryField");
  officeSummaryField.classList.toggle("hidden", role === "Branch" || !role);

  // Clear district dropdown
  const districtSelect = document.getElementById("districtSelect");
  districtSelect.innerHTML = '<option value="">Select district...</option>';

  // Update office summary
  updateOfficeSummary();
}

function handleProvinceChange() {
  const province = document.getElementById("provinceSelect").value;
  const districtSelect = document.getElementById("districtSelect");

  // Reset district dropdown
  districtSelect.innerHTML = '<option value="">Select district...</option>';

  // Load districts from global data (populated in auth.js)
  if (
    province &&
    window.PROVINCE_DISTRICT_MAP &&
    window.PROVINCE_DISTRICT_MAP[province]
  ) {
    const districts = window.PROVINCE_DISTRICT_MAP[province];
    districts.forEach((district) => {
      const option = document.createElement("option");
      option.value = district;
      option.textContent = district;
      districtSelect.appendChild(option);
    });
  }

  updateOfficeSummary();
}

function updateOfficeSummary() {
  const role = document.getElementById("signupRole").value;
  const province = document.getElementById("provinceSelect")?.value || "";
  const district = document.getElementById("districtSelect")?.value || "";
  const branch = document.getElementById("branchName")?.value.trim() || "";
  const summary = document.getElementById("officeSummary");

  if (!summary) return;

  let text = role;
  if (province) text += ` - ${province}`;
  if (district) text += ` - ${district}`;
  if (branch) text += ` - ${branch}`;

  summary.value = text;
}

function goToPasswordStep() {
  const secretaryName = document.getElementById("secretaryName")?.value.trim();
  const email = document.getElementById("churchEmail")?.value.trim();
  const role = document.getElementById("signupRole")?.value;
  const province = document.getElementById("provinceSelect")?.value;
  const district = document.getElementById("districtSelect")?.value;
  const branch = document.getElementById("branchName")?.value.trim();

  // Validate step 1
  if (!secretaryName || !email || !role) {
    showAuthStatus("Please fill in all required fields", "danger");
    return;
  }

  if (!isValidEmail(email)) {
    showAuthStatus("Please enter a valid email address", "danger");
    return;
  }

  if (["Province", "District", "Branch"].includes(role) && !province) {
    showAuthStatus("Please select a province", "danger");
    return;
  }

  if (["District", "Branch"].includes(role) && !district) {
    showAuthStatus("Please select a district", "danger");
    return;
  }

  if (role === "Branch" && !branch) {
    showAuthStatus("Please enter the branch name", "danger");
    return;
  }

  // Move to step 2
  document.getElementById("signupStepOne").classList.add("hidden");
  document.getElementById("signupStepTwo").classList.remove("hidden");
  clearAuthStatus();
}

function goToDetailsStep() {
  document.getElementById("signupStepOne").classList.remove("hidden");
  document.getElementById("signupStepTwo").classList.add("hidden");
  clearAuthStatus();
}

async function handleSignupSubmit(event) {
  event.preventDefault();

  // Collect data
  const secretaryName = document.getElementById("secretaryName")?.value.trim();
  const email = document
    .getElementById("churchEmail")
    ?.value.trim()
    .toLowerCase();
  const password = document.getElementById("signupPassword")?.value;
  const confirmPassword = document.getElementById(
    "confirmSignupPassword",
  )?.value;
  const role = document.getElementById("signupRole")?.value;
  const province = document.getElementById("provinceSelect")?.value || null;
  const district = document.getElementById("districtSelect")?.value || null;
  const branch = document.getElementById("branchName")?.value.trim() || null;

  // Validate passwords
  if (!password || !confirmPassword) {
    showAuthStatus("Please enter both passwords", "danger");
    return;
  }

  if (password !== confirmPassword) {
    showAuthStatus("Passwords do not match", "danger");
    return;
  }

  if (password.length < 8) {
    showAuthStatus("Password must be at least 8 characters long", "danger");
    return;
  }

  if (!isStrongPassword(password)) {
    showAuthStatus(
      "Password must contain uppercase, lowercase, number and special character",
      "danger",
    );
    return;
  }

  // Disable submit button
  const btn = event.target.querySelector('button[type="submit"]');
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = "Creating account...";

  try {
    // Extract name parts
    const nameParts = secretaryName.split(" ");
    const firstName = nameParts[0];
    const lastName = nameParts.slice(1).join(" ") || "User";

    const response = await API.post(API.register(), {
      email: email,
      password: password,
      first_name: firstName,
      last_name: lastName,
      role: role,
      province_name: province,
      district_name: district,
      branch_name: branch,
    });

    if (response.success) {
      showAuthStatus("Account created! Redirecting to login...", "success");

      // Reset form
      document.getElementById("signupForm").reset();
      document.getElementById("signupStepOne").classList.remove("d-none");
      document.getElementById("signupStepTwo").classList.add("d-none");

      // Switch to signin tab
      setTimeout(() => {
        activatePanel("signinPanel");
        document.getElementById("signinEmail").value = email;
        document.getElementById("signinEmail").focus();
      }, AUTH_CONFIG.REDIRECT_DELAY);
    } else {
      showAuthStatus(response.message || "Registration failed", "danger");
      btn.disabled = false;
      btn.textContent = originalText;
    }
  } catch (error) {
    console.error("Registration error:", error);
    showAuthStatus(
      "An error occurred during registration. Please try again.",
      "danger",
    );
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

// ==================== Forgot Password Flow ====================

function bindForgotPasswordFlow() {
  const toggleBtn = document.getElementById("forgotPasswordToggle");
  if (toggleBtn) {
    toggleBtn.addEventListener("click", toggleForgotPasswordPanel);
  }

  const cancelBtn = document.getElementById("cancelForgotPassword");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", closeForgotPasswordPanel);
  }

  const form = document.getElementById("forgotPasswordForm");
  if (form) {
    form.addEventListener("submit", handleForgotPasswordSubmit);
  }
}

function toggleForgotPasswordPanel() {
  const panel = document.getElementById("forgotPasswordPanel");
  panel.classList.toggle("d-none");
  clearAuthStatus();
}

function closeForgotPasswordPanel() {
  const panel = document.getElementById("forgotPasswordPanel");
  panel.classList.add("d-none");
  document.getElementById("forgotPasswordForm").reset();
  const info = document.getElementById("resetTokenInfo");
  if (info) info.classList.add("d-none");
  clearAuthStatus();
}

async function handleForgotPasswordSubmit(event) {
  event.preventDefault();

  const email = document
    .getElementById("forgotPasswordEmail")
    ?.value.trim()
    .toLowerCase();

  if (!email || !isValidEmail(email)) {
    showAuthStatus("Please enter a valid email address", "danger");
    return;
  }

  // Disable submit button
  const btn = event.target.querySelector('button[type="submit"]');
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = "Sending...";

  try {
    const response = await API.post(API.requestPasswordReset(), {
      email: email,
      reset_url_base:
        window.location.origin + "/OrdinaTrack/App/pages/reset-password.html",
    });

    if (response.success) {
      showAuthStatus(
        "Password reset email sent! Check your inbox for the reset link.",
        "success",
      );

      // Show info message
      const infoMsg = document.getElementById("resetTokenInfo");
      if (infoMsg) {
        infoMsg.classList.remove("d-none");
      }

      // Close panel after delay
      setTimeout(() => {
        closeForgotPasswordPanel();
        btn.disabled = false;
        btn.textContent = originalText;
      }, 3000);
    } else {
      showAuthStatus(
        response.message || "Failed to send reset email",
        "danger",
      );
      btn.disabled = false;
      btn.textContent = originalText;
    }
  } catch (error) {
    console.error("Password reset error:", error);
    showAuthStatus("An error occurred. Please try again.", "danger");
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

// ==================== Session Management ====================

function storeAuthToken(data) {
  if (data.token) {
    localStorage.setItem(AUTH_CONFIG.TOKEN_KEY, data.token);

    // Parse JWT to get expiry
    try {
      const payload = JSON.parse(atob(data.token.split(".")[1]));
      if (payload.exp) {
        localStorage.setItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY, payload.exp * 1000);
      }
    } catch (e) {
      console.warn("Could not parse token expiry");
    }
  }

  if (data.user) {
    localStorage.setItem(AUTH_CONFIG.USER_INFO_KEY, JSON.stringify(data.user));
  }

  if (data.refresh_token) {
    localStorage.setItem(AUTH_CONFIG.REFRESH_TOKEN_KEY, data.refresh_token);
  }
}

function checkExistingSession() {
  const token = localStorage.getItem(AUTH_CONFIG.TOKEN_KEY);
  const expiry = localStorage.getItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);

  // If token exists and not expired, redirect
  if (token && expiry && Date.now() < parseInt(expiry)) {
    // Only redirect if on auth page
    if (
      window.location.pathname.includes("/App/pages/signin") ||
      window.location.pathname.includes("/App/pages/signup")
    ) {
      redirectToDashboard(
        JSON.parse(localStorage.getItem(AUTH_CONFIG.USER_INFO_KEY)),
      );
    }
  }
}

function redirectToDashboard(user) {
  console.log(user);
  if (!user) {
    window.location.href = "/OrdinaTrack/App/pages/index.html";
    return;
  }

  let path = "/OrdinaTrack/App/pages/index.html";

  if (user.role) {
    // switch (user.role.toLowerCase()) {
    //   case "admin":
    //   case "nhq":
    //   case "nhq_admin":
    // path = "/OrdinaTrack/App/pages/nhq-dashboard.html";
    path = `${AUTH_CONFIG.DASHBAORD_REDIRECT}/${user.role}/index.html`;
    //     break;
    //   case "province":
    //   case "province_lead":
    //     path = "/OrdinaTrack/App/pages/province-dashboard.html";
    //     break;
    //   case "district":
    //   case "district_lead":
    //     path = "/OrdinaTrack/App/pages/district-dashboard.html";
    //     break;
    //   case "branch":
    //   case "branch_admin":
    //     path = "/OrdinaTrack/App/pages/branch-dashboard.html";
    //     break;
    // }
  }

  window.location.href = path;
}

function logout() {
  localStorage.removeItem(AUTH_CONFIG.TOKEN_KEY);
  localStorage.removeItem(AUTH_CONFIG.USER_INFO_KEY);
  localStorage.removeItem(AUTH_CONFIG.REFRESH_TOKEN_KEY);
  localStorage.removeItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);

  showAuthStatus("You have been logged out.", "info");

  setTimeout(() => {
    window.location.href = "/OrdinaTrack/App/pages/signin.html";
  }, 1000);
}

// ==================== Logout Handler ====================

document.addEventListener("click", function (event) {
  if (
    event.target.classList.contains("logout-link") ||
    event.target.id === "logoutBtn"
  ) {
    event.preventDefault();
    logout();
  }
});

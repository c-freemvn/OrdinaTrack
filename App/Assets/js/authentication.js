"use strict";

/**
 * OrdinaTrack Authentication Module
 *
 * Handles all authentication operations including:
 * - User login/logout
 * - User registration
 * - Password reset
 * - Session management
 * - Token storage and validation
 */

// ==================== Configuration ====================

const AUTH_CONFIG = {
  TOKEN_KEY: "auth_token",
  USER_INFO_KEY: "user_info",
  REFRESH_TOKEN_KEY: "refresh_token",
  TOKEN_EXPIRY_KEY: "token_expiry",
  REDIRECT_DELAY: 1000,
  TOKEN_REFRESH_THRESHOLD: 300, // Refresh token 5 minutes before expiry
};

// ==================== Initialization ====================

$(document).ready(function () {
  // Initialize authentication module
  initializeAuthenticationModule();

  // Handle sign-in
  handleSignIn();

  // Handle sign-up
  handleSignUp();

  // Handle password reset flow
  handleForgotPassword();

  // Handle tab switching
  handleTabSwitching();

  // Check if user is already logged in
  checkExistingSession();
});

/**
 * Initialize the authentication module
 */
function initializeAuthenticationModule() {
  // Check for expired token on page load
  checkTokenExpiry();

  // Set up auto token refresh
  setupTokenRefresh();
}

// ==================== Sign In ====================

/**
 * Handle sign-in button click
 */
function handleSignIn() {
  $("#Btn_signin").click(function (event) {
    event.preventDefault();

    const email = $("#email").val().trim();
    const password = $("#password").val();

    // Validate input
    if (!email || !password) {
      showAuthStatus("Please enter both email and password", "danger");
      return;
    }

    if (!isValidEmail(email)) {
      showAuthStatus("Please enter a valid email address", "danger");
      return;
    }

    // Disable button and show loading state
    const $btn = $(this);
    const originalText = $btn.text();
    $btn.prop("disabled", true).text("Signing in...");

    // Make API request
    api
      .post("/auth/login", {
        email: email,
        password: password,
      })
      .then(function (response) {
        if (response.success) {
          // Store authentication data
          storeAuthToken(response);

          // Log the sign-in action
          logActivity("login", "User logged in successfully");

          showAuthStatus("Login successful! Redirecting...", "success");

          // Redirect to dashboard after delay
          setTimeout(function () {
            redirectToDashboard();
          }, AUTH_CONFIG.REDIRECT_DELAY);
        } else {
          showAuthStatus(response.message || "Login failed", "danger");
          $btn.prop("disabled", false).text(originalText);
        }
      })
      .catch(function (error) {
        console.error("Error during login:", error);
        showAuthStatus(
          "An error occurred during login. Please try again later.",
          "danger",
        );
        $btn.prop("disabled", false).text(originalText);
      });
  });
}

/**
 * Store authentication token and user info
 *
 * @param {Object} response API response containing token and user data
 */
function storeAuthToken(response) {
  if (response.data && response.data.token) {
    localStorage.setItem(AUTH_CONFIG.TOKEN_KEY, response.data.token);

    // Calculate token expiry (typically JWT tokens have 'exp' claim)
    try {
      const payload = JSON.parse(atob(response.data.token.split(".")[1]));
      if (payload.exp) {
        localStorage.setItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY, payload.exp * 1000);
      }
    } catch (e) {
      console.warn("Could not parse token expiry from JWT");
    }
  }

  // Store user info
  if (response.data && response.data.user) {
    localStorage.setItem(
      AUTH_CONFIG.USER_INFO_KEY,
      JSON.stringify(response.data.user),
    );
  }

  // Store refresh token if provided
  if (response.data && response.data.refresh_token) {
    localStorage.setItem(
      AUTH_CONFIG.REFRESH_TOKEN_KEY,
      response.data.refresh_token,
    );
  }
}

/**
 * Redirect to appropriate dashboard based on user role
 */
function redirectToDashboard() {
  const userInfo = getCurrentUser();

  if (!userInfo) {
    window.location.href = "/App/pages/index.html";
    return;
  }

  // Determine redirect URL based on user role/organization
  let redirectUrl = "/App/pages/index.html";

  if (userInfo.role) {
    switch (userInfo.role.toLowerCase()) {
      case "admin":
      case "nhq_admin":
        redirectUrl = "/App/pages/nhq-dashboard.html";
        break;
      case "province_lead":
        redirectUrl = "/App/pages/province-dashboard.html";
        break;
      case "district_lead":
        redirectUrl = "/App/pages/district-dashboard.html";
        break;
      case "branch_admin":
        redirectUrl = "/App/pages/branch-dashboard.html";
        break;
      default:
        redirectUrl = "/App/pages/index.html";
    }
  }

  window.location.href = redirectUrl;
}

// ==================== Sign Up ====================

/**
 * Handle sign-up form submission
 */
function handleSignUp() {
  // Handle role selection change
  $("#signupRole").change(function () {
    const role = $(this).val();

    // Show/hide province field
    if (role === "Province" || role === "District" || role === "Branch") {
      $("#provinceField").removeClass("d-none");
      loadProvinces();
    } else {
      $("#provinceField").addClass("d-none");
    }

    // Show/hide district field
    if (role === "District" || role === "Branch") {
      $("#districtField").removeClass("d-none");
    } else {
      $("#districtField").addClass("d-none");
    }

    // Show/hide branch field
    if (role === "Branch") {
      $("#branchNameField").removeClass("d-none");
    } else {
      $("#branchNameField").addClass("d-none");
    }

    // Update office summary
    updateOfficeSummary();
  });

  // Handle next button
  $("#nextSignupStep").click(function (event) {
    event.preventDefault();
    validateSignupStep1();
  });

  // Handle back button
  $("#backSignupStep").click(function (event) {
    event.preventDefault();

    // Hide step 2, show step 1
    $("#signupStepTwo").addClass("d-none");
    $("#signupStepOne").removeClass("d-none");

    // Update step indicators
    $("#stepOneBadge").addClass("active");
    $("#stepTwoBadge").removeClass("active");
  });

  // Handle form submission
  $("#signupForm").submit(function (event) {
    event.preventDefault();
    submitSignupForm();
  });
}

/**
 * Load provinces for dropdown
 */
function loadProvinces() {
  api
    .get("/public/provinces")
    .then(function (response) {
      if (response.success && response.data) {
        const $select = $("#provinceSelect");
        $select.empty();
        $select.append('<option value="">Select province</option>');

        response.data.forEach(function (province) {
          $select.append(
            $("<option></option>")
              .attr("value", province.id)
              .text(province.name),
          );
        });
      }
    })
    .catch(function (error) {
      console.error("Error loading provinces:", error);
    });
}

/**
 * Load districts for selected province
 */
function loadDistricts(provinceId) {
  api
    .post("/public/districts", { province_id: provinceId })
    .then(function (response) {
      if (response.success && response.data) {
        const $select = $("#districtSelect");
        $select.empty();
        $select.append('<option value="">Select district</option>');

        response.data.forEach(function (district) {
          $select.append(
            $("<option></option>")
              .attr("value", district.id)
              .text(district.name),
          );
        });
      }
    })
    .catch(function (error) {
      console.error("Error loading districts:", error);
    });
}

/**
 * Update office summary display
 */
function updateOfficeSummary() {
  const role = $("#signupRole").val();
  const province = $("#provinceSelect").find("option:selected").text();
  const district = $("#districtSelect").find("option:selected").text();
  const branch = $("#branchName").val();

  let summary = role;

  if (province && province !== "Select province") {
    summary += " - " + province;
  }

  if (district && district !== "Select district") {
    summary += " - " + district;
  }

  if (branch) {
    summary += " - " + branch;
  }

  $("#officeSummary").val(summary);
}

/**
 * Validate signup step 1
 */
function validateSignupStep1() {
  const secretaryName = $("#secretaryName").val().trim();
  const email = $("#churchEmail").val().trim();
  const role = $("#signupRole").val();
  const province = $("#provinceSelect").val();
  const district = $("#districtSelect").val();
  const branch = $("#branchName").val().trim();

  // Validate required fields
  if (!secretaryName) {
    showAuthStatus("Please enter secretary name", "danger");
    return;
  }

  if (!email || !isValidEmail(email)) {
    showAuthStatus("Please enter a valid email address", "danger");
    return;
  }

  if (!role) {
    showAuthStatus("Please select a role", "danger");
    return;
  }

  if (
    (role === "Province" || role === "District" || role === "Branch") &&
    !province
  ) {
    showAuthStatus("Please select a province", "danger");
    return;
  }

  if ((role === "District" || role === "Branch") && !district) {
    showAuthStatus("Please select a district", "danger");
    return;
  }

  if (role === "Branch" && !branch) {
    showAuthStatus("Please enter branch name", "danger");
    return;
  }

  // Move to step 2
  $("#signupStepOne").addClass("d-none");
  $("#signupStepTwo").removeClass("d-none");

  // Update step indicators
  $("#stepOneBadge").removeClass("active");
  $("#stepTwoBadge").addClass("active");
}

/**
 * Submit signup form
 */
function submitSignupForm() {
  const secretaryName = $("#secretaryName").val().trim();
  const email = $("#churchEmail").val().trim();
  const password = $("#password").val();
  const confirmPassword = $("#confirmPassword").val();

  // Validate password fields
  if (!password) {
    showAuthStatus("Please enter a password", "danger");
    return;
  }

  if (!confirmPassword) {
    showAuthStatus("Please confirm your password", "danger");
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

  // Validate password strength
  if (!isStrongPassword(password)) {
    showAuthStatus(
      "Password must contain uppercase, lowercase, number and special character",
      "danger",
    );
    return;
  }

  // Disable submit button
  const $btn = $("#signupForm").find('button[type="submit"]');
  const originalText = $btn.text();
  $btn.prop("disabled", true).text("Creating account...");

  // Extract name parts
  const nameParts = secretaryName.trim().split(" ");
  const firstName = nameParts[0];
  const lastName = nameParts.slice(1).join(" ") || "User";

  // Prepare registration data
  const registrationData = {
    email: email,
    password: password,
    first_name: firstName,
    last_name: lastName,
    role: $("#signupRole").val(),
    province_id: $("#provinceSelect").val() || null,
    district_id: $("#districtSelect").val() || null,
    branch_name: $("#branchName").val() || null,
  };

  // Make API request
  api
    .post("/auth/register", registrationData)
    .then(function (response) {
      if (response.success) {
        showAuthStatus(
          "Account created successfully! Redirecting to login...",
          "success",
        );

        // Clear form
        $("#signupForm")[0].reset();

        // Redirect to signin tab after delay
        setTimeout(function () {
          $("#signInTab").click();
          $("#email").val(email).focus();
        }, AUTH_CONFIG.REDIRECT_DELAY);
      } else {
        showAuthStatus(response.message || "Registration failed", "danger");
        $btn.prop("disabled", false).text(originalText);
      }
    })
    .catch(function (error) {
      console.error("Error during registration:", error);
      showAuthStatus(
        "An error occurred during registration. Please try again later.",
        "danger",
      );
      $btn.prop("disabled", false).text(originalText);
    });
}

/**
 * Handle province selection change
 */
$(document).on("change", "#provinceSelect", function () {
  const provinceId = $(this).val();
  if (provinceId) {
    loadDistricts(provinceId);
  }
  updateOfficeSummary();
});

/**
 * Handle district selection change
 */
$(document).on("change", "#districtSelect", function () {
  updateOfficeSummary();
});

/**
 * Handle branch name change
 */
$(document).on("input", "#branchName", function () {
  updateOfficeSummary();
});

// ==================== Password Reset ====================

/**
 * Handle forgot password flow
 */
function handleForgotPassword() {
  // Show forgot password panel
  $("#forgotPasswordToggle").click(function (event) {
    event.preventDefault();
    $("#forgotPasswordPanel").removeClass("d-none");
    $("#resetEmail").focus();
  });

  // Cancel forgot password
  $("#cancelForgotPassword").click(function (event) {
    event.preventDefault();
    closeForgotPasswordPanel();
  });

  // Submit forgot password form
  $("#forgotPasswordForm").submit(function (event) {
    event.preventDefault();
    requestPasswordReset();
  });
}

/**
 * Request password reset
 */
function requestPasswordReset() {
  const email = $("#resetEmail").val().trim();
  const password = $("#resetPassword").val();
  const confirmPassword = $("#resetConfirmPassword").val();

  // Validate email
  if (!email || !isValidEmail(email)) {
    showAuthStatus("Please enter a valid email address", "danger");
    return;
  }

  // Validate passwords
  if (!password || !confirmPassword) {
    showAuthStatus("Please fill in all password fields", "danger");
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
  const $btn = $("#forgotPasswordForm").find('button[type="submit"]');
  const originalText = $btn.text();
  $btn.prop("disabled", true).text("Sending reset link...");

  // Make API request to request password reset
  api
    .post("/auth/request-password-reset", { email: email })
    .then(function (response) {
      if (response.success) {
        showAuthStatus(
          "Password reset email has been sent. Please check your inbox and follow the link to reset your password.",
          "success",
        );

        // Close panel and clear form after delay
        setTimeout(function () {
          closeForgotPasswordPanel();
          $btn.prop("disabled", false).text(originalText);
        }, 2000);
      } else {
        showAuthStatus(
          response.message || "Failed to request password reset",
          "danger",
        );
        $btn.prop("disabled", false).text(originalText);
      }
    })
    .catch(function (error) {
      console.error("Error during password reset request:", error);
      showAuthStatus("An error occurred. Please try again later.", "danger");
      $btn.prop("disabled", false).text(originalText);
    });
}

/**
 * Close forgot password panel
 */
function closeForgotPasswordPanel() {
  $("#forgotPasswordPanel").addClass("d-none");
  $("#forgotPasswordForm")[0].reset();
}

// ==================== Tab Switching ====================

/**
 * Handle authentication tab switching
 */
function handleTabSwitching() {
  $(".auth-tab").click(function (event) {
    event.preventDefault();

    const targetPanel = $(this).data("auth-target");

    // Remove active class from all tabs
    $(".auth-tab").removeClass("active");

    // Add active class to clicked tab
    $(this).addClass("active");

    // Hide all panels
    $(".auth-panel-section").addClass("d-none");

    // Show target panel
    $("#" + targetPanel).removeClass("d-none");

    // Clear any previous messages
    $("#authStatus").addClass("d-none");
  });
}

// ==================== Session Management ====================

/**
 * Check if user is already logged in
 */
function checkExistingSession() {
  const token = getAuthToken();

  if (token && !isTokenExpired()) {
    // User is already logged in, redirect to dashboard
    // Only do this if we're on the authentication page
    if (
      window.location.pathname.includes("signin") ||
      window.location.pathname.includes("auth")
    ) {
      redirectToDashboard();
    }
  }
}

/**
 * Check token expiry
 */
function checkTokenExpiry() {
  const expiry = localStorage.getItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);

  if (expiry && isTokenExpired()) {
    // Token has expired, clear auth data
    clearAuthData();
    showAuthStatus(
      "Your session has expired. Please sign in again.",
      "warning",
    );
  }
}

/**
 * Setup auto token refresh
 */
function setupTokenRefresh() {
  setInterval(function () {
    if (isTokenNearExpiry()) {
      refreshToken();
    }
  }, 60000); // Check every minute
}

/**
 * Check if token is near expiry
 */
function isTokenNearExpiry() {
  const expiry = localStorage.getItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);

  if (!expiry) {
    return false;
  }

  const now = Date.now();
  const timeUntilExpiry = parseInt(expiry) - now;

  return timeUntilExpiry < AUTH_CONFIG.TOKEN_REFRESH_THRESHOLD * 1000;
}

/**
 * Refresh authentication token
 */
function refreshToken() {
  const refreshToken = localStorage.getItem(AUTH_CONFIG.REFRESH_TOKEN_KEY);

  if (!refreshToken) {
    clearAuthData();
    return;
  }

  api
    .post("/auth/refresh-token", { refresh_token: refreshToken })
    .then(function (response) {
      if (response.success) {
        storeAuthToken(response);
      } else {
        clearAuthData();
      }
    })
    .catch(function (error) {
      console.error("Error refreshing token:", error);
      clearAuthData();
    });
}

/**
 * Check if token is expired
 */
function isTokenExpired() {
  const expiry = localStorage.getItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);

  if (!expiry) {
    return false;
  }

  return Date.now() > parseInt(expiry);
}

// ==================== Logout ====================

/**
 * Logout user
 */
function logout() {
  // Log the logout action
  logActivity("logout", "User logged out");

  // Clear authentication data
  clearAuthData();

  // Show logout message
  showAuthStatus("You have been logged out successfully.", "info");

  // Redirect to signin page
  setTimeout(function () {
    window.location.href = "/App/pages/signin.html";
  }, 1000);
}

/**
 * Clear all authentication data
 */
function clearAuthData() {
  localStorage.removeItem(AUTH_CONFIG.TOKEN_KEY);
  localStorage.removeItem(AUTH_CONFIG.USER_INFO_KEY);
  localStorage.removeItem(AUTH_CONFIG.REFRESH_TOKEN_KEY);
  localStorage.removeItem(AUTH_CONFIG.TOKEN_EXPIRY_KEY);
}

// ==================== Utility Functions ====================

/**
 * Get stored authentication token
 */
function getAuthToken() {
  return localStorage.getItem(AUTH_CONFIG.TOKEN_KEY);
}

/**
 * Get current user info
 */
function getCurrentUser() {
  const userInfo = localStorage.getItem(AUTH_CONFIG.USER_INFO_KEY);
  return userInfo ? JSON.parse(userInfo) : null;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
  return getAuthToken() && !isTokenExpired();
}

/**
 * Validate email format
 *
 * @param {string} email Email to validate
 * @returns {boolean}
 */
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Validate password strength
 * Must contain: uppercase, lowercase, number, and special character
 *
 * @param {string} password Password to validate
 * @returns {boolean}
 */
function isStrongPassword(password) {
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

  return hasUppercase && hasLowercase && hasNumber && hasSpecialChar;
}

/**
 * Display authentication status message
 *
 * @param {string} message Message to display
 * @param {string} type Alert type (success, danger, warning, info)
 */
function showAuthStatus(message, type = "info") {
  const $status = $("#authStatus");
  $status
    .removeClass("d-none")
    .removeClass("alert-success alert-danger alert-warning alert-info")
    .addClass("alert-" + type)
    .text(message)
    .show();

  // Auto-hide after 5 seconds for success messages
  if (type === "success") {
    setTimeout(function () {
      $status.fadeOut();
    }, 5000);
  }
}

/**
 * Log user activity (for audit trail)
 *
 * @param {string} action Action performed
 * @param {string} details Additional details
 */
function logActivity(action, details) {
  try {
    const user = getCurrentUser();
    const timestamp = new Date().toISOString();

    // Send activity log to backend
    api
      .post("/audit/log", {
        action: action,
        details: details,
        user_id: user ? user.id : null,
        timestamp: timestamp,
      })
      .catch(function (error) {
        // Log errors silently - don't interrupt user flow
        console.warn("Could not log activity:", error);
      });
  } catch (e) {
    console.warn("Error in logActivity:", e);
  }
}

/**
 * Setup logout button handler
 */
function setupLogoutHandler() {
  $(document).on("click", "#logoutBtn, .logout-link", function (event) {
    event.preventDefault();
    logout();
  });
}

// Setup logout handler on ready
$(document).ready(function () {
  setupLogoutHandler();
});

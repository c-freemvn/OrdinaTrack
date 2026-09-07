"use strict";

$(document).ready(function () {
  // Handle login form submission
  handleSignIn();

  // Handle password reset flow
  handleForgotPassword();
});

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
          // Store token if provided
          if (response.data && response.data.token) {
            localStorage.setItem("auth_token", response.data.token);
          }

          // Store user info if provided
          if (response.data && response.data.user) {
            localStorage.setItem(
              "user_info",
              JSON.stringify(response.data.user),
            );
          }

          showAuthStatus("Login successful! Redirecting...", "success");

          // Redirect to dashboard after short delay
          setTimeout(function () {
            window.location.href = "/App/pages/index.html";
          }, 1000);
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
 * Handle forgot password flow
 */
function handleForgotPassword() {
  // Show forgot password panel
  $("#forgotPasswordToggle").click(function (event) {
    event.preventDefault();
    $("#forgotPasswordPanel").removeClass("d-none");
  });

  // Cancel forgot password
  $("#cancelForgotPassword").click(function (event) {
    event.preventDefault();
    $("#forgotPasswordPanel").addClass("d-none");
    $("#forgotPasswordForm")[0].reset();
  });

  // Submit forgot password form
  $("#forgotPasswordForm").submit(function (event) {
    event.preventDefault();

    const email = $("#resetEmail").val().trim();
    const newPassword = $("#resetPassword").val();
    const confirmPassword = $("#resetConfirmPassword").val();

    // Validate input
    if (!email || !newPassword || !confirmPassword) {
      showAuthStatus("Please fill in all password reset fields", "danger");
      return;
    }

    if (newPassword !== confirmPassword) {
      showAuthStatus("Passwords do not match", "danger");
      return;
    }

    if (newPassword.length < 8) {
      showAuthStatus("Password must be at least 8 characters long", "danger");
      return;
    }

    // Request password reset
    api
      .post("/auth/request-password-reset", { email: email })
      .then(function (response) {
        if (response.success) {
          showAuthStatus(
            "Password reset email has been sent. Please check your inbox.",
            "success",
          );
          setTimeout(function () {
            $("#forgotPasswordPanel").addClass("d-none");
            $("#forgotPasswordForm")[0].reset();
          }, 2000);
        } else {
          showAuthStatus(
            response.message || "Failed to request password reset",
            "danger",
          );
        }
      })
      .catch(function (error) {
        console.error("Error during password reset request:", error);
        showAuthStatus("An error occurred. Please try again later.", "danger");
      });
  });
}

/**
 * Display authentication status message
 *
 * @param {string} message Message to display
 * @param {string} type Alert type (success, danger, warning, info)
 */
function showAuthStatus(message, type) {
  const $status = $("#authStatus");
  $status
    .removeClass("d-none")
    .removeClass("alert-success alert-danger alert-warning alert-info")
    .addClass("alert-" + type)
    .text(message)
    .show();
}

const AUTH_STORAGE_KEY = "esocsAuthAccounts";
const AUTH_SESSION_KEY = "esocsCurrentUser";

const PROVINCE_DISTRICT_MAP = {
  "DIOBU PROVINCIAL HQ": [
    "DIOBU CENTRAL",
    "DIOBU",
    "ENEKA",
    "OGBA/EGBEMA",
    "DIOBU SOUTH"
  ],
  "PORT HARCOURT PROVINCIAL HQ": [
    "OGBUMNUABALI",
    "PHC CENTRAL",
    "NEW PHC - ABULOMA",
    "RUMUKALAGBOR",
    "NKORO",
    "IKWERRE CENTRAL",
    "BAKANA",
    "APARA",
    "OPOBO",
    "OMOKWA CENTRAL",
    "DEGEMA",
    "OTAPHA ABUA",
    "RIVERS WEST"
  ],
  "IGBO PROVINCIAL HQ": [
    "IGBO DISTRICT",
    "ULAKWOR",
    "UMUEBULE DISTRICT",
    "UMUECHEM",
    "OZUZU MBA-ASA",
    "ODUFOR"
  ],
  "AKPOKU BRANCH 1 PROVINCIAL HQ": [
    "OKEHI DISTRICT 1",
    "OKEHI DISTRICT 2",
    "ULAKWO",
    "OAJU",
    "OMUMA",
    "AKWUKABI/OBIBI",
    "UMUSELEM 1",
    "UMUSELEM 2",
    "NDASHI",
    "MBA",
    "OBIOHA"
  ],
  "REO PROVINCIAL HQ": [
    "RUNDELE",
    "ODEGU",
    "EMOHUA",
    "ODEGU CENTRAL",
    "EMOHUA CENTRAL",
    "OGBAKIRI",
    "ALIMINI",
    "RUMUEKPE"
  ],
  "RUMUOMASI PROVINCIAL HQ": [
    "RUMUOKWURUSI",
    "ELEKAHIA",
    "OILMILL",
    "OKORO NU ODO",
    "OGINIGBA",
    "RUMUOGBA",
    "OROIGWE",
    "IRIEBE"
  ],
  "OKRIKA PROVINCIAL HQ": [
    "OKRIKA CENTRAL DISTRICT",
    "OGU",
    "OKUJAGU",
    "ISAKA",
    "OGU CENTRAL",
    "OKURU"
  ],
  "IKWERRE PROVINCIAL HQ": [
    "ISIOKPO",
    "ELELE",
    "UBIMA",
    "OZUAHA",
    "OMADEME",
    "OMAGWA",
    "AGWAWIRE",
    "IGWURUTA"
  ],
  "YENEGOA PROVINCIAL HQ": [
    "AGUDAMA",
    "NEMBE",
    "RIVER NUN",
    "RIVER NUN CENTRAL"
  ],
  "AHOADA PROVINCIAL HQ": [
    "IGBUDUYA",
    "UKPATA",
    "UBIE",
    "AKOH",
    "ABUA"
  ],
  "ELEME PROVINCIAL HQ": [
    "ONNE",
    "AKPAJO",
    "ODIDO CENTRAL",
    "NCHIA CENTRAL"
  ],
  "OBIO/AKPOR PROVINCIAL HQ": [
    "OBIO",
    "AKPOR CENTRAL",
    "OBIO CENTRAL",
    "AKPOR"
  ],
  "ANDONI PROVINCIAL HQ": [
    "UNYEADA",
    "NGO",
    "OLUK-AMA",
    "EBUKUMA"
  ],
  "BONNY PROVINCIAL HQ": [
    "FINIMA"
  ],
  "OGONI PROVINCIAL HQ": [
    "GOKANA",
    "TAI"
  ],
  "IBAA PROVINCIAL HQ": [
    "OBELLE",
    "MGBUOSIMINI",
    "IBAA CENTRAL",
    "AKPABU"
  ]
};

const PROVINCES = Object.keys(PROVINCE_DISTRICT_MAP);

document.addEventListener("DOMContentLoaded", () => {
  if (document.body.dataset.page !== "auth") return;

  initializeAuthPage();
});

function initializeAuthPage() {
  populateProvinceOptions();
  bindAuthTabs();
  bindSignupFlow();
  bindSigninFlow();
  bindForgotPasswordFlow();

  if (window.location.hash === "#signup" || window.location.pathname === "/signup") {
    activateAuthPanel("signupPanel");
  }
}

function bindAuthTabs() {
  document.querySelectorAll(".auth-tab").forEach(button => {
    button.addEventListener("click", () => {
      activateAuthPanel(button.dataset.authTarget);
    });
  });
}

function activateAuthPanel(targetId) {
  const isSignup = targetId === "signupPanel";

  document.querySelectorAll(".auth-tab").forEach(button => {
    button.classList.toggle("active", button.dataset.authTarget === targetId);
  });

  document.querySelectorAll(".auth-panel-section").forEach(section => {
    section.classList.toggle("d-none", section.id !== targetId);
  });

  if (isSignup) {
    window.location.hash = "signup";
  } else {
    history.replaceState(null, "", window.location.pathname);
  }

  clearAuthStatus();
}

function bindSignupFlow() {
  const roleSelect = document.getElementById("signupRole");
  const provinceSelect = document.getElementById("provinceSelect");
  const nextButton = document.getElementById("nextSignupStep");
  const backButton = document.getElementById("backSignupStep");
  const signupForm = document.getElementById("signupForm");

  roleSelect?.addEventListener("change", handleRoleChange);
  provinceSelect?.addEventListener("change", handleProvinceChange);
  nextButton?.addEventListener("click", goToPasswordStep);
  backButton?.addEventListener("click", goToDetailsStep);
  signupForm?.addEventListener("submit", handleSignupSubmit);
}

function bindSigninFlow() {
  const signinForm = document.getElementById("signinForm");
  signinForm?.addEventListener("submit", handleSigninSubmit);
}

function bindForgotPasswordFlow() {
  const toggleButton = document.getElementById("forgotPasswordToggle");
  const cancelButton = document.getElementById("cancelForgotPassword");
  const forgotForm = document.getElementById("forgotPasswordForm");

  toggleButton?.addEventListener("click", () => {
    document.getElementById("forgotPasswordPanel")?.classList.toggle("d-none");
    clearAuthStatus();
  });

  cancelButton?.addEventListener("click", () => {
    document.getElementById("forgotPasswordPanel")?.classList.add("d-none");
    forgotForm?.reset();
    clearAuthStatus();
  });

  forgotForm?.addEventListener("submit", handleForgotPasswordSubmit);
}

function populateProvinceOptions() {
  const provinceSelect = document.getElementById("provinceSelect");
  if (!provinceSelect) return;

  provinceSelect.innerHTML = '<option value="">Select province</option>';
  PROVINCES.forEach(province => {
    const option = document.createElement("option");
    option.value = province;
    option.textContent = province;
    provinceSelect.appendChild(option);
  });
}

function handleRoleChange() {
  const role = document.getElementById("signupRole")?.value;
  const provinceField = document.getElementById("provinceField");
  const districtField = document.getElementById("districtField");
  const branchNameField = document.getElementById("branchNameField");
  const officeSummaryField = document.getElementById("officeSummaryField");
  const officeSummary = document.getElementById("officeSummary");
  const districtSelect = document.getElementById("districtSelect");

  provinceField?.classList.toggle("d-none", role !== "Province" && role !== "District" && role !== "Branch");
  districtField?.classList.toggle("d-none", role !== "District" && role !== "Branch");
  branchNameField?.classList.toggle("d-none", role !== "Branch");
  officeSummaryField?.classList.toggle("d-none", role === "Branch" || !role);

  if (districtSelect) {
    districtSelect.innerHTML = '<option value="">Select district</option>';
  }

  if (role === "NHQ" && officeSummary) {
    officeSummary.value = "ESOCS NATIONAL HQ";
  } else if (role !== "Branch" && officeSummary) {
    officeSummary.value = "";
  }

  if (role === "Province") {
    handleProvinceChange();
  }
}

function handleProvinceChange() {
  const role = document.getElementById("signupRole")?.value;
  const province = document.getElementById("provinceSelect")?.value || "";
  const districtSelect = document.getElementById("districtSelect");
  const officeSummary = document.getElementById("officeSummary");

  if (districtSelect) {
    districtSelect.innerHTML = '<option value="">Select district</option>';

    (PROVINCE_DISTRICT_MAP[province] || []).forEach(district => {
      const option = document.createElement("option");
      option.value = district;
      option.textContent = district;
      districtSelect.appendChild(option);
    });
  }

  if (officeSummary && role === "Province") {
    officeSummary.value = province;
  }
}

function handleDistrictSelection() {
  const role = document.getElementById("signupRole")?.value;
  const district = document.getElementById("districtSelect")?.value || "";
  const officeSummary = document.getElementById("officeSummary");

  if (officeSummary && role === "District") {
    officeSummary.value = district;
  }
}

document.addEventListener("change", event => {
  if (event.target?.id === "districtSelect") {
    handleDistrictSelection();
  }
});

function goToPasswordStep() {
  const details = collectSignupDetails();
  const validationMessage = validateSignupDetails(details);

  if (validationMessage) {
    showAuthStatus(validationMessage, "danger");
    return;
  }

  document.getElementById("signupStepOne")?.classList.add("d-none");
  document.getElementById("signupStepTwo")?.classList.remove("d-none");
  document.getElementById("stepOneBadge")?.classList.remove("active");
  document.getElementById("stepTwoBadge")?.classList.add("active");
  clearAuthStatus();
}

function goToDetailsStep() {
  document.getElementById("signupStepOne")?.classList.remove("d-none");
  document.getElementById("signupStepTwo")?.classList.add("d-none");
  document.getElementById("stepOneBadge")?.classList.add("active");
  document.getElementById("stepTwoBadge")?.classList.remove("active");
  clearAuthStatus();
}

function collectSignupDetails() {
  return {
    secretaryName: document.getElementById("secretaryName")?.value.trim() || "",
    email: document.getElementById("churchEmail")?.value.trim().toLowerCase() || "",
    role: document.getElementById("signupRole")?.value || "",
    province: document.getElementById("provinceSelect")?.value || "",
    district: document.getElementById("districtSelect")?.value || "",
    branchName: document.getElementById("branchName")?.value.trim() || ""
  };
}

function validateSignupDetails(details) {
  if (!details.secretaryName || !details.email || !details.role) {
    return "Please fill the secretary name, church email, and role.";
  }

  if (details.role === "NHQ") {
    const existingNhq = getStoredAccounts().find(account => account.role === "NHQ");
    if (existingNhq) return "National HQ account already exists.";
    return "";
  }

  if (!details.province) {
    return "Please select a province.";
  }

  if ((details.role === "District" || details.role === "Branch") && !details.district) {
    return "Please select a district.";
  }

  if (details.role === "Branch" && !details.branchName) {
    return "Please enter the branch name.";
  }

  return "";
}

function handleSignupSubmit(event) {
  event.preventDefault();

  const details = collectSignupDetails();
  const password = document.getElementById("signupPassword")?.value || "";
  const confirmPassword = document.getElementById("confirmSignupPassword")?.value || "";
  const validationMessage = validateSignupDetails(details);

  if (validationMessage) {
    showAuthStatus(validationMessage, "danger");
    return;
  }

  if (!password || password.length < 4) {
    showAuthStatus("Please create a password with at least 4 characters.", "danger");
    return;
  }

  if (password !== confirmPassword) {
    showAuthStatus("Passwords do not match.", "danger");
    return;
  }

  const accounts = getStoredAccounts();
  const emailExists = accounts.some(account => account.email === details.email);

  if (emailExists) {
    showAuthStatus("That church email is already registered.", "danger");
    return;
  }

  const account = {
    id: `acct_${Date.now()}`,
    secretaryName: details.secretaryName,
    email: details.email,
    password,
    role: details.role,
    province: details.province || null,
    district: details.district || null,
    branchName: details.role === "Branch" ? details.branchName : null,
    officeName: getOfficeName(details)
  };

  accounts.push(account);
  localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(accounts));
  sessionStorage.setItem(AUTH_SESSION_KEY, JSON.stringify(account));

  showAuthStatus("Account created successfully. Redirecting to your dashboard...", "success");

  setTimeout(() => {
    window.location.href = getDashboardPath(account.role);
  }, 700);
}

function handleSigninSubmit(event) {
  event.preventDefault();

  const email = document.getElementById("signinEmail")?.value.trim().toLowerCase() || "";
  const password = document.getElementById("signinPassword")?.value || "";
  const account = getStoredAccounts().find(entry => entry.email === email && entry.password === password);

  if (!account) {
    showAuthStatus("Invalid church email or password.", "danger");
    return;
  }

  sessionStorage.setItem(AUTH_SESSION_KEY, JSON.stringify(account));
  showAuthStatus("Sign in successful. Redirecting...", "success");

  setTimeout(() => {
    window.location.href = getDashboardPath(account.role);
  }, 500);
}

function handleForgotPasswordSubmit(event) {
  event.preventDefault();

  const resetEmail = document.getElementById("resetEmail")?.value.trim().toLowerCase() || "";
  const resetPassword = document.getElementById("resetPassword")?.value || "";
  const confirmResetPassword = document.getElementById("resetConfirmPassword")?.value || "";
  const accounts = getStoredAccounts();
  const accountIndex = accounts.findIndex(entry => entry.email === resetEmail);

  if (accountIndex === -1) {
    showAuthStatus("No account was found for that church email.", "danger");
    return;
  }

  if (!resetPassword || resetPassword.length < 4) {
    showAuthStatus("Please enter a new password with at least 4 characters.", "danger");
    return;
  }

  if (resetPassword !== confirmResetPassword) {
    showAuthStatus("New password and confirmation do not match.", "danger");
    return;
  }

  accounts[accountIndex].password = resetPassword;
  localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(accounts));

  document.getElementById("forgotPasswordForm")?.reset();
  document.getElementById("forgotPasswordPanel")?.classList.add("d-none");
  showAuthStatus("Password updated successfully. You can now sign in.", "success");
}

function getStoredAccounts() {
  try {
    return JSON.parse(localStorage.getItem(AUTH_STORAGE_KEY) || "[]");
  } catch (error) {
    return [];
  }
}

function getOfficeName(details) {
  if (details.role === "NHQ") return "ESOCS NATIONAL HQ";
  if (details.role === "Province") return details.province;
  if (details.role === "District") return details.district;
  return details.branchName;
}

function getDashboardPath(role) {
  if (role === "NHQ") return "/nhq-dashboard";
  if (role === "Province") return "/province-dashboard";
  if (role === "District") return "/district-dashboard";
  return "/branch-dashboard";
}

function showAuthStatus(message, variant) {
  const status = document.getElementById("authStatus");
  if (!status) return;

  status.className = `alert alert-${variant} mt-3`;
  status.textContent = message;
  status.classList.remove("d-none");
}

function clearAuthStatus() {
  const status = document.getElementById("authStatus");
  if (!status) return;

  status.className = "alert d-none mt-3";
  status.textContent = "";
}

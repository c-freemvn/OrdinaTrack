/**
 * Signup Success Page
 *
 * Personalises the welcome message, account summary and next steps using
 * the details auth-api.js saves after a successful registration. Without
 * them (e.g. a direct visit) the page keeps its generic copy.
 */

const SIGNUP_SUCCESS_KEY = "signup_success";

// Role-specific first task, based on each role's dashboard pages
const ROLE_GUIDES = {
  NHQ: {
    label: "National HQ",
    title: "Oversee the national picture",
    copy: "Review user accounts, national reports and system settings from your NHQ dashboard.",
  },
  Province: {
    label: "Province",
    title: "Get to know your province",
    copy: "Review the districts under your province, keep member records up to date and follow provincial reports.",
  },
  District: {
    label: "District",
    title: "Organise your district",
    copy: "Add the branches in your district, manage members and record district activities.",
  },
  Branch: {
    label: "Branch",
    title: "Start recording branch activity",
    copy: "Add your members, record activities and submit your branch reports.",
  },
};

document.addEventListener("DOMContentLoaded", () => {
  const details = readSignupDetails();
  if (details) personalisePage(details);

  // Move focus to the heading so screen readers announce the success
  document.getElementById("successTitle")?.focus();
});

function readSignupDetails() {
  try {
    return JSON.parse(sessionStorage.getItem(SIGNUP_SUCCESS_KEY) || "null");
  } catch (e) {
    return null;
  }
}

function officeFor(details) {
  switch (details.role) {
    case "NHQ":
      return "ESOCS National HQ";
    case "Province":
      return details.province;
    case "District":
      return [details.district, details.province].filter(Boolean).join(" · ");
    case "Branch":
      return [details.branch, details.district, details.province]
        .filter(Boolean)
        .join(" · ");
    default:
      return "";
  }
}

function personalisePage(details) {
  const guide = ROLE_GUIDES[details.role];

  if (details.first_name) {
    setText("successTitle", `Welcome aboard, ${details.first_name}!`);
  }
  if (guide) {
    setText(
      "successLead",
      `Your ${guide.label} office account is ready. Here's how to get started.`,
    );
    setText("roleStepTitle", guide.title);
    setText("roleStepCopy", guide.copy);
  }
  setText("signinStepEmail", details.email);

  // Fill the summary, hiding any row we have no value for
  const rows = {
    summaryName: details.name,
    summaryRole: guide ? guide.label : details.role,
    summaryEmail: details.email,
    summaryOffice: officeFor(details),
  };
  let hasRow = false;
  Object.entries(rows).forEach(([id, value]) => {
    const dd = document.getElementById(id);
    if (!dd) return;
    dd.textContent = value || "";
    dd.parentElement.hidden = !value;
    hasRow = hasRow || Boolean(value);
  });
  document.getElementById("successSummary").hidden = !hasRow;
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el && value) el.textContent = value;
}

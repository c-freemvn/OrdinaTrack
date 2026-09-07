// Tracks which member is currently being edited in modals.
let currentEditIndex = null;
// Tracks which member is currently selected for recommendation.
let currentRecommendIndex = null;

// Resolve active dashboard role from the page first so one script can support multiple HQ levels.
const userRole = document.body?.dataset.role || "";
const currentAuthAccount = getCurrentAuthAccount();
let currentAnalyticsFilter = "30d";
const availableProvincialHqs = ["Okrika Province"];
const supportPageIdsByRole = {
  Branch: {
    announcements: "announcements",
    feedback: "feedback",
    help: "help",
    settings: "configuration"
  },
  District: {
    announcements: "district-announcements",
    feedback: "district-feedback",
    help: "district-help",
    settings: "district-settings"
  },
  Province: {
    announcements: "province-announcements",
    feedback: "province-feedback",
    help: "province-help",
    settings: "province-settings"
  },
  NHQ: {
    announcements: "nhq-announcements",
    feedback: "nhq-feedback",
    help: "nhq-help",
    settings: "nhq-settings"
  }
};

// Branch-level member dataset (in-memory for now).
let branchMembers = [
  { name: "John Doe", sex: "Male", rank: "Worker", status: "", recommendedRank: null },
  { name: "Mary James", sex: "Female", rank: "Leader", status: "", recommendedRank: null },
  { name: "Paul Isaac", sex: "Male", rank: "Assistant", status: "", recommendedRank: null }
];

// Province-level member dataset (kept separate from branch records).
let provinceMembers = [
  { name: "Apostle Femi Johnson", sex: "Male", rank: "Apostle", status: "", recommendedRank: null },
  { name: "Mother Grace Bassey", sex: "Female", rank: "Mother-In-Israel", status: "", recommendedRank: null },
  { name: "Evangelist Ruth Cole", sex: "Female", rank: "Prophetess", status: "", recommendedRank: null }
];

// District-level member dataset for Central District Headquarters.
let districtMembers = [
  { name: "Senior Leader Tunde Adeyemi", sex: "Male", rank: "Leader", status: "", recommendedRank: null },
  { name: "Lady Leader Esther Bello", sex: "Female", rank: "Lady Leader", status: "", recommendedRank: null },
  { name: "Evangelist Peter Nnaji", sex: "Male", rank: "Evangelist", status: "", recommendedRank: null }
];

// National Headquarters member dataset.
let nhqMembers = [
  {
    name: "Patriarch Daniel Akin",
    sex: "Male",
    rank: "Senior Apostle General",
    status: "",
    recommendedRank: null,
    email: "daniel.akin@odina.local",
    phone: "+234 803 000 1001",
    nationality: "Nigerian",
    occupation: "Clergy Administrator",
    band: "Cherub",
    association: "King David Association",
    maritalStatus: "Married",
    marriageYear: "1992",
    ordinationHistory: [
      { rank: "Leader", year: "1996" },
      { rank: "Pastor", year: "2003" },
      { rank: "Apostle", year: "2011" },
      { rank: "Senior Apostle General", year: "2021" }
    ]
  },
  {
    name: "Mother Deborah Salami",
    sex: "Female",
    rank: "Senior Mother-In-Israel",
    status: "",
    recommendedRank: null,
    email: "deborah.salami@odina.local",
    phone: "+234 803 000 1002",
    nationality: "Nigerian",
    occupation: "Education Consultant",
    band: "Seraph",
    association: "Zion Daughters",
    maritalStatus: "Married",
    marriageYear: "1988",
    ordinationHistory: [
      { rank: "Lady Leader", year: "1995" },
      { rank: "Mother-In-Israel", year: "2008" },
      { rank: "Senior Mother-In-Israel", year: "2019" }
    ]
  },
  {
    name: "Apostle Joseph Nwosu",
    sex: "Male",
    rank: "Apostle",
    status: "",
    recommendedRank: null,
    email: "joseph.nwosu@odina.local",
    phone: "+234 803 000 1003",
    nationality: "Nigerian",
    occupation: "Mission Coordinator",
    band: "Cherub",
    association: "MZYS",
    maritalStatus: "Single",
    marriageYear: "",
    ordinationHistory: [
      { rank: "Brother", year: "2007" },
      { rank: "Evangelist", year: "2014" },
      { rank: "Apostle", year: "2022" }
    ]
  }
];

// Rank options used in add/edit member forms.
const maleRanks = [
  "Baba Aladura", "Deputy Baba Aladura", "Senior Apostle General",
  "Supervising Apostle General", "Apostle General", "Special Senior Apostle",
  "Senior Apostle", "Supervising Apostle", "Apostle", "Prophet", "Evangelist",
  "Pastor", "Rabbi", "Leader", "Aladura", "Army of Salvation", "Fogo Olorun Han",
  "Baba Nla Mejila", "Army of Christ", "Brother"
];

const femaleRanks = [
  "Mother Captain", "Mother Seraph", "Mother Cherub", "Mother (Her Eminence)",
  "Senior Mother-In-Israel", "Mother-In-Israel", "Prophetess", "Mary", "Deborah",
  "Dorcas", "Lady Leader", "Lady Aladura", "Army of Salvation", "Army of Christ",
  "Esther", "Martha", "Queen Sheba", "Sister"
];

// Province-level incoming submissions from branches (in-memory mock data).
let phqSubmissions = [
  { district: "Central District", name: "Ayo Daniel", currentRank: "Leader", recommendedRank: "Pastor", status: "pending" },
  { district: "Western District", name: "Ruth Bamidele", currentRank: "Lady Leader", recommendedRank: "Mother-In-Israel", status: "pending" },
  { district: "Northern District", name: "Samuel Ade", currentRank: "Evangelist", recommendedRank: "Prophet", status: "approved" }
];

// District-level incoming submissions from branches (in-memory mock data).
let districtSubmissions = [
  { branch: "Ikeja Central", name: "Ayo Daniel", currentRank: "Leader", recommendedRank: "Pastor", status: "pending" },
  { branch: "Maryland Branch", name: "Ruth Bamidele", currentRank: "Lady Leader", recommendedRank: "Mother-In-Israel", status: "approved" },
  { branch: "Yaba Outreach", name: "Samuel Ade", currentRank: "Evangelist", recommendedRank: "Prophet", status: "queued" }
];

const confirmationWindowMs = 3 * 24 * 60 * 60 * 1000;

window.addEventListener("DOMContentLoaded", () => {
  // Show only the content section that matches the active role.
  document.querySelectorAll(".dashboard-section").forEach(section => {
    if (section.dataset.role !== userRole) section.style.display = "none";
  });

  // Show only the role-specific side navigation group.
  document.querySelectorAll("#sidenav li").forEach(link => {
    if (link.dataset.role !== userRole) link.style.display = "none";
  });

  const roleDisplay = document.getElementById("user-role-display");
  if (roleDisplay) {
    const officeName = getCurrentOfficeName();
    roleDisplay.textContent = officeName ? `Role: ${userRole} | ${officeName}` : `Role: ${userRole}`;
  }

  if (userRole === "Branch") {
    const branchSection = document.querySelector('[data-role="Branch"]');
    if (branchSection) branchSection.style.display = "block";
  }

  // Branch bootstrap flow.
  if (userRole === "Branch") {
    renderTable();
    showPage("overview");
    renderOverviewAnalytics();
  }

  // Province bootstrap flow.
  if (userRole === "Province") {
    renderPHQDashboard();
    showPage("province-overview");
    renderOverviewAnalytics();
  }

  if (userRole === "District") {
    renderDistrictDashboard();
    showPage("district-overview");
    renderOverviewAnalytics();
  }

  if (userRole === "NHQ") {
    renderNHQDashboard();
    showPage("nhq-overview");
    renderOverviewAnalytics();
  }

  initializeSupportWorkspace();
  personalizeDashboardHeading();

  // On mobile, close sidebar after selecting any navigation link.
  document.querySelectorAll("#sidenav .sidebar-link").forEach(link => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 768) {
        document.getElementById("sidebar")?.classList.remove("open");
      }
    });
  });
});

function getSupportPageIds(role = userRole) {
  return supportPageIdsByRole[role] || supportPageIdsByRole.Branch;
}

function getAvailableProvincialHqs() {
  return [...new Set(availableProvincialHqs.filter(Boolean))];
}

function getSupportNarrative(role = userRole) {
  if (role === "NHQ") {
    return {
      announcementLabel: "National broadcast desk",
      announcementHint: "Send policy updates, event notices, or national directives with clear routing.",
      feedbackHint: "Capture policy concerns, queue issues, and product observations from senior desks.",
      helpHint: "Guide national users through queue review, record handling, and escalation procedures.",
      settingsHint: "Control national workflow defaults, visibility rules, and platform preferences."
    };
  }

  if (role === "Province") {
    return {
      announcementLabel: "Provincial messaging desk",
      announcementHint: "Coordinate district communication and prepare clean notices before they move across the network.",
      feedbackHint: "Collect district process concerns, onboarding issues, and review feedback from provincial teams.",
      helpHint: "Support district users with queue handling, confirmation guidance, and escalation contacts.",
      settingsHint: "Manage province review preferences, confirmation defaults, and internal notifications."
    };
  }

  if (role === "District") {
    return {
      announcementLabel: "District message center",
      announcementHint: "Share operational updates, branch reminders, and routing notices from one structured composer.",
      feedbackHint: "Capture branch concerns, review bottlenecks, and onboarding observations for district action.",
      helpHint: "Provide branch support, workflow answers, and district escalation details in one place.",
      settingsHint: "Set district review defaults, queue preferences, and branch-facing controls."
    };
  }

  return {
    announcementLabel: "Branch notice composer",
    announcementHint: "Write clean notices for workers and connected offices using a familiar chat-style layout.",
    feedbackHint: "Collect improvement ideas, issue reports, and operational suggestions from branch users.",
    helpHint: "Offer quick answers, contact points, and simple support requests for branch-level users.",
    settingsHint: "Manage branch workspace preferences, reminders, and local dashboard behavior."
  };
}

function initializeSupportWorkspace() {
  renderSupportPages();
  attachSupportInteractions();
  ensureSidebarLogoutButton();
}

function renderSupportPages() {
  const pageIds = getSupportPageIds();
  const announcementsPage = document.getElementById(pageIds.announcements);
  const feedbackPage = document.getElementById(pageIds.feedback);
  const helpPage = document.getElementById(pageIds.help);
  const settingsPage = document.getElementById(pageIds.settings);

  if (announcementsPage) announcementsPage.innerHTML = getAnnouncementsTemplate();
  if (feedbackPage) feedbackPage.innerHTML = getFeedbackTemplate();
  if (helpPage) helpPage.innerHTML = getHelpTemplate();
  if (settingsPage) settingsPage.innerHTML = getSettingsTemplate();
}

function getAnnouncementsTemplate() {
  const narrative = getSupportNarrative();
  const provinceOptions = getAvailableProvincialHqs()
    .map(name => `<option value="${name}">${name}</option>`)
    .join("");

  return `
    <section class="support-page">
      <div class="support-page-hero">
        <div>
          <span class="support-kicker">Announcements</span>
          <h2>${narrative.announcementLabel}</h2>
          <p>${narrative.announcementHint}</p>
        </div>
        <div class="support-status-pill">
          <i class="bi bi-broadcast-pin"></i>
          <span>Message routing ready</span>
        </div>
      </div>

      <div class="support-grid support-grid-wide">
        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Compose broadcast</h3>
              <p>Choose a target route before writing the message.</p>
            </div>
          </div>

          <form class="support-form" data-announcement-form>
            <div class="support-audience-row">
              <label class="support-audience-pill">
                <input type="radio" name="announcementAudience" value="all" data-announcement-audience checked>
                <span>Send to all</span>
              </label>
              <label class="support-audience-pill">
                <input type="radio" name="announcementAudience" value="provinces" data-announcement-audience>
                <span>To provinces</span>
              </label>
              <label class="support-audience-pill">
                <input type="radio" name="announcementAudience" value="districts" data-announcement-audience>
                <span>To districts</span>
              </label>
              <label class="support-audience-pill">
                <input type="radio" name="announcementAudience" value="specific-province" data-announcement-audience>
                <span>Particular province</span>
              </label>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label" for="announcementTitle">Message title</label>
                <input id="announcementTitle" class="form-control" type="text" placeholder="Ordination schedule update">
              </div>
              <div class="col-md-6">
                <div data-announcement-province-wrap class="d-none">
                  <label class="form-label" for="announcementProvinceSelect">Select provincial HQ</label>
                  <select id="announcementProvinceSelect" class="form-select">
                    <option value="">Choose provincial HQ</option>
                    ${provinceOptions}
                  </select>
                </div>
              </div>
            </div>

            <div class="support-message-shell mt-3">
              <div class="support-message-header">
                <span><i class="bi bi-chat-dots"></i> Text message UI</span>
                <small>160-500 characters recommended</small>
              </div>
              <textarea class="form-control support-message-textarea" rows="6" placeholder="Type the announcement here exactly as you want it delivered to connected offices..."></textarea>
            </div>

            <div class="support-form-actions">
              <button class="btn btn-outline-secondary" type="submit" value="draft">Save Draft</button>
              <button class="btn btn-primary" type="submit" value="send">Send Announcement</button>
            </div>
            <p class="support-inline-status mb-0" data-announcement-status>Ready to draft or send.</p>
          </form>
        </article>

        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Preview thread</h3>
              <p>Broadcasts are presented like concise office messages.</p>
            </div>
          </div>
          <div class="support-thread">
            <div class="support-bubble support-bubble-received">
              <strong>District Desk</strong>
              <span>Reminder: submit approved recommendations before Friday 5:00 PM.</span>
            </div>
            <div class="support-bubble support-bubble-sent">
              <strong>${userRole} Desk</strong>
              <span>Confirmed. A fresh queue review has started and districts will receive the next notice after approval.</span>
            </div>
            <div class="support-bubble support-bubble-received">
              <strong>System routing</strong>
              <span>Audience controls above help you choose all offices, all provinces, all districts, or one provincial HQ.</span>
            </div>
          </div>
        </article>
      </div>
    </section>
  `;
}

function getFeedbackTemplate() {
  const narrative = getSupportNarrative();

  return `
    <section class="support-page">
      <div class="support-page-hero">
        <div>
          <span class="support-kicker">Feedback</span>
          <h2>Structured feedback desk</h2>
          <p>${narrative.feedbackHint}</p>
        </div>
        <div class="support-status-pill">
          <i class="bi bi-clipboard2-pulse"></i>
          <span>Review queue enabled</span>
        </div>
      </div>

      <div class="support-grid support-grid-wide">
        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Submit feedback</h3>
              <p>Use a clear title and choose the most relevant category.</p>
            </div>
          </div>
          <form class="support-form" data-feedback-form>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="feedbackCategory">Category</label>
                <select id="feedbackCategory" class="form-select">
                  <option>Workflow improvement</option>
                  <option>Bug report</option>
                  <option>Data quality issue</option>
                  <option>Access problem</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="feedbackPriority">Priority</label>
                <select id="feedbackPriority" class="form-select">
                  <option>Normal</option>
                  <option>High</option>
                  <option>Urgent</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="feedbackTitle">Feedback title</label>
                <input id="feedbackTitle" class="form-control" type="text" placeholder="Review queue should show branch source more clearly">
              </div>
              <div class="col-12">
                <label class="form-label" for="feedbackMessage">Details</label>
                <textarea id="feedbackMessage" class="form-control" rows="6" placeholder="Describe the issue, its impact, and the ideal improvement."></textarea>
              </div>
            </div>
            <div class="support-form-actions">
              <button class="btn btn-outline-secondary" type="submit" value="draft">Save Note</button>
              <button class="btn btn-primary" type="submit" value="submit">Submit Feedback</button>
            </div>
            <p class="support-inline-status mb-0" data-feedback-status>Feedback is easier to act on when the impact is clearly stated.</p>
          </form>
        </article>

        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Recent items</h3>
              <p>Examples of the kind of feedback that gets resolved faster.</p>
            </div>
          </div>
          <div class="support-list">
            <div class="support-list-item">
              <strong>Queue visibility improvement</strong>
              <span>Need clearer branch origin labels for district approvals.</span>
              <small>High priority</small>
            </div>
            <div class="support-list-item">
              <strong>Member profile data issue</strong>
              <span>Ordination history should remain visible after confirmation.</span>
              <small>Normal priority</small>
            </div>
            <div class="support-list-item">
              <strong>Access support request</strong>
              <span>New desk officer cannot see the announcement panel after sign in.</span>
              <small>Urgent</small>
            </div>
          </div>
        </article>
      </div>
    </section>
  `;
}

function getHelpTemplate() {
  const narrative = getSupportNarrative();

  return `
    <section class="support-page">
      <div class="support-page-hero">
        <div>
          <span class="support-kicker">Get Help</span>
          <h2>Support and guidance workspace</h2>
          <p>${narrative.helpHint}</p>
        </div>
        <div class="support-status-pill">
          <i class="bi bi-life-preserver"></i>
          <span>Help desk available</span>
        </div>
      </div>

      <div class="support-grid">
        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Quick answers</h3>
            </div>
          </div>
          <div class="support-list">
            <div class="support-list-item">
              <strong>How do I trace a stuck record?</strong>
              <span>Open the relevant queue page and review the current status badge before escalating.</span>
            </div>
            <div class="support-list-item">
              <strong>Who handles access issues?</strong>
              <span>Escalate office login issues to the next administrative level or the platform desk.</span>
            </div>
            <div class="support-list-item">
              <strong>When should announcements be used?</strong>
              <span>Use announcements for official notices, policy reminders, and structured operational updates.</span>
            </div>
          </div>
        </article>

        <article class="support-card">
          <div class="support-card-head">
            <div>
              <h3>Open support request</h3>
            </div>
          </div>
          <form class="support-form" data-help-form>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="helpTopic">Help topic</label>
                <select id="helpTopic" class="form-select">
                  <option>Queue issue</option>
                  <option>Record update problem</option>
                  <option>Access/login problem</option>
                  <option>Announcement delivery</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="helpChannel">Preferred response</label>
                <select id="helpChannel" class="form-select">
                  <option>Email response</option>
                  <option>Dashboard follow-up</option>
                  <option>Phone call</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="helpDetails">Describe the issue</label>
                <textarea id="helpDetails" class="form-control" rows="5" placeholder="Summarize what happened, which page is affected, and any steps already taken."></textarea>
              </div>
            </div>
            <div class="support-form-actions">
              <button class="btn btn-primary" type="submit" value="submit">Request Help</button>
            </div>
            <p class="support-inline-status mb-0" data-help-status>Support requests should include the affected page and expected result.</p>
          </form>
        </article>
      </div>
    </section>
  `;
}

function getSettingsTemplate() {
  const narrative = getSupportNarrative();
  const settingsByRole = {
    Branch: [
      {
        title: "Branch workspace",
        items: [
          createToggleSetting("Show member alerts", "Highlight flagged and pending records in branch views.", true),
          createToggleSetting("Announcement notifications", "Receive dashboard prompts when new official notices arrive.", true),
          createSelectSetting("Default member sort", "Choose how branch member tables open by default.", ["Newest updates", "Name A-Z", "Rank level"])
        ]
      },
      {
        title: "Submission flow",
        items: [
          createToggleSetting("Confirm before push", "Ask for confirmation before sending recommendations to district.", true),
          createToggleSetting("Auto-highlight incomplete profiles", "Warn users when key member fields are missing.", true)
        ]
      }
    ],
    District: [
      {
        title: "District review",
        items: [
          createToggleSetting("Require approval notes", "Prompt reviewers to add short notes before final actions.", true),
          createToggleSetting("Branch queue alerts", "Show reminders when branches submit new recommendation batches.", true),
          createSelectSetting("Default queue filter", "Choose the default status shown on district queue pages.", ["Pending first", "Approved first", "All submissions"])
        ]
      },
      {
        title: "Branch coordination",
        items: [
          createToggleSetting("Announcement delivery receipts", "Track whether branch offices have opened district notices.", false),
          createToggleSetting("Escalation reminder banner", "Display reminders when approved items are ready for province forwarding.", true)
        ]
      }
    ],
    Province: [
      {
        title: "Province workflow",
        items: [
          createToggleSetting("Confirmation reminders", "Show prompts for approved members awaiting physical confirmation.", true),
          createToggleSetting("NHQ push confirmation", "Require review confirmation before forwarding records to NHQ.", true),
          createSelectSetting("Default recommendation view", "Choose the opening panel for province review tasks.", ["District submissions", "Physical confirmation", "Overview"])
        ]
      },
      {
        title: "District oversight",
        items: [
          createToggleSetting("District status digest", "Display a quick readiness summary for connected districts.", true),
          createToggleSetting("Announcement escalation copy", "Attach a standard footer to operational province broadcasts.", false)
        ]
      }
    ],
    NHQ: [
      {
        title: "National controls",
        items: [
          createToggleSetting("Province inflow alerts", "Show immediate prompts when new provincial submissions arrive.", true),
          createToggleSetting("National review lock", "Require explicit reviewer confirmation before changing queue status.", true),
          createSelectSetting("Default national queue", "Choose the first queue view for NHQ desks.", ["Province queue", "Overview", "Members data"])
        ]
      },
      {
        title: "Governance settings",
        items: [
          createToggleSetting("Policy notice receipts", "Track whether provincial HQs have opened national announcements.", true),
          createToggleSetting("Archived queue visibility", "Keep completed review items visible in dashboards for reference.", false)
        ]
      }
    ]
  };

  const sections = settingsByRole[userRole] || settingsByRole.Branch;

  return `
    <section class="support-page">
      <div class="support-page-hero">
        <div>
          <span class="support-kicker">Configuration</span>
          <h2>Dashboard settings</h2>
          <p>${narrative.settingsHint}</p>
        </div>
        <div class="support-status-pill">
          <i class="bi bi-sliders2"></i>
          <span>Preferences ready</span>
        </div>
      </div>

      <div class="support-grid">
        ${sections.map(section => `
          <article class="support-card">
            <div class="support-card-head">
              <div>
                <h3>${section.title}</h3>
              </div>
            </div>
            <div class="support-setting-stack">
              ${section.items.join("")}
            </div>
          </article>
        `).join("")}
      </div>
    </section>
  `;
}

function createToggleSetting(title, description, checked) {
  return `
    <div class="support-setting-item">
      <div>
        <strong>${title}</strong>
        <p>${description}</p>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" ${checked ? "checked" : ""}>
      </div>
    </div>
  `;
}

function createSelectSetting(title, description, options) {
  return `
    <div class="support-setting-item support-setting-item-select">
      <div>
        <strong>${title}</strong>
        <p>${description}</p>
      </div>
      <select class="form-select">
        ${options.map(option => `<option>${option}</option>`).join("")}
      </select>
    </div>
  `;
}

function attachSupportInteractions() {
  document.querySelectorAll("[data-announcement-form]").forEach(form => {
    form.addEventListener("change", event => {
      if (event.target.matches("[data-announcement-audience]")) {
        updateAnnouncementAudienceState(form);
      }
    });

    updateAnnouncementAudienceState(form);

    form.addEventListener("submit", event => {
      event.preventDefault();
      const action = event.submitter?.value === "send" ? "Announcement sent to selected audience." : "Announcement saved as draft.";
      const status = form.querySelector("[data-announcement-status]");
      if (status) status.textContent = action;
    });
  });

  document.querySelectorAll("[data-feedback-form]").forEach(form => {
    form.addEventListener("submit", event => {
      event.preventDefault();
      const action = event.submitter?.value === "submit" ? "Feedback submitted for review." : "Feedback note saved.";
      const status = form.querySelector("[data-feedback-status]");
      if (status) status.textContent = action;
    });
  });

  document.querySelectorAll("[data-help-form]").forEach(form => {
    form.addEventListener("submit", event => {
      event.preventDefault();
      const status = form.querySelector("[data-help-status]");
      if (status) status.textContent = "Help request logged. A follow-up route can now be assigned.";
    });
  });
}

function updateAnnouncementAudienceState(form) {
  const selectedAudience = form.querySelector("[data-announcement-audience]:checked")?.value;
  const provinceWrap = form.querySelector("[data-announcement-province-wrap]");
  if (!provinceWrap) return;

  provinceWrap.classList.toggle("d-none", selectedAudience !== "specific-province");
}

function ensureSidebarLogoutButton() {
  const sideNavGroup = document.querySelector(`#sidenav li[data-role="${userRole}"]`);
  if (!sideNavGroup || sideNavGroup.querySelector("[data-sidebar-logout]")) return;

  const logoutLink = document.createElement("a");
  logoutLink.href = "#";
  logoutLink.className = "nav-link sidebar-link sidebar-logout-link mt-4";
  logoutLink.dataset.sidebarLogout = "true";
  logoutLink.innerHTML = `<i class="bi bi-box-arrow-right me-2"></i>Log Out`;
  logoutLink.addEventListener("click", event => {
    event.preventDefault();
    logoutDashboardUser();
  });
  sideNavGroup.appendChild(logoutLink);
}

function logoutDashboardUser() {
  window.sessionStorage.removeItem("esocsCurrentUser");
  window.location.href = "/signin";
}

function getCurrentAuthAccount() {
  try {
    return JSON.parse(window.sessionStorage.getItem("esocsCurrentUser") || "null");
  } catch (error) {
    return null;
  }
}

function getCurrentOfficeName() {
  if (!currentAuthAccount) return "";

  if (currentAuthAccount.role === "Branch") return currentAuthAccount.branchName || currentAuthAccount.officeName || "";
  if (currentAuthAccount.role === "District") return currentAuthAccount.district || currentAuthAccount.officeName || "";
  if (currentAuthAccount.role === "Province") return currentAuthAccount.province || currentAuthAccount.officeName || "";
  return currentAuthAccount.officeName || "ESOCS NATIONAL HQ";
}

function getOverviewPageIdForRole(role = userRole) {
  if (role === "Province") return "province-overview";
  if (role === "District") return "district-overview";
  if (role === "NHQ") return "nhq-overview";
  return "overview";
}

function getAnalyticsRoot() {
  const overviewPage = document.getElementById(getOverviewPageIdForRole());
  return overviewPage?.querySelector("[data-analytics-dashboard]") || null;
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-US").format(value);
}

function hashString(value) {
  let hash = 0;
  const input = String(value || "");

  for (let i = 0; i < input.length; i += 1) {
    hash = ((hash << 5) - hash) + input.charCodeAt(i);
    hash |= 0;
  }

  return Math.abs(hash);
}

function getHierarchyTotals() {
  const branchTotal = branchMembers.length;
  const districtTotal = districtMembers.length + branchTotal;
  const provinceTotal = provinceMembers.length + districtTotal;
  const nhqTotal = nhqMembers.length + provinceTotal;

  return {
    Branch: branchTotal,
    District: districtTotal,
    Province: provinceTotal,
    NHQ: nhqTotal
  };
}

function getAnalyticsMembersByScope() {
  return {
    Branch: branchMembers,
    District: [...districtMembers, ...branchMembers],
    Province: [...provinceMembers, ...districtMembers, ...branchMembers],
    NHQ: [...nhqMembers, ...provinceMembers, ...districtMembers, ...branchMembers]
  };
}

function getSummaryCardsData() {
  const totals = getHierarchyTotals();
  const cardConfig = [
    { key: "Branch", label: "Branches", description: "Total members", value: totals.Branch },
    { key: "District", label: "Districts", description: "Total members including branches", value: totals.District },
    { key: "Province", label: "Province", description: "Total members including branches", value: totals.Province },
    { key: "NHQ", label: "National HQ", description: "Total members", value: totals.NHQ }
  ];

  return cardConfig.map((card, index) => {
    const base = (hashString(`${card.key}-${card.value}`) % 9) + 2;
    const direction = index === 2 ? -1 : 1;
    const change = base * direction;

    return {
      ...card,
      changeText: `${change > 0 ? "+" : ""}${change}%`,
      tone: change > 0 ? "positive" : change < 0 ? "negative" : "neutral"
    };
  });
}

function getAnalyticsSeries(filter, scopeLabel) {
  const labels = filter === "7d"
    ? ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    : filter === "12m"
      ? ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
      : Array.from({ length: 30 }, (_, index) => `D${index + 1}`);

  const totals = getHierarchyTotals();
  const scopeTotal = totals[scopeLabel] || 1;
  const seed = hashString(`${scopeLabel}-${filter}`);
  const divisor = filter === "12m" ? 3.8 : filter === "7d" ? 2.1 : 3.1;
  const base = Math.max(4, Math.round(scopeTotal / divisor));

  return labels.map((label, index) => {
    const waveOne = Math.sin((index + 1) * 0.72 + seed) * 0.18;
    const waveTwo = Math.cos((index + 1) * 0.31 + seed) * 0.14;
    const momentum = index / Math.max(labels.length - 1, 1) * 0.22;
    const value = Math.round(base * (0.9 + waveOne + waveTwo + momentum));

    return {
      label,
      value: Math.max(2, value)
    };
  });
}

function getGrowthMetrics(series, filter) {
  const total = series.reduce((sum, item) => sum + item.value, 0);
  const growthCount = Math.max(1, Math.round(total / (filter === "12m" ? 16 : filter === "7d" ? 8 : 14)));
  const baseline = Math.max(1, Math.round(growthCount * 0.82));
  const growthDelta = Math.round(((growthCount - baseline) / baseline) * 100);
  const periodLabel = filter === "12m" ? "the last 12 months" : filter === "7d" ? "the last 7 days" : "the last 30 days";

  return {
    count: growthCount,
    text: `${growthCount} new members added in ${periodLabel}.`,
    percentage: `${growthDelta >= 0 ? "+" : ""}${growthDelta}%`,
    tone: growthDelta >= 0 ? "positive" : "negative"
  };
}

function getDemographicStats(members) {
  const total = members.length;
  const males = members.filter(member => member.sex === "Male").length;
  const females = members.filter(member => member.sex === "Female").length;
  const children = Math.min(total, Math.max(1, Math.round(total * 0.18)));

  return [
    { label: "Total Males", value: males, color: "#5b3df5" },
    { label: "Total Females", value: females, color: "#4f8bff" },
    { label: "Total Children (Under 18)", value: children, color: "#25b39e" }
  ];
}

function getRankDistribution(members) {
  const counts = members.reduce((accumulator, member) => {
    const rank = member.rank || "Unassigned";
    accumulator[rank] = (accumulator[rank] || 0) + 1;
    return accumulator;
  }, {});

  return Object.entries(counts)
    .map(([rank, count]) => ({ rank, count }))
    .sort((left, right) => right.count - left.count)
    .slice(0, 4);
}

function getRecentActivity(scopeLabel) {
  const baseFeed = [];
  const officeName = getCurrentOfficeName() || scopeLabel;
  const activeMembers = getActiveMembers();

  activeMembers.slice(0, 2).forEach((member, index) => {
    baseFeed.push({
      tone: "general",
      icon: scopeLabel.slice(0, 2).toUpperCase(),
      title: `${member.name} profile synced`,
      description: `${member.rank} record is now visible inside ${officeName}.`,
      time: index === 0 ? "Just now" : "Today"
    });
  });

  if (userRole === "Branch") {
    baseFeed.push({
      tone: "growth",
      icon: "NM",
      title: "New branch growth detected",
      description: `${branchMembers.length} active members are currently tracked in this branch.`,
      time: "2h ago"
    });
  }

  if (userRole === "District") {
    districtSubmissions.slice(0, 2).forEach((item, index) => {
      baseFeed.push({
        tone: item.status === "rejected" ? "decline" : item.status === "approved" || item.status === "queued" ? "growth" : "general",
        icon: "DQ",
        title: `${item.name} moved in district queue`,
        description: `${item.branch} submission is currently ${item.status}.`,
        time: index === 0 ? "1h ago" : "Yesterday"
      });
    });
  }

  if (userRole === "Province" || userRole === "NHQ") {
    phqSubmissions.slice(0, 3).forEach((item, index) => {
      baseFeed.push({
        tone: item.status === "rejected" ? "decline" : item.status === "approved" || item.status === "queued" || item.status === "confirmed" ? "growth" : "general",
        icon: userRole === "NHQ" ? "HQ" : "PQ",
        title: `${item.name} ${item.status === "confirmed" ? "confirmed" : "updated"} at ${item.district}`,
        description: `${item.currentRank} to ${item.recommendedRank} workflow is ${item.status}.`,
        time: index === 0 ? "45m ago" : index === 1 ? "Today" : "Yesterday"
      });
    });
  }

  return baseFeed.slice(0, 5);
}

function bindAnalyticsFilters(root) {
  if (!root || root.dataset.filtersBound === "true") return;

  root.querySelectorAll("[data-filter]").forEach(button => {
    button.addEventListener("click", () => {
      currentAnalyticsFilter = button.dataset.filter || "30d";
      renderOverviewAnalytics();
    });
  });

  root.dataset.filtersBound = "true";
}

function renderOverviewAnalytics() {
  const root = getAnalyticsRoot();
  if (!root) return;

  bindAnalyticsFilters(root);

  const scopeLabel = userRole === "NHQ" ? "NHQ" : userRole;
  const summaryCards = root.querySelector("[data-summary-cards]");
  const chartHost = root.querySelector("[data-membership-chart]");
  const axisHost = root.querySelector("[data-chart-axis]");
  const growthCount = root.querySelector("[data-growth-count]");
  const growthNote = root.querySelector("[data-growth-note]");
  const growthTrend = root.querySelector("[data-growth-trend]");
  const totalLabel = root.querySelector("[data-demographic-total]");
  const donut = root.querySelector("[data-demographic-donut]");
  const legendHost = root.querySelector("[data-demographic-legend]");
  const rankHost = root.querySelector("[data-rank-distribution]");
  const activityHost = root.querySelector("[data-activity-feed]");
  const scopeBadge = root.querySelector("[data-analytics-scope-label]");

  if (scopeBadge) {
    scopeBadge.textContent = userRole === "NHQ" ? "National HQ" : scopeLabel;
  }

  if (summaryCards) {
    summaryCards.innerHTML = getSummaryCardsData().map(card => `
      <article class="analytics-summary-card">
        <p class="metric-label">${card.label}</p>
        <h3 class="metric-value">${formatNumber(card.value)}</h3>
        <div class="metric-foot">
          <span class="analytics-change ${card.tone}">${card.changeText}</span>
          <span class="metric-description">${card.description}</span>
        </div>
      </article>
    `).join("");
  }

  const analyticsMembers = getAnalyticsMembersByScope()[scopeLabel];
  const chartSeries = getAnalyticsSeries(currentAnalyticsFilter, scopeLabel);
  const maxValue = Math.max(...chartSeries.map(item => item.value), 1);

  if (chartHost) {
    chartHost.style.setProperty("--bar-count", String(chartSeries.length));
    chartHost.innerHTML = chartSeries.map(item => `
      <div class="analytics-bar-group" title="${item.label}: ${item.value}">
        <span class="analytics-bar-value">${item.value}</span>
        <div class="analytics-bar" style="--bar-height: ${Math.max(12, (item.value / maxValue) * 100)}%;"></div>
      </div>
    `).join("");
  }

  if (axisHost) {
    axisHost.style.setProperty("--bar-count", String(chartSeries.length));
    axisHost.innerHTML = chartSeries.map(item => `
      <span class="analytics-axis-label">${item.label}</span>
    `).join("");
  }

  root.querySelectorAll("[data-filter]").forEach(button => {
    button.classList.toggle("active", button.dataset.filter === currentAnalyticsFilter);
  });

  const growthMetrics = getGrowthMetrics(chartSeries, currentAnalyticsFilter);

  if (growthCount) growthCount.textContent = String(growthMetrics.count);
  if (growthNote) growthNote.textContent = growthMetrics.text;
  if (growthTrend) {
    growthTrend.textContent = growthMetrics.percentage;
    growthTrend.className = `analytics-trend-chip ${growthMetrics.tone}`;
  }

  const demographics = getDemographicStats(analyticsMembers);
  const totalDemographic = demographics.reduce((sum, item) => sum + item.value, 0) || 1;
  const donutSegments = [];
  let offset = 0;

  demographics.forEach(item => {
    const share = (item.value / totalDemographic) * 100;
    donutSegments.push(`${item.color} ${offset}% ${offset + share}%`);
    offset += share;
  });

  if (totalLabel) {
    totalLabel.textContent = `${formatNumber(analyticsMembers.length)} members`;
  }

  if (donut) {
    donut.style.setProperty("--donut-fill", `conic-gradient(${donutSegments.join(", ")})`);
  }

  if (legendHost) {
    legendHost.innerHTML = demographics.map(item => `
      <div class="analytics-legend-item">
        <div class="analytics-legend-meta">
          <span class="analytics-dot" style="background:${item.color};"></span>
          <span class="analytics-legend-label">${item.label}</span>
        </div>
        <strong class="analytics-legend-value">${formatNumber(item.value)}</strong>
      </div>
    `).join("");
  }

  const rankDistribution = getRankDistribution(analyticsMembers);
  const highestRankValue = Math.max(...rankDistribution.map(item => item.count), 1);

  if (rankHost) {
    rankHost.innerHTML = rankDistribution.map(item => `
      <div class="analytics-rank-item">
        <div class="analytics-rank-meta">
          <span>${item.rank}</span>
          <strong>${formatNumber(item.count)}</strong>
        </div>
        <div class="analytics-rank-bar">
          <span style="--rank-width: ${(item.count / highestRankValue) * 100}%;"></span>
        </div>
      </div>
    `).join("");
  }

  if (activityHost) {
    activityHost.innerHTML = getRecentActivity(scopeLabel).map(item => `
      <div class="analytics-activity-item">
        <span class="analytics-activity-icon ${item.tone}">${item.icon}</span>
        <div class="analytics-activity-copy">
          <strong>${item.title}</strong>
          <span>${item.description}</span>
        </div>
        <span class="analytics-activity-time">${item.time}</span>
      </div>
    `).join("");
  }
}

function showFeatureInDevelopment() {
  alert("Feature still in development.");
}

function personalizeDashboardHeading() {
  const officeName = getCurrentOfficeName();
  if (!officeName) return;

  const heading = document.querySelector(`[data-role="${userRole}"] .page h2`);
  if (!heading) return;

  if (!heading.dataset.baseText) {
    heading.dataset.baseText = heading.textContent.trim();
  }

  heading.textContent = `${heading.dataset.baseText} - ${officeName}`;
}

function getActiveMembers() {
  if (userRole === "NHQ") return nhqMembers;
  if (userRole === "Province") return provinceMembers;
  if (userRole === "District") return districtMembers;
  return branchMembers;
}

function setActiveMembers(updatedMembers) {
  if (userRole === "NHQ") {
    nhqMembers = updatedMembers;
    return;
  }

  if (userRole === "Province") {
    provinceMembers = updatedMembers;
    return;
  }

  if (userRole === "District") {
    districtMembers = updatedMembers;
    return;
  }

  branchMembers = updatedMembers;
}

// Mobile-only sidebar toggle handler.
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) return;
  sidebar.classList.toggle("open");
}

// Global page switcher used by sidebar links.
function showPage(pageId) {
  const pages = document.querySelectorAll(".page");
  pages.forEach(page => page.classList.add("d-none"));

  const target = document.getElementById(pageId);
  if (target) target.classList.remove("d-none");

  setActiveSidebarLink(pageId);

  // Keep recommendation table current when that page opens.
  if (pageId === "ordination") {
    renderRecommended();
  }

  // Render member tables whenever a member data page is opened.
  if (pageId === "members" || pageId === "province-members" || pageId === "district-members" || pageId === "nhq-members") {
    renderTable();
  }

  if (pageId === "province-confirmation") {
    renderConfirmationList();
  }

  // Keep PHQ metrics/tables current while navigating PHQ pages.
  if (pageId === "province-overview" || pageId === "province-submissions" || pageId === "province-nhq") {
    renderPHQDashboard();
  }

  if (pageId === "district-overview" || pageId === "district-submissions" || pageId === "district-province") {
    renderDistrictDashboard();
  }

  if (pageId === "nhq-overview" || pageId === "nhq-submissions") {
    renderNHQDashboard();
  }

  personalizeDashboardHeading();
}

// Applies active styling to the currently selected sidebar link.
function setActiveSidebarLink(pageId) {
  const sidebarLinks = document.querySelectorAll("#sidenav .sidebar-link");
  sidebarLinks.forEach(link => {
    link.classList.toggle("active", link.dataset.page === pageId);
  });
}

// Returns a styled badge for branch/member status.
function getStatusBadge(status) {
  if (!status) return "";

  if (status === "recommended") {
    return `<span class="badge bg-primary">Recommended</span>`;
  }

  if (status === "pending") {
    return `<span class="badge bg-warning text-dark">Pending</span>`;
  }

  if (status === "approved") {
    return `<span class="badge bg-success">Approved</span>`;
  }

  if (status === "flagged") {
    return `<span class="badge bg-danger">Flagged</span>`;
  }

  if (status === "confirmed") {
    return `<span class="badge bg-success">Confirmed</span>`;
  }

  return "";
}

function getActiveMembersSearchValue() {
  const input = userRole === "Province"
    ? document.getElementById("provinceSearchInput")
    : userRole === "District"
      ? document.getElementById("districtSearchInput")
      : userRole === "NHQ"
        ? document.getElementById("nhqSearchInput")
        : document.getElementById("searchInput");

  return (input?.value || "").trim().toLowerCase();
}

function getPrintableMembers() {
  const members = getActiveMembers();
  const searchValue = getActiveMembersSearchValue();
  if (!searchValue) return members;

  return members.filter(member =>
    String(member.name || "").toLowerCase().includes(searchValue) ||
    String(member.rank || "").toLowerCase().includes(searchValue)
  );
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function formatMemberFieldLabel(key) {
  const labels = {
    name: "Name",
    sex: "Sex",
    rank: "Rank",
    status: "Status",
    recommendedRank: "Recommended Rank",
    email: "Email",
    phone: "Phone",
    nationality: "Nationality",
    occupation: "Occupation",
    band: "Band",
    association: "Association",
    maritalStatus: "Marital Status",
    marriageYear: "Marriage Year",
    ordinationHistory: "Ordination History"
  };

  if (labels[key]) return labels[key];
  return key.replace(/([A-Z])/g, " $1").replace(/^./, character => character.toUpperCase());
}

function formatMemberFieldValue(key, value) {
  if (key === "ordinationHistory" && Array.isArray(value)) {
    return value.length
      ? value.map(item => `${item.rank || "Rank"} (${item.year || "Year N/A"})`).join(", ")
      : "N/A";
  }

  if (key === "status") {
    return value ? formatMemberFieldLabel(String(value).toLowerCase()) : "Active";
  }

  if (value === null || value === undefined || value === "") {
    return "N/A";
  }

  return String(value);
}

function getMembersPrintColumns(members) {
  const preferredOrder = [
    "name",
    "sex",
    "rank",
    "status",
    "recommendedRank",
    "email",
    "phone",
    "nationality",
    "occupation",
    "band",
    "association",
    "maritalStatus",
    "marriageYear",
    "ordinationHistory"
  ];

  const seen = new Set();
  const columns = [];

  preferredOrder.forEach(key => {
    if (members.some(member => member[key] !== undefined)) {
      seen.add(key);
      columns.push(key);
    }
  });

  members.forEach(member => {
    Object.keys(member).forEach(key => {
      if (key === "originalIndex" || seen.has(key)) return;
      seen.add(key);
      columns.push(key);
    });
  });

  return columns;
}

function getPrintOfficeLabel() {
  const officeName = getCurrentOfficeName();
  if (officeName) return officeName;
  if (userRole === "NHQ") return "ESOCS National HQ";
  if (userRole === "Province") return "Province Office";
  if (userRole === "District") return "District Office";
  return "Branch Office";
}

function getOrdinaTrackPrintLogo() {
  return `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36.53 34" aria-hidden="true">
      <g fill="currentColor">
        <rect x="0" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="0" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="0" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="4.25" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="4.25" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="4.25" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="7.65" y="2.55" width="1.7" height="13.1" rx=".85" />
        <rect x="7.65" y="18.7" width="1.7" height="12.25" rx=".85" />
        <rect x="11.9" y="2.55" width="1.7" height="13.1" rx=".85" />
        <rect x="11.9" y="18.7" width="1.7" height="12.25" rx=".85" />
        <rect x="15.3" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="15.3" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="15.3" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="19.55" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="19.55" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="19.55" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="22.93" y="2.55" width="1.7" height="13.1" rx=".85" />
        <rect x="22.93" y="18.7" width="1.7" height="12.25" rx=".85" />
        <rect x="27.18" y="2.55" width="1.7" height="13.1" rx=".85" />
        <rect x="27.18" y="18.7" width="1.7" height="12.25" rx=".85" />
        <rect x="30.58" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="30.58" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="30.58" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="34.83" y="0" width="1.7" height="7.7" rx=".85" />
        <rect x="34.83" y="8.5" width="1.7" height="14.8" rx=".85" />
        <rect x="34.83" y="24.3" width="1.7" height="9.7" rx=".85" />
        <rect x="0" y="8.5" width="5.95" height="1.7" rx=".85" />
        <rect x="0" y="23.78" width="5.95" height="1.7" rx=".85" />
        <rect x="7.65" y="0" width="5.95" height="1.7" rx=".85" />
        <rect x="7.65" y="16.97" width="5.95" height="1.7" rx=".85" />
        <rect x="7.65" y="32.25" width="5.95" height="1.7" rx=".85" />
        <rect x="15.3" y="8.5" width="5.95" height="1.7" rx=".85" />
        <rect x="15.3" y="23.78" width="5.95" height="1.7" rx=".85" />
        <rect x="22.93" y="0" width="5.95" height="1.7" rx=".85" />
        <rect x="22.93" y="16.97" width="5.95" height="1.7" rx=".85" />
        <rect x="22.93" y="32.25" width="5.95" height="1.7" rx=".85" />
        <rect x="30.58" y="8.5" width="5.95" height="1.7" rx=".85" />
        <rect x="30.58" y="23.78" width="5.95" height="1.7" rx=".85" />
      </g>
    </svg>
  `;
}

function printMembersData() {
  const members = getPrintableMembers();
  if (!members.length) {
    alert("No members available to print.");
    return;
  }

  const columns = getMembersPrintColumns(members);
  const officeLabel = escapeHtml(getPrintOfficeLabel());
  const printedDate = escapeHtml(new Date().toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "long",
    year: "numeric"
  }));
  const rowsMarkup = members.map(member => `
    <tr>
      ${columns.map(column => `<td>${escapeHtml(formatMemberFieldValue(column, member[column]))}</td>`).join("")}
    </tr>
  `).join("");

  const printWindow = window.open("", "_blank", "width=1100,height=900");
  if (!printWindow) {
    alert("Unable to open print preview. Please allow pop-ups for this page.");
    return;
  }

  printWindow.document.write(`
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Members Printout</title>
      <style>
        body {
          margin: 0;
          font-family: Arial, sans-serif;
          color: #111827;
          background: #ffffff;
        }
        .print-shell {
          padding: 36px 40px 28px;
        }
        .print-head {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          gap: 24px;
          margin-bottom: 24px;
          padding-bottom: 18px;
          border-bottom: 2px solid #dbe3f0;
        }
        .print-title {
          margin: 0;
          font-size: 30px;
          line-height: 1.1;
        }
        .print-subline {
          margin: 8px 0 0;
          color: #4b5563;
          font-size: 15px;
        }
        .print-date {
          text-align: right;
          font-size: 14px;
          color: #4b5563;
          white-space: nowrap;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          font-size: 13px;
        }
        thead th {
          background: #0f172a;
          color: #ffffff;
          text-align: left;
          padding: 10px 12px;
          border: 1px solid #dbe3f0;
        }
        tbody td {
          padding: 10px 12px;
          border: 1px solid #dbe3f0;
          vertical-align: top;
        }
        tbody tr:nth-child(even) {
          background: #f8fafc;
        }
        .print-foot {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 24px;
          margin-top: 28px;
          padding-top: 18px;
          border-top: 2px solid #dbe3f0;
        }
        .print-foot-note {
          color: #4b5563;
          font-size: 13px;
        }
        .print-brand {
          display: inline-flex;
          align-items: center;
          gap: 12px;
          color: #111827;
        }
        .print-brand svg {
          width: 28px;
          height: 28px;
          color: #c69224;
          flex: 0 0 auto;
        }
        .print-brand strong {
          display: block;
          font-size: 15px;
        }
        .print-brand span {
          display: block;
          font-size: 12px;
          color: #6b7280;
        }
        @media print {
          body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-shell">
        <div class="print-head">
          <div>
            <h1 class="print-title">${officeLabel}</h1>
            <p class="print-subline">Members Data Printout</p>
          </div>
          <div class="print-date">
            <strong>Date</strong><br>
            ${printedDate}
          </div>
        </div>

        <table>
          <thead>
            <tr>
              ${columns.map(column => `<th>${escapeHtml(formatMemberFieldLabel(column))}</th>`).join("")}
            </tr>
          </thead>
          <tbody>
            ${rowsMarkup}
          </tbody>
        </table>

        <div class="print-foot">
          <div class="print-foot-note">
            Printed from the OrdinaTrack dashboard.
          </div>
          <div class="print-brand">
            ${getOrdinaTrackPrintLogo()}
            <div>
              <strong>OrdinaTrack</strong>
              <span>Track. Manage. Elevate.</span>
            </div>
          </div>
        </div>
      </div>
    </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}

// Renders the branch members table (supports filtered dataset too).
function renderTable(data = getActiveMembers()) {
  clearExpiredConfirmations();
  const members = getActiveMembers();
  const table = userRole === "Province"
    ? document.getElementById("provinceMembersTable")
    : userRole === "District"
      ? document.getElementById("districtMembersTable")
      : userRole === "NHQ"
        ? document.getElementById("nhqMembersTable")
      : document.getElementById("membersTable");
  if (!table) return;

  table.innerHTML = data.map((member, index) => {
    const rowIndex = member.originalIndex ?? index;
    const cancelRecommendationAction = member.status === "recommended"
      ? `<li><a class="dropdown-item text-warning" href="#" onclick="cancelRecommendation(${rowIndex})">Cancel Recommendation</a></li>`
      : "";

    return `
    <tr>
      <td>${member.name}</td>
      <td>${member.sex}</td>
      <td>${member.rank}</td>
      <td>${getStatusBadge(member.status)}</td>
      <td>
        <div class="dropdown position-static">
          <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
            Actions
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="viewProfile(${rowIndex})">View Profile</a></li>
            <li><a class="dropdown-item" href="#" onclick="editMember(${rowIndex})">Edit Data</a></li>
            <li><a class="dropdown-item" href="#" onclick="openRecommend(${rowIndex})">Recommend</a></li>
            ${cancelRecommendationAction}
            <li><a class="dropdown-item" href="#" onclick="flagMember(${rowIndex})">Flag</a></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="deleteMember(${rowIndex})">Delete</a></li>
          </ul>
        </div>
      </td>
    </tr>
  `;
  }).join("");

  renderOverviewAnalytics();
}

// Filters members table by name or rank search input.
function filterTable() {
  const members = getActiveMembers();
  const input = getActiveMembersSearchValue();

  const filtered = members
    .map((member, index) => ({ ...member, originalIndex: index }))
    .filter(member =>
      member.name.toLowerCase().includes(input) ||
      member.rank.toLowerCase().includes(input)
    );

  renderTable(filtered);
}

// Marks a member as flagged.
function flagMember(index) {
  const members = getActiveMembers();
  members[index].status = "flagged";
  renderTable();
}

// Removes a member row after confirmation.
function deleteMember(index) {
  const members = getActiveMembers();
  if (confirm("Delete this member?")) {
    members.splice(index, 1);
    renderTable();
  }
}

// Updates rank dropdown based on selected sex in Add Member form.
function updateRankOptions() {
  const sex = document.getElementById("memberSex")?.value;
  const rankSelect = document.getElementById("memberRank");
  if (!rankSelect) return;

  rankSelect.innerHTML = '<option value="">Select Rank</option>';

  const ranks = sex === "Male" ? maleRanks : femaleRanks;
  ranks.forEach(rank => {
    const option = document.createElement("option");
    option.value = rank;
    option.textContent = rank;
    rankSelect.appendChild(option);
  });
}

// Adds one dynamic ordination history row to Add Member form.
function addOrdinationField() {
  const container = document.getElementById("ordinationContainer");
  if (!container) return;

  const div = document.createElement("div");
  div.classList.add("row", "mb-2");

  div.innerHTML = `
    <div class="col-md-6">
      <input type="text" class="form-control ord-rank" placeholder="Rank">
    </div>
    <div class="col-md-6">
      <input type="number" class="form-control ord-year" placeholder="Year">
    </div>
  `;

  container.appendChild(div);
}

// Shows or hides marriage year input based on marital status.
function toggleMarriageYear() {
  const status = document.getElementById("maritalStatus")?.value;
  const yearSelect = document.getElementById("marriageYear");
  if (!yearSelect) return;

  yearSelect.classList.toggle("d-none", status !== "Married");

  if (status === "Married") {
    yearSelect.innerHTML = '<option value="">Year of Marriage</option>';
    const currentYear = new Date().getFullYear();

    for (let i = currentYear; i >= 1970; i--) {
      yearSelect.innerHTML += `<option value="${i}">${i}</option>`;
    }
  }
}

// Creates and stores a new member from Add Member modal fields.
function saveMember() {
  const members = getActiveMembers();
  const name = document.getElementById("memberName")?.value;
  const sex = document.getElementById("memberSex")?.value;
  const rank = document.getElementById("memberRank")?.value;

  if (!name || !sex || !rank) {
    alert("Please fill all required fields");
    return;
  }

  const ranks = document.querySelectorAll(".ord-rank");
  const years = document.querySelectorAll(".ord-year");
  const ordinationHistory = [];

  for (let i = 0; i < ranks.length; i++) {
    const r = ranks[i].value;
    const y = years[i].value;

    if (r && y && r !== "Brother" && r !== "Sister") {
      ordinationHistory.push({ rank: r, year: y });
    }
  }

  const currentYear = new Date().getFullYear();
  if (rank !== "Brother" && rank !== "Sister") {
    ordinationHistory.push({ rank, year: currentYear });
  }

  members.push({
    name,
    sex,
    rank,
    status: "",
    email: document.getElementById("memberEmail")?.value || "",
    phone: document.getElementById("memberPhone")?.value || "",
    nationality: document.getElementById("memberNationality")?.value || "",
    occupation: document.getElementById("memberOccupation")?.value || "",
    band: document.getElementById("memberBand")?.value || "",
    association: document.getElementById("memberAssociation")?.value || "",
    maritalStatus: document.getElementById("maritalStatus")?.value || "",
    marriageYear: document.getElementById("marriageYear")?.value || "",
    ordinationHistory,
    recommendedRank: null
  });

  renderTable();
  bootstrap.Modal.getInstance(document.getElementById("addMemberModal"))?.hide();
}

// Opens Add Member modal.
function openAddModal() {
  new bootstrap.Modal(document.getElementById("addMemberModal")).show();
}

// Renders selected member profile in a modal.
function viewProfile(index) {
  const members = getActiveMembers();
  const member = members[index];
  currentEditIndex = index;

  let historyHTML = "";
  if (member.ordinationHistory && member.ordinationHistory.length > 0) {
    historyHTML = member.ordinationHistory.map(item => `
      <li class="list-group-item d-flex justify-content-between">
        <span>${item.rank}</span>
        <span class="text-muted">${item.year}</span>
      </li>
    `).join("");
  } else {
    historyHTML = '<li class="list-group-item">No ordination history</li>';
  }

  const content = `
    <div class="row">
      <div class="col-md-6">
        <p><strong>Name:</strong> ${member.name}</p>
        <p><strong>Sex:</strong> ${member.sex}</p>
        <p><strong>Rank:</strong> ${member.rank}</p>
        <p><strong>Status:</strong> ${member.status}</p>
      </div>
      <div class="col-md-6">
        <p><strong>Email:</strong> ${member.email || "N/A"}</p>
        <p><strong>Phone:</strong> ${member.phone || "N/A"}</p>
        <p><strong>Nationality:</strong> ${member.nationality || "N/A"}</p>
        <p><strong>Occupation:</strong> ${member.occupation || "N/A"}</p>
      </div>
    </div>
    <hr>
    <div class="row">
      <div class="col-md-6">
        <p><strong>Band:</strong> ${member.band || "N/A"}</p>
        <p><strong>Association:</strong> ${member.association || "N/A"}</p>
      </div>
      <div class="col-md-6">
        <p><strong>Marital Status:</strong> ${member.maritalStatus || "N/A"}</p>
        <p><strong>Marriage Year:</strong> ${member.marriageYear || "N/A"}</p>
      </div>
    </div>
    <hr>
    <h6>Ordination History</h6>
    <ul class="list-group">${historyHTML}</ul>
  `;

  document.getElementById("profileContent").innerHTML = content;
  new bootstrap.Modal(document.getElementById("profileModal")).show();
}

// Loads selected member into Edit Member modal.
function editMember(index) {
  const members = getActiveMembers();
  const member = members[index];
  currentEditIndex = index;

  document.getElementById("editName").value = member.name || "";
  document.getElementById("editSex").value = member.sex || "";

  updateEditRankOptions();

  document.getElementById("editRank").value = member.rank || "";
  document.getElementById("editEmail").value = member.email || "";
  document.getElementById("editPhone").value = member.phone || "";
  document.getElementById("editOccupation").value = member.occupation || "";
  document.getElementById("editNationality").value = member.nationality || "";
  document.getElementById("editBand").value = member.band || "";
  document.getElementById("editAssociation").value = member.association || "";
  document.getElementById("editMaritalStatus").value = member.maritalStatus || "";

  toggleEditMarriageYear();
  document.getElementById("editMarriageYear").value = member.marriageYear || "";

  document.getElementById("editOrdinationContainer").innerHTML = "";

  if (member.ordinationHistory && member.ordinationHistory.length > 0) {
    member.ordinationHistory.forEach(item => addEditOrdinationField(item.rank, item.year));
  }

  new bootstrap.Modal(document.getElementById("editMemberModal")).show();
}

// Updates edit-rank options based on selected sex.
function updateEditRankOptions() {
  const sex = document.getElementById("editSex").value;
  const rankSelect = document.getElementById("editRank");

  rankSelect.innerHTML = "";

  const ranks = sex === "Male" ? maleRanks : femaleRanks;
  ranks.forEach(rank => {
    const option = document.createElement("option");
    option.value = rank;
    option.textContent = rank;
    rankSelect.appendChild(option);
  });
}

// Persists edits made in Edit Member modal back to member list.
function updateMember() {
  const members = getActiveMembers();
  if (currentEditIndex === null) return;

  const ranks = document.querySelectorAll(".edit-ord-rank");
  const years = document.querySelectorAll(".edit-ord-year");
  const updatedHistory = [];

  for (let i = 0; i < ranks.length; i++) {
    const r = ranks[i].value;
    const y = years[i].value;

    if (r && y && r !== "Brother" && r !== "Sister") {
      updatedHistory.push({ rank: r, year: y });
    }
  }

  members[currentEditIndex] = {
    ...members[currentEditIndex],
    name: document.getElementById("editName").value,
    sex: document.getElementById("editSex").value,
    rank: document.getElementById("editRank").value,
    email: document.getElementById("editEmail").value,
    phone: document.getElementById("editPhone").value,
    occupation: document.getElementById("editOccupation").value,
    nationality: document.getElementById("editNationality").value,
    band: document.getElementById("editBand").value,
    association: document.getElementById("editAssociation").value,
    maritalStatus: document.getElementById("editMaritalStatus").value,
    marriageYear: document.getElementById("editMarriageYear").value,
    ordinationHistory: updatedHistory
  };

  renderTable();
  bootstrap.Modal.getInstance(document.getElementById("editMemberModal"))?.hide();
}

// Shows or hides marriage year in Edit Member modal.
function toggleEditMarriageYear() {
  const status = document.getElementById("editMaritalStatus").value;
  const yearSelect = document.getElementById("editMarriageYear");

  yearSelect.classList.toggle("d-none", status !== "Married");

  if (status === "Married") {
    yearSelect.innerHTML = '<option value="">Year of Marriage</option>';
    const currentYear = new Date().getFullYear();
    for (let i = currentYear; i >= 1970; i--) {
      yearSelect.innerHTML += `<option value="${i}">${i}</option>`;
    }
  }
}

// Adds one dynamic ordination row in Edit Member modal.
function addEditOrdinationField(rank = "", year = "") {
  const container = document.getElementById("editOrdinationContainer");

  const div = document.createElement("div");
  div.classList.add("row", "mb-2");

  div.innerHTML = `
    <div class="col-md-6">
      <input type="text" class="form-control edit-ord-rank" placeholder="Rank" value="${rank}">
    </div>
    <div class="col-md-6">
      <input type="number" class="form-control edit-ord-year" placeholder="Year" value="${year}">
    </div>
  `;

  container.appendChild(div);
}

// Quick action: jump from profile modal to edit modal.
function editFromProfile() {
  if (currentEditIndex === null) {
    alert("Please select a member first.");
    return;
  }

  bootstrap.Modal.getInstance(document.getElementById("profileModal"))?.hide();
  editMember(currentEditIndex);
}

// Opens recommendation modal and loads eligible ranks.
function openRecommend(index) {
  const members = getActiveMembers();
  currentRecommendIndex = index;

  const member = members[index];
  const select = document.getElementById("recommendRank");

  select.innerHTML = '<option value="">Select Rank</option>';

  const ranks = member.sex === "Male" ? maleRanks : femaleRanks;

  ranks.forEach(rank => {
    if (rank !== "Brother" && rank !== "Sister") {
      const option = document.createElement("option");
      option.value = rank;
      option.textContent = rank;
      select.appendChild(option);
    }
  });

  new bootstrap.Modal(document.getElementById("recommendModal")).show();
}

// Confirms recommendation and stores selected target rank.
function confirmRecommendation() {
  const members = getActiveMembers();
  const rank = document.getElementById("recommendRank").value;

  if (!rank) {
    alert("Please select a rank");
    return;
  }

  const member = members[currentRecommendIndex];

  if (userRole === "Province") {
    member.status = "pending";
    member.recommendedRank = rank;

    const existingSubmission = phqSubmissions.find(item =>
      item.district === (getCurrentOfficeName() || "Province Members") && item.name === member.name
    );

    const submissionPayload = {
      district: getCurrentOfficeName() || "Province Members",
      name: member.name,
      currentRank: member.rank,
      recommendedRank: rank,
      status: "pending"
    };

    if (existingSubmission) {
      Object.assign(existingSubmission, submissionPayload);
    } else {
      phqSubmissions.unshift(submissionPayload);
    }

    renderTable();
    renderPHQDashboard();
    bootstrap.Modal.getInstance(document.getElementById("recommendModal"))?.hide();
    return;
  }

  if (userRole === "District") {
    member.status = "pending";
    member.recommendedRank = rank;

    const existingSubmission = districtSubmissions.find(item =>
      item.branch === (getCurrentOfficeName() || "District HQ Members") && item.name === member.name
    );

    const submissionPayload = {
      branch: getCurrentOfficeName() || "District HQ Members",
      name: member.name,
      currentRank: member.rank,
      recommendedRank: rank,
      status: "pending"
    };

    if (existingSubmission) {
      Object.assign(existingSubmission, submissionPayload);
    } else {
      districtSubmissions.unshift(submissionPayload);
    }

    renderTable();
    renderDistrictDashboard();
    bootstrap.Modal.getInstance(document.getElementById("recommendModal"))?.hide();
    return;
  }

  member.status = "recommended";
  member.recommendedRank = rank;

  renderTable();
  renderRecommended();

  bootstrap.Modal.getInstance(document.getElementById("recommendModal"))?.hide();
}

// Cancels recommendation state for one member.
function cancelRecommendation(index) {
  const members = getActiveMembers();
  members[index].status = "";
  members[index].recommendedRank = null;
  renderTable();
  renderRecommended();
}

// Renders Recommended List page table for pending/recommended members.
function renderRecommended() {
  const members = getActiveMembers();
  const table = document.getElementById("recommendedTable");
  if (!table) return;

  const filtered = members
    .map((m, idx) => ({ ...m, originalIndex: idx }))
    .filter(m => m.status === "recommended" || m.status === "pending");

  table.innerHTML = filtered.map(m => `
    <tr>
      <td>
        <div class="d-flex align-items-center gap-2">
          <span>${m.name}</span>
          <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Cancel recommendation" onclick="cancelRecommendation(${m.originalIndex})">x</button>
        </div>
      </td>
      <td>${m.sex}</td>
      <td>${m.rank}</td>
      <td>${m.recommendedRank || "-"}</td>
      <td>${getStatusBadge(m.status)}</td>
      <td>
        <button class="btn btn-sm btn-success" onclick="pushToPHQ(${m.originalIndex})">Push</button>
        <button class="btn btn-sm btn-warning" onclick="pullBack(${m.originalIndex})">Pull</button>
      </td>
    </tr>
  `).join("");
}

// Pushes one member to pending PHQ review state.
function pushToPHQ(index) {
  const members = getActiveMembers();
  members[index].status = "pending";
  syncBranchSubmission(index);
  renderRecommended();
  renderTable();
}

// Pulls one member back into recommended state.
function pullBack(index) {
  const members = getActiveMembers();
  members[index].status = "recommended";
  renderRecommended();
  renderTable();
}

// Bulk action: push all recommended members to pending.
function pushAllToPHQ() {
  const members = getActiveMembers();
  members.forEach((member, index) => {
    if (member.status === "recommended") {
      member.status = "pending";
      syncBranchSubmission(index);
    }
  });
  renderRecommended();
  renderTable();
}

function syncBranchSubmission(index) {
  if (userRole !== "Branch") return;

  const members = getActiveMembers();
  const member = members[index];
  if (!member) return;

  const branchHeading = getCurrentOfficeName() || "Branch Members";
  const existingSubmission = districtSubmissions.find(item =>
    item.branch === branchHeading && item.name === member.name
  );

  const submissionPayload = {
    branch: branchHeading,
    name: member.name,
    currentRank: member.rank,
    recommendedRank: member.recommendedRank || member.rank,
    status: "pending"
  };

  if (existingSubmission) {
    Object.assign(existingSubmission, submissionPayload);
  } else {
    districtSubmissions.unshift(submissionPayload);
  }
}

function getDistrictStatusBadge(status) {
  if (status === "pending") {
    return `<span class="badge bg-warning text-dark">Pending</span>`;
  }

  if (status === "approved") {
    return `<span class="badge bg-success">Approved</span>`;
  }

  if (status === "rejected") {
    return `<span class="badge bg-danger">Rejected</span>`;
  }

  if (status === "queued") {
    return `<span class="badge bg-primary">Queued To Province</span>`;
  }

  return "";
}

function renderDistrictDashboard() {
  const totalBranchesEl = document.getElementById("districtTotalBranches");
  const pendingCountEl = document.getElementById("districtPendingCount");
  const readyCountEl = document.getElementById("districtReadyCount");
  const submissionsTable = document.getElementById("districtSubmissionsTable");
  const provinceQueueTable = document.getElementById("districtProvinceQueueTable");

  if (totalBranchesEl) {
    const uniqueBranches = new Set(districtSubmissions.map(item => item.branch));
    totalBranchesEl.textContent = String(uniqueBranches.size);
  }

  if (pendingCountEl) {
    pendingCountEl.textContent = String(districtSubmissions.filter(item => item.status === "pending").length);
  }

  if (readyCountEl) {
    readyCountEl.textContent = String(districtSubmissions.filter(item => item.status === "approved" || item.status === "queued").length);
  }

  if (submissionsTable) {
    submissionsTable.innerHTML = districtSubmissions.map((item, index) => `
      <tr>
        <td>${item.branch}</td>
        <td>${item.name}</td>
        <td>${item.currentRank}</td>
        <td>${item.recommendedRank}</td>
        <td>${getDistrictStatusBadge(item.status)}</td>
        <td>
          <div class="d-flex flex-wrap gap-2">
            ${item.status === "pending" ? `
              <button class="btn btn-sm btn-success" onclick="approveDistrictSubmission(${index})">Approve</button>
              <button class="btn btn-sm btn-danger" onclick="rejectDistrictSubmission(${index})">Reject</button>
            ` : ""}
            ${item.status === "approved" ? `
              <button class="btn btn-sm btn-primary" onclick="pushSingleToProvince(${index})">Push</button>
            ` : ""}
            ${item.status === "queued" ? '<span class="text-primary fw-semibold">Pushed To Province</span>' : ""}
            ${item.status === "rejected" ? '<span class="text-muted">No action</span>' : ""}
          </div>
        </td>
      </tr>
    `).join("");
  }

  if (provinceQueueTable) {
    const approvedItems = districtSubmissions.filter(item => item.status === "approved" || item.status === "queued");
    provinceQueueTable.innerHTML = approvedItems.map(item => `
      <tr>
        <td>${item.branch}</td>
        <td>${item.name}</td>
        <td>${item.recommendedRank}</td>
        <td>${getDistrictStatusBadge(item.status)}</td>
      </tr>
    `).join("");
  }

  renderOverviewAnalytics();
}

function renderNHQDashboard() {
  clearExpiredConfirmations();

  const totalSourcesEl = document.getElementById("nhqTotalSources");
  const queuedCountEl = document.getElementById("nhqQueuedCount");
  const confirmedCountEl = document.getElementById("nhqConfirmedCount");
  const queueTable = document.getElementById("nhqQueueTable");

  const nhqItems = phqSubmissions.filter(item =>
    item.status === "queued" || item.status === "confirmed" || item.status === "approved"
  );

  if (totalSourcesEl) {
    const uniqueSources = new Set(nhqItems.map(item => item.district));
    totalSourcesEl.textContent = String(uniqueSources.size);
  }

  if (queuedCountEl) {
    queuedCountEl.textContent = String(nhqItems.filter(item => item.status === "queued").length);
  }

  if (confirmedCountEl) {
    confirmedCountEl.textContent = String(nhqItems.filter(item => item.status === "confirmed").length);
  }

  if (queueTable) {
    queueTable.innerHTML = nhqItems.length ? nhqItems.map(item => {
      const sourceIndex = phqSubmissions.indexOf(item);

      return `
        <tr>
          <td>${item.district}</td>
          <td>${item.name}</td>
          <td>${item.currentRank}</td>
          <td>${item.recommendedRank}</td>
          <td>${getPHQStatusBadge(item.status)}</td>
          <td>
            <div class="d-flex flex-wrap gap-2">
              ${item.status === "queued" ? `
                <button class="btn btn-sm btn-success" onclick="confirmNHQSubmission(${sourceIndex})">Confirm</button>
                <button class="btn btn-sm btn-warning" onclick="returnNHQSubmission(${sourceIndex})">Return</button>
              ` : ""}
              ${item.status === "approved" ? '<span class="text-muted">Awaiting province push</span>' : ""}
              ${item.status === "confirmed" ? '<span class="text-success fw-semibold">Confirmed At NHQ</span>' : ""}
            </div>
          </td>
        </tr>
      `;
    }).join("") : `
      <tr>
        <td colspan="6" class="text-center text-muted">No provincial submissions have reached NHQ yet.</td>
      </tr>
    `;
  }

  renderOverviewAnalytics();
}

function confirmNHQSubmission(index) {
  const item = phqSubmissions[index];
  if (!item || item.status !== "queued") return;

  if (item?.district === "Province Members") {
    const member = provinceMembers.find(entry => entry.name === item.name);
    if (member) {
      member.rank = item.recommendedRank;
      member.status = "confirmed";
      member.recommendedRank = null;
      member.confirmedAt = Date.now();
    }
  }

  item.currentRank = item.recommendedRank;
  item.status = "confirmed";
  item.confirmedAt = Date.now();
  renderNHQDashboard();
}

function returnNHQSubmission(index) {
  const item = phqSubmissions[index];
  if (!item || item.status !== "queued") return;

  item.status = "approved";
  renderNHQDashboard();
}

function approveDistrictSubmission(index) {
  const item = districtSubmissions[index];
  if (item?.branch === "District HQ Members") {
    const member = districtMembers.find(entry => entry.name === item.name);
    if (member) {
      member.status = "approved";
      member.recommendedRank = item.recommendedRank;
    }
  }

  districtSubmissions[index].status = "approved";
  renderTable();
  renderDistrictDashboard();
}

function rejectDistrictSubmission(index) {
  const item = districtSubmissions[index];
  if (item?.branch === "District HQ Members") {
    const member = districtMembers.find(entry => entry.name === item.name);
    if (member) {
      member.status = "flagged";
      member.recommendedRank = item.recommendedRank;
    }
  }

  districtSubmissions[index].status = "rejected";
  renderTable();
  renderDistrictDashboard();
}

function approveAllDistrictSubmissions() {
  districtSubmissions.forEach(item => {
    if (item.status === "pending") {
      item.status = "approved";
    }
  });
  renderDistrictDashboard();
}

function pushApprovedToProvince() {
  districtSubmissions.forEach(item => {
    if (item.status === "approved") {
      item.status = "queued";
    }
  });
  renderDistrictDashboard();
}

function pushSingleToProvince(index) {
  if (!districtSubmissions[index]) return;
  if (districtSubmissions[index].status === "approved") {
    districtSubmissions[index].status = "queued";
  }
  renderDistrictDashboard();
}

// Bulk action: pull all pending/recommended members to recommended.
function pullAllBack() {
  const members = getActiveMembers();
  members.forEach(member => {
    if (member.status === "pending" || member.status === "recommended") {
      member.status = "recommended";
    }
  });
  renderRecommended();
  renderTable();
}

// Returns status badge used in PHQ tables.
function getPHQStatusBadge(status) {
  if (status === "pending") {
    return `<span class="badge bg-warning text-dark">Pending</span>`;
  }

  if (status === "approved") {
    return `<span class="badge bg-success">Approved</span>`;
  }

  if (status === "rejected") {
    return `<span class="badge bg-danger">Rejected</span>`;
  }

  if (status === "queued") {
    return `<span class="badge bg-primary">Queued To NHQ</span>`;
  }

  if (status === "confirmed") {
    return `<span class="badge bg-success">Confirmed</span>`;
  }

  return "";
}

// Renders PHQ cards, district submissions table, and NHQ queue table.
function renderPHQDashboard() {
  clearExpiredConfirmations();

  const totalBranchesEl = document.getElementById("phqTotalBranches");
  const pendingCountEl = document.getElementById("phqPendingCount");
  const readyCountEl = document.getElementById("phqReadyCount");
  const submissionsTable = document.getElementById("phqSubmissionsTable");
  const nhqQueueTable = document.getElementById("phqNhqQueueTable");

  if (totalBranchesEl) {
    const uniqueBranches = new Set(phqSubmissions.map(item => item.district));
    totalBranchesEl.textContent = String(uniqueBranches.size);
  }

  if (pendingCountEl) {
    pendingCountEl.textContent = String(phqSubmissions.filter(item => item.status === "pending").length);
  }

  if (readyCountEl) {
    readyCountEl.textContent = String(phqSubmissions.filter(item => item.status === "approved" || item.status === "queued").length);
  }

  if (submissionsTable) {
    submissionsTable.innerHTML = phqSubmissions.map((item, index) => `
      <tr>
        <td>${item.district}</td>
        <td>${item.name}</td>
        <td>${item.currentRank}</td>
        <td>${item.recommendedRank}</td>
        <td>${getPHQStatusBadge(item.status)}</td>
        <td>
          <div class="d-flex flex-wrap gap-2">
            ${item.status === "pending" ? `
              <button class="btn btn-sm btn-success" onclick="approveSubmission(${index})">Approve</button>
              <button class="btn btn-sm btn-danger" onclick="rejectSubmission(${index})">Reject</button>
            ` : ""}
            ${item.status === "approved" ? `
              <button class="btn btn-sm btn-primary" onclick="pushSingleToNHQ(${index})">Push</button>
            ` : ""}
            ${item.status === "queued" ? '<span class="text-primary fw-semibold">Pushed To NHQ</span>' : ""}
            ${item.status === "rejected" ? '<span class="text-muted">No action</span>' : ""}
          </div>
        </td>
      </tr>
    `).join("");
  }

  if (nhqQueueTable) {
    const approvedItems = phqSubmissions.filter(item => item.status === "approved" || item.status === "queued");
    nhqQueueTable.innerHTML = approvedItems.map(item => `
      <tr>
        <td>${item.district}</td>
        <td>${item.name}</td>
        <td>${item.recommendedRank}</td>
        <td>${getPHQStatusBadge(item.status)}</td>
      </tr>
    `).join("");
  }

  renderOverviewAnalytics();
}

// Approves one branch submission.
function approveSubmission(index) {
  const item = phqSubmissions[index];
  if (item?.district === "Province Members") {
    const member = provinceMembers.find(entry => entry.name === item.name);
    if (member) {
      member.status = "approved";
      member.recommendedRank = item.recommendedRank;
    }
  }

  phqSubmissions[index].status = "approved";
  renderTable();
  renderPHQDashboard();
}

// Rejects one branch submission.
function rejectSubmission(index) {
  const item = phqSubmissions[index];
  if (item?.district === "Province Members") {
    const member = provinceMembers.find(entry => entry.name === item.name);
    if (member) {
      member.status = "flagged";
      member.recommendedRank = item.recommendedRank;
    }
  }

  phqSubmissions[index].status = "rejected";
  renderTable();
  renderPHQDashboard();
}

// Bulk action for all pending submissions.
function approveAllSubmissions() {
  phqSubmissions.forEach(item => {
    if (item.status === "pending") {
      item.status = "approved";
    }
  });
  renderPHQDashboard();
}

// Pushes approved provincial items into NHQ queue state.
function pushApprovedToNHQ() {
  phqSubmissions.forEach(item => {
    if (item.status === "approved") {
      item.status = "queued";
    }
  });
  renderPHQDashboard();
}

function pushSingleToNHQ(index) {
  if (!phqSubmissions[index]) return;
  if (phqSubmissions[index].status === "approved") {
    phqSubmissions[index].status = "queued";
  }
  renderPHQDashboard();
}

function renderConfirmationList() {
  clearExpiredConfirmations();

  const table = document.getElementById("confirmationTable");
  if (!table) return;

  const searchInput = document.getElementById("confirmationSearchInput")?.value.toLowerCase() || "";
  const approvedItems = phqSubmissions
    .map((item, index) => ({ ...item, originalIndex: index }))
    .filter(item =>
      (item.status === "approved" || item.status === "queued") &&
      item.name.toLowerCase().includes(searchInput)
    );

  table.innerHTML = approvedItems.length ? approvedItems.map(item => `
    <tr>
      <td>${item.district}</td>
      <td>${item.name}</td>
      <td>${item.currentRank}</td>
      <td>${item.recommendedRank}</td>
      <td>${getPHQStatusBadge(item.status)}</td>
      <td>
        <button class="btn btn-sm btn-dark" onclick="confirmOrdination(${item.originalIndex})">Confirm Attendance</button>
      </td>
    </tr>
  `).join("") : `
    <tr>
      <td colspan="6" class="text-center text-muted">No approved members found.</td>
    </tr>
  `;
}

function confirmOrdination(index) {
  const item = phqSubmissions[index];
  if (!item || (item.status !== "approved" && item.status !== "queued")) return;

  const member = provinceMembers.find(entry => entry.name === item.name);
  if (member) {
    member.rank = item.recommendedRank;
    member.status = "confirmed";
    member.recommendedRank = null;
    member.confirmedAt = Date.now();
  }

  item.currentRank = item.recommendedRank;
  item.status = "confirmed";
  item.confirmedAt = Date.now();

  renderTable();
  renderPHQDashboard();
  renderConfirmationList();
}

function clearExpiredConfirmations() {
  const now = Date.now();

  provinceMembers.forEach(member => {
    if (member.status === "confirmed" && member.confirmedAt && now - member.confirmedAt >= confirmationWindowMs) {
      member.status = "";
      delete member.confirmedAt;
    }
  });

  phqSubmissions = phqSubmissions.filter(item => {
    if (item.status === "confirmed" && item.confirmedAt && now - item.confirmedAt >= confirmationWindowMs) {
      return false;
    }
    return true;
  });
}

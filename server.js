const express = require("express");
const path = require("path");

const app = express();
const PORT = Number(process.env.PORT) || 3001;

app.use(express.urlencoded({ extended: true }));
app.use("/assets", express.static(path.join(__dirname, "Assets")));

app.get("/", (req, res) => res.sendFile(path.join(__dirname, "pages", "index.html")));
app.get("/signin", (req, res) => res.sendFile(path.join(__dirname, "pages", "signin.html")));
app.get("/signup", (req, res) => res.sendFile(path.join(__dirname, "pages", "signin.html")));
app.get("/dashboard", (req, res) => res.sendFile(path.join(__dirname, "pages", "province-dashboard.html")));
app.get("/province-dashboard", (req, res) => res.sendFile(path.join(__dirname, "pages", "province-dashboard.html")));
app.get("/branch-dashboard", (req, res) => res.sendFile(path.join(__dirname, "pages", "branch-dashboard.html")));
app.get("/district-dashboard", (req, res) => res.sendFile(path.join(__dirname, "pages", "district-dashboard.html")));
app.get("/nhq-dashboard", (req, res) => res.sendFile(path.join(__dirname, "pages", "nhq-dashboard.html")));

app.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
});

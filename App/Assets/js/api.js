class Api {
  constructor() {
    this.baseUrl = "/API/index.php";
  }

  async get(endpoint) {
    // Ensure endpoint starts with /
    if (!endpoint.startsWith("/")) {
      endpoint = "/" + endpoint;
    }
    const response = await fetch(`${this.baseUrl}${endpoint}`);
    return response.json();
  }

  async post(endpoint, data) {
    // Ensure endpoint starts with /
    if (!endpoint.startsWith("/")) {
      endpoint = "/" + endpoint;
    }
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });
    return response.json();
  }
}

const api = new Api();

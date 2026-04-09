import { buildApiUrl } from "@/lib/config";

export async function apiFetch(path, options = {}) {
  const response = await fetch(buildApiUrl(path), {
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {})
    },
    ...options
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch (error) {
    payload = null;
  }

  if (!response.ok) {
    const message = payload?.error || `Request failed with status ${response.status}`;
    const err = new Error(message);
    err.status = response.status;
    err.payload = payload;
    throw err;
  }

  return payload;
}

export async function loadSession() {
  return apiFetch("session");
}

export async function loginRequest(username, password) {
  return apiFetch("login", {
    method: "POST",
    body: JSON.stringify({ username, password })
  });
}

export async function logoutRequest() {
  return apiFetch("logout", { method: "POST" });
}


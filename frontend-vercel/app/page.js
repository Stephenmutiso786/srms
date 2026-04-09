"use client";

import { useState } from "react";
import { appConfig } from "@/lib/config";
import { loginRequest } from "@/lib/api";

export default function HomePage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event) {
    event.preventDefault();
    setLoading(true);
    setError("");
    try {
      const result = await loginRequest(username, password);
      const portalMap = {
        admin: "/admin",
        academic: "/academic",
        teacher: "/teacher",
        student: "/student",
        parent: "/parent",
        accountant: "/accountant",
      };
      window.location.href = portalMap[result.portal] || "/";
    } catch (err) {
      setError(err.message || "Unable to sign in right now.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="legacy-login-shell">
      <section className="legacy-login-card">
        <h1 className="legacy-login-title">{appConfig.name}</h1>
        <p className="legacy-login-subtitle">Student Results Management System</p>

        <form className="legacy-login-form" onSubmit={handleSubmit}>
          <label className="legacy-login-label">
            Username
            <input
              className="legacy-login-input"
              value={username}
              onChange={(event) => setUsername(event.target.value)}
              placeholder="Email or Registration Number"
              required
            />
          </label>

          <label className="legacy-login-label">
            Password
            <div className="legacy-password-wrap">
              <input
                className="legacy-login-input legacy-password-input"
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Login Password"
                required
              />
              <button
                type="button"
                className="legacy-password-toggle"
                onClick={() => setShowPassword((prev) => !prev)}
              >
                {showPassword ? "Hide" : "Show"}
              </button>
            </div>
          </label>

          {error ? <div className="error-box">{error}</div> : null}

          <button className="legacy-login-button" disabled={loading}>
            {loading ? "SIGNING IN..." : "SIGN IN"}
          </button>
        </form>

        <div className="legacy-login-footnote">
          API backend: <strong>{appConfig.apiBaseUrl}</strong>
        </div>
      </section>
    </main>
  );
}

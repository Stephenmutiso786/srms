"use client";

import { useMemo, useState } from "react";
import { appConfig } from "@/lib/config";
import { loginRequest } from "@/lib/api";

export default function HomePage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  const backendHost = useMemo(() => appConfig.backendUrl, []);

  async function handleSubmit(event) {
    event.preventDefault();
    setLoading(true);
    setError("");
    try {
      const result = await loginRequest(username, password);
      const portalMap = {
        admin: `${backendHost}/admin`,
        academic: `${backendHost}/academic`,
        teacher: "/teacher",
        student: "/student",
        parent: "/parent",
        accountant: `${backendHost}/accountant`
      };
      window.location.href = portalMap[result.portal] || result.redirect || backendHost;
    } catch (err) {
      setError(err.message || "Unable to sign in right now.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="legacy-login-shell">
      <section className="legacy-login-card">
        <div className="legacy-login-logo-wrap">
          <img
            className="legacy-login-logo"
            src={`${backendHost}/images/logo/logo.png`}
            alt="School logo"
            onError={(event) => {
              event.currentTarget.style.display = "none";
            }}
          />
        </div>

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

          <div className="legacy-login-links">
            <a href={`${backendHost}/`} target="_blank" rel="noreferrer">
              Open main system
            </a>
          </div>

          {error ? <div className="error-box">{error}</div> : null}

          <button className="legacy-login-button" disabled={loading}>
            {loading ? "SIGNING IN..." : "SIGN IN"}
          </button>
        </form>

        <div className="legacy-login-footnote">
          Connected to live backend: <strong>{backendHost}</strong>
        </div>
      </section>
    </main>
  );
}

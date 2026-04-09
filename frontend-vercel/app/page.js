"use client";

import { useMemo, useState } from "react";
import { appConfig } from "@/lib/config";
import { loginRequest } from "@/lib/api";

export default function HomePage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

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
    <main className="landing-shell">
      <section className="landing-hero">
        <p className="eyebrow">Elimu Hub Cloud Frontend</p>
        <h1>Fast frontend on Vercel. Real data on Render.</h1>
        <p className="hero-copy">
          This frontend now talks to the live Render backend through secure APIs.
          Sign in here to open the student, parent, or teacher portals without
          relying on the older PHP-rendered interface for those views.
        </p>
        <div className="backend-banner">
          <span>Backend:</span>
          <strong>{backendHost}</strong>
        </div>
      </section>

      <section className="login-grid">
        <div className="feature-card">
          <h2>Sign In</h2>
          <p>Use the same school account you already use on the main system.</p>
          <form className="login-form" onSubmit={handleSubmit}>
            <label>
              Username or Email
              <input
                className="text-input"
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                placeholder="Enter username or email"
                required
              />
            </label>
            <label>
              Password
              <input
                className="text-input"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Enter password"
                required
              />
            </label>
            {error ? <div className="error-box">{error}</div> : null}
            <button className="primary-button button-reset" disabled={loading}>
              {loading ? "Signing in..." : "Sign In"}
            </button>
          </form>
        </div>

        <div className="feature-card">
          <h2>What’s already live</h2>
          <ul className="check-list">
            <li>Cross-site login from Vercel to the Render backend</li>
            <li>Student dashboard + published report card API flow</li>
            <li>Parent dashboard + child-scoped report card API flow</li>
            <li>Teacher dashboard with class/subject analytics</li>
          </ul>
          <a className="outline-button" href={appConfig.backendUrl}>
            Open legacy backend
          </a>
        </div>
      </section>
    </main>
  );
}

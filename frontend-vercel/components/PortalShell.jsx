"use client";

import { logoutRequest } from "@/lib/api";

export default function PortalShell({
  portal,
  title,
  subtitle,
  user,
  nav = [],
  summary = [],
  actions = null,
  children
}) {
  async function handleLogout() {
    try {
      await logoutRequest();
    } finally {
      window.location.href = "/";
    }
  }

  return (
    <div className="portal-shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark">E</div>
          <div>
            <div className="brand-title">Elimu Hub</div>
            <div className="brand-subtitle">{title}</div>
          </div>
        </div>

        <div className="sidebar-user">
          <div className="sidebar-user-name">{user?.name || "Portal User"}</div>
          <div className="sidebar-user-meta">{portal}</div>
        </div>

        <nav className="nav-list">
          {nav.map((item) => (
            <a
              key={item.label}
              className={`nav-item ${item.active ? "active" : ""}`}
              href={item.href}
            >
              {item.label}
            </a>
          ))}
        </nav>
      </aside>

      <main className="main-panel">
        <header className="topbar">
          <div>
            <div className="page-title">{title}</div>
            <div className="page-subtitle">{subtitle}</div>
          </div>
          <div className="topbar-actions">
            {actions}
            <button className="outline-button button-reset" onClick={handleLogout}>
              Logout
            </button>
          </div>
        </header>

        {summary.length > 0 ? (
          <section className="metrics-grid">
            {summary.map((item) => (
              <div className="metric-card" key={item.label}>
                <div className="metric-label">{item.label}</div>
                <div className="metric-value">{item.value}</div>
              </div>
            ))}
          </section>
        ) : null}

        {children}
      </main>
    </div>
  );
}


const navConfig = {
  student: [
    { label: "Overview", href: "/student" },
    { label: "Performance", href: "#" },
    { label: "Report Cards", href: "#" },
    { label: "E-Learning", href: "#" }
  ],
  teacher: [
    { label: "Overview", href: "/teacher" },
    { label: "Exams", href: "#" },
    { label: "Marks Entry", href: "#" },
    { label: "Classes", href: "#" }
  ],
  parent: [
    { label: "Overview", href: "/parent" },
    { label: "Report Cards", href: "#" },
    { label: "Attendance", href: "#" },
    { label: "Fees", href: "#" }
  ]
};

export default function PortalShell({
  portal,
  title,
  subtitle,
  summary = [],
  children
}) {
  const nav = navConfig[portal] || [];

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
        <nav className="nav-list">
          {nav.map((item) => (
            <a key={item.label} className="nav-item" href={item.href}>
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
          <a className="outline-button" href="/">
            Home
          </a>
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

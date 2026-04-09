"use client";

import { useEffect, useMemo, useState } from "react";
import PortalShell from "@/components/PortalShell";
import { apiFetch, loadSession } from "@/lib/api";

export default function TeacherPage() {
  const [session, setSession] = useState(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ class_id: "", subject_id: "", term_id: "" });

  async function loadDashboard(nextFilters = {}) {
    setLoading(true);
    setError("");
    try {
      const sessionPayload = await loadSession();
      if (!sessionPayload?.authenticated) {
        window.location.href = "/";
        return;
      }
      if (sessionPayload.user.portal !== "teacher") {
        window.location.href = `/${sessionPayload.user.portal}`;
        return;
      }
      setSession(sessionPayload);
      const params = new URLSearchParams();
      Object.entries(nextFilters).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const dashboard = await apiFetch(`teacher/dashboard${params.toString() ? `?${params.toString()}` : ""}`);
      setData(dashboard);
      setFilters({
        class_id: String(dashboard.selected.class_id || ""),
        subject_id: String(dashboard.selected.subject_id || ""),
        term_id: String(dashboard.selected.term_id || "")
      });
    } catch (err) {
      setError(err.message || "Unable to load teacher analytics.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadDashboard();
  }, []);

  const summary = useMemo(() => {
    if (!data) return [];
    return [
      { label: "Classes", value: data.summary.classes ?? 0 },
      { label: "Subjects", value: data.summary.subjects ?? 0 },
      { label: "Students", value: data.summary.students ?? 0 },
      { label: "Average", value: Number(data.summary.avg || 0).toFixed(2) }
    ];
  }, [data]);

  if (loading && !data) {
    return <main className="loading-screen">Loading teacher dashboard...</main>;
  }

  return (
    <PortalShell
      portal="teacher"
      title="Teacher Portal"
      subtitle="Class-aware and subject-aware performance analytics."
      user={session?.user}
      nav={[
        { label: "Overview", href: "/teacher", active: true },
        { label: "Open Exams", href: `${process.env.NEXT_PUBLIC_BACKEND_URL || "https://srms-n7g2.onrender.com"}/teacher/exam_marks_entry` }
      ]}
      summary={summary}
      actions={
        <div className="inline-selects">
          <select className="topbar-select" value={filters.class_id} onChange={(event) => loadDashboard({ ...filters, class_id: event.target.value })}>
            {Object.entries(data?.options?.classes || {}).map(([id, name]) => (
              <option key={id} value={id}>{name}</option>
            ))}
          </select>
          <select className="topbar-select" value={filters.subject_id} onChange={(event) => loadDashboard({ ...filters, subject_id: event.target.value })}>
            {Object.entries(data?.options?.subjects || {}).map(([id, name]) => (
              <option key={id} value={id}>{name}</option>
            ))}
          </select>
          <select className="topbar-select" value={filters.term_id} onChange={(event) => loadDashboard({ ...filters, term_id: event.target.value })}>
            {Object.entries(data?.options?.terms || {}).map(([id, name]) => (
              <option key={id} value={id}>{name}</option>
            ))}
          </select>
        </div>
      }
    >
      {error ? <div className="error-box">{error}</div> : null}

      <section className="content-card">
        <h2>Current Selection</h2>
        <div className="detail-grid">
          <div className="detail-card"><span className="detail-label">Class</span><strong>{data?.options?.classes?.[data?.selected?.class_id] || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">Subject</span><strong>{data?.options?.subjects?.[data?.selected?.subject_id] || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">Term</span><strong>{data?.options?.terms?.[data?.selected?.term_id] || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">Best Score</span><strong>{Number(data?.summary?.best || 0).toFixed(0)}</strong></div>
        </div>
      </section>

      <section className="content-card">
        <h2>Student Performance</h2>
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>School ID</th>
                <th>Score</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {(data?.rows || []).length === 0 ? (
                <tr><td colSpan="4" className="empty-cell">No marks available for the current class, subject, and term.</td></tr>
              ) : data.rows.map((row) => (
                <tr key={row.student_id}>
                  <td>{row.student_name}</td>
                  <td>{row.school_id || "N/A"}</td>
                  <td>{Number(row.score || 0).toFixed(1)}</td>
                  <td><span className="badge-chip">{row.grade}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="content-card">
        <h2>Trend by Term</h2>
        <div className="trend-bars">
          {(data?.trend || []).length === 0 ? (
            <div className="empty-cell">No term trend data yet.</div>
          ) : data.trend.map((point) => (
            <div className="trend-bar-item" key={point.term_name}>
              <div className="trend-bar-label">{point.term_name}</div>
              <div className="trend-bar-track">
                <div className="trend-bar-fill" style={{ width: `${Math.max(4, Number(point.mean || 0))}%` }} />
              </div>
              <div className="trend-bar-value">{Number(point.mean || 0).toFixed(1)}%</div>
            </div>
          ))}
        </div>
      </section>
    </PortalShell>
  );
}


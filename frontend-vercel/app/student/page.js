"use client";

import { useEffect, useMemo, useState } from "react";
import PortalShell from "@/components/PortalShell";
import { apiFetch, loadSession } from "@/lib/api";

export default function StudentPage() {
  const [session, setSession] = useState(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [termId, setTermId] = useState("");

  async function loadDashboard(nextTermId = "") {
    setLoading(true);
    setError("");
    try {
      const sessionPayload = await loadSession();
      if (!sessionPayload?.authenticated) {
        window.location.href = "/";
        return;
      }
      if (sessionPayload.user.portal !== "student") {
        window.location.href = `/${sessionPayload.user.portal}`;
        return;
      }
      setSession(sessionPayload);
      const query = nextTermId ? `?term_id=${encodeURIComponent(nextTermId)}` : "";
      const dashboard = await apiFetch(`student/dashboard${query}`);
      setData(dashboard);
      setTermId(String(dashboard.selected_term_id || ""));
    } catch (err) {
      setError(err.message || "Unable to load the student dashboard.");
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
      { label: "Mean Score", value: `${Number(data.summary.mean || 0).toFixed(2)}%` },
      { label: "Grade", value: data.summary.grade || "N/A" },
      { label: "Position", value: data.summary.position || "-" },
      { label: "Attendance", value: `${Number(data.summary.attendance_rate || 0).toFixed(1)}%` }
    ];
  }, [data]);

  if (loading && !data) {
    return <main className="loading-screen">Loading student dashboard...</main>;
  }

  return (
    <PortalShell
      portal="student"
      title="Student Portal"
      subtitle="Published performance analytics, report cards, and academic progress."
      user={session?.user}
      nav={[
        { label: "Overview", href: "/student", active: true },
        { label: "Report Card", href: "/student/report-card" }
      ]}
      summary={summary}
      actions={
        <select
          className="topbar-select"
          value={termId}
          onChange={(event) => loadDashboard(event.target.value)}
        >
          <option value="">Latest published term</option>
          {(data?.terms || []).map((term) => (
            <option key={term.id} value={term.id}>
              {term.name}
            </option>
          ))}
        </select>
      }
    >
      {error ? <div className="error-box">{error}</div> : null}

      <section className="content-card">
        <div className="section-title-row">
          <div>
            <h2>Student Summary</h2>
            <p className="section-copy">
              View only published terms. The backend still controls moderation,
              finalization, and publishing.
            </p>
          </div>
          {data?.report_card?.download_url ? (
            <a className="outline-button" href={data.report_card.download_url} target="_blank">
              Download PDF
            </a>
          ) : null}
        </div>
        <div className="detail-grid">
          <div className="detail-card">
            <span className="detail-label">Student</span>
            <strong>{data?.student?.name || session?.user?.name}</strong>
          </div>
          <div className="detail-card">
            <span className="detail-label">Class</span>
            <strong>{data?.student?.class_name || session?.user?.class_name || "N/A"}</strong>
          </div>
          <div className="detail-card">
            <span className="detail-label">School ID</span>
            <strong>{data?.student?.school_id || "N/A"}</strong>
          </div>
          <div className="detail-card">
            <span className="detail-label">Term</span>
            <strong>{data?.selected_term_name || "No published term"}</strong>
          </div>
        </div>
      </section>

      <section className="content-card">
        <h2>Subject Performance</h2>
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Score</th>
                <th>Class Mean</th>
                <th>Change</th>
                <th>Trend</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {(data?.subject_rows || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">
                    No published subject analytics yet.
                  </td>
                </tr>
              ) : (
                data.subject_rows.map((row) => (
                  <tr key={row.subject_name}>
                    <td>{row.subject_name}</td>
                    <td>{Number(row.score || 0).toFixed(1)}</td>
                    <td>{Number(row.class_mean || 0).toFixed(1)}%</td>
                    <td>{row.change >= 0 ? "+" : ""}{Number(row.change || 0).toFixed(1)}</td>
                    <td>{row.trend}</td>
                    <td><span className="badge-chip">{row.grade}</span></td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="content-card">
        <h2>Performance Over Time</h2>
        <div className="trend-bars">
          {(data?.history || []).length === 0 ? (
            <div className="empty-cell">No trend history yet.</div>
          ) : (
            data.history.map((point) => (
              <div className="trend-bar-item" key={point.term_name}>
                <div className="trend-bar-label">{point.term_name}</div>
                <div className="trend-bar-track">
                  <div className="trend-bar-fill" style={{ width: `${Math.max(4, Number(point.mean || 0))}%` }} />
                </div>
                <div className="trend-bar-value">{Number(point.mean || 0).toFixed(1)}%</div>
              </div>
            ))
          )}
        </div>
      </section>
    </PortalShell>
  );
}


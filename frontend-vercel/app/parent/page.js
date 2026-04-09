"use client";

import { useEffect, useMemo, useState } from "react";
import PortalShell from "@/components/PortalShell";
import { apiFetch, loadSession } from "@/lib/api";

export default function ParentPage() {
  const [session, setSession] = useState(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [studentId, setStudentId] = useState("");
  const [termId, setTermId] = useState("");

  async function loadDashboard(nextStudentId = "", nextTermId = "") {
    setLoading(true);
    setError("");
    try {
      const sessionPayload = await loadSession();
      if (!sessionPayload?.authenticated) {
        window.location.href = "/";
        return;
      }
      if (sessionPayload.user.portal !== "parent") {
        window.location.href = `/${sessionPayload.user.portal}`;
        return;
      }
      setSession(sessionPayload);
      const params = new URLSearchParams();
      if (nextStudentId) params.set("student_id", nextStudentId);
      if (nextTermId) params.set("term_id", nextTermId);
      const dashboard = await apiFetch(`parent/dashboard${params.toString() ? `?${params.toString()}` : ""}`);
      setData(dashboard);
      setStudentId(String(dashboard.selected_student_id || ""));
      setTermId(String(dashboard.selected_term_id || ""));
    } catch (err) {
      setError(err.message || "Unable to load the parent dashboard.");
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
      { label: "Children", value: data.summary.children ?? 0 },
      { label: "Attendance", value: `${Number(data.summary.attendance_rate || 0).toFixed(1)}%` },
      { label: "Mean Score", value: Number(data.summary.avg_score || 0).toFixed(2) },
      { label: "Grade", value: data.summary.grade || "N/A" }
    ];
  }, [data]);

  if (loading && !data) {
    return <main className="loading-screen">Loading parent dashboard...</main>;
  }

  return (
    <PortalShell
      portal="parent"
      title="Parent Portal"
      subtitle="Child-scoped performance, fees, attendance, and published report cards."
      user={session?.user}
      nav={[
        { label: "Overview", href: "/parent", active: true },
        { label: "Report Card", href: `/parent/report-card?student_id=${encodeURIComponent(studentId || "")}` }
      ]}
      summary={summary}
      actions={
        <div className="inline-selects">
          <select className="topbar-select" value={studentId} onChange={(event) => loadDashboard(event.target.value, termId)}>
            {(data?.students || []).map((student) => (
              <option key={student.id} value={student.id}>
                {student.name}
              </option>
            ))}
          </select>
          <select className="topbar-select" value={termId} onChange={(event) => loadDashboard(studentId, event.target.value)}>
            {(data?.terms || []).map((term) => (
              <option key={term.id} value={term.id}>
                {term.name}
              </option>
            ))}
          </select>
        </div>
      }
    >
      {error ? <div className="error-box">{error}</div> : null}

      <section className="content-card">
        <h2>Selected Child</h2>
        <div className="detail-grid">
          <div className="detail-card"><span className="detail-label">Student</span><strong>{data?.selected_student?.name || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">Class</span><strong>{data?.selected_student?.class_name || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">School ID</span><strong>{data?.selected_student?.school_id || "N/A"}</strong></div>
          <div className="detail-card"><span className="detail-label">Fees Balance</span><strong>KES {Number(data?.summary?.fees_balance || 0).toFixed(2)}</strong></div>
        </div>
      </section>

      <section className="content-card">
        <div className="section-title-row">
          <h2>Subject Performance</h2>
          {studentId ? (
            <a className="outline-button" href={`/parent/report-card?student_id=${encodeURIComponent(studentId)}&term_id=${encodeURIComponent(termId)}`}>
              Open Report Card
            </a>
          ) : null}
        </div>
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Score</th>
                <th>Class Mean</th>
                <th>Change</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              {(data?.subject_rows || []).length === 0 ? (
                <tr><td colSpan="5" className="empty-cell">No published subject data yet.</td></tr>
              ) : data.subject_rows.map((row) => (
                <tr key={row.subject_name}>
                  <td>{row.subject_name}</td>
                  <td>{Number(row.score || 0).toFixed(1)}</td>
                  <td>{Number(row.class_mean || 0).toFixed(1)}</td>
                  <td>{row.change >= 0 ? "+" : ""}{Number(row.change || 0).toFixed(1)}</td>
                  <td><span className="badge-chip">{row.grade}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="content-card">
        <h2>Notifications</h2>
        <div className="notification-list">
          {(data?.notifications || []).length === 0 ? (
            <div className="empty-cell">No notifications yet.</div>
          ) : data.notifications.map((note, index) => (
            <div className="notification-card" key={`${note.title}-${index}`}>
              <strong>{note.title}</strong>
              <p>{note.message}</p>
              <span>{note.created_at}</span>
            </div>
          ))}
        </div>
      </section>
    </PortalShell>
  );
}


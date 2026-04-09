"use client";

import { useEffect, useState } from "react";
import PortalShell from "@/components/PortalShell";
import { apiFetch, loadSession } from "@/lib/api";

export default function ParentReportCardPage() {
  const [session, setSession] = useState(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [selfHref, setSelfHref] = useState("/parent/report-card");

  useEffect(() => {
    (async () => {
      try {
        const url = new URL(window.location.href);
        const studentId = url.searchParams.get("student_id") || "";
        const termId = url.searchParams.get("term_id") || "";
        setSelfHref(url.pathname + url.search);
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
        if (studentId) params.set("student_id", studentId);
        if (termId) params.set("term_id", termId);
        const report = await apiFetch(`parent/report_card?${params.toString()}`);
        setData(report);
      } catch (err) {
        setError(err.message || "Unable to load the report card.");
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading && !data) {
    return <main className="loading-screen">Loading report card...</main>;
  }

  return (
    <PortalShell
      portal="parent"
      title="Parent Portal"
      subtitle="Official child report card."
      user={session?.user}
      nav={[
        { label: "Overview", href: "/parent" },
        { label: "Report Card", href: selfHref, active: true }
      ]}
      actions={
        data?.download_url ? (
          <a className="outline-button" href={data.download_url} target="_blank">
            Download PDF
          </a>
        ) : null
      }
    >
      {error ? <div className="error-box">{error}</div> : null}
      {data?.report_card ? (
        <section className="content-card">
          <h2>{data.student?.name}</h2>
          <div className="detail-grid">
            <div className="detail-card"><span className="detail-label">School ID</span><strong>{data.student?.school_id || "N/A"}</strong></div>
            <div className="detail-card"><span className="detail-label">Class</span><strong>{data.student?.class_name || "N/A"}</strong></div>
            <div className="detail-card"><span className="detail-label">Mean</span><strong>{Number(data.report_card.mean || 0).toFixed(2)}%</strong></div>
            <div className="detail-card"><span className="detail-label">Grade</span><strong>{data.report_card.grade || "N/A"}</strong></div>
          </div>
          <div className="table-scroll mt-16">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Subject</th>
                  <th>Score</th>
                  <th>Grade</th>
                  <th>Teacher</th>
                </tr>
              </thead>
              <tbody>
                {(data.report_card.subjects || []).map((subject) => (
                  <tr key={subject.subject_id || subject.subject_name}>
                    <td>{subject.subject_name}</td>
                    <td>{Number(subject.score || 0).toFixed(1)}</td>
                    <td><span className="badge-chip">{subject.grade}</span></td>
                    <td>{subject.teacher_name || "N/A"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}
    </PortalShell>
  );
}

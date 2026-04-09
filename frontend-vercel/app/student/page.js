import PortalShell from "@/components/PortalShell";

export default function StudentPage() {
  return (
    <PortalShell
      portal="student"
      title="Student Portal"
      subtitle="Vercel-ready portal shell using the Elimu Hub theme"
      summary={[
        { label: "Migration Stage", value: "Phase 1" },
        { label: "Backend", value: "PHP on Render" },
        { label: "Frontend", value: "Next.js on Vercel" }
      ]}
    >
      <section className="content-card">
        <h2>What moves first</h2>
        <ul className="check-list">
          <li>Authentication handoff to the backend</li>
          <li>Published student dashboard and analytics views</li>
          <li>Report card and performance pages</li>
          <li>E-learning pages after core academic flows are stable</li>
        </ul>
      </section>
    </PortalShell>
  );
}

import PortalShell from "@/components/PortalShell";

export default function TeacherPage() {
  return (
    <PortalShell
      portal="teacher"
      title="Teacher Portal"
      subtitle="Teacher workflows will stay exam-aware and class-aware during migration"
      summary={[
        { label: "Exam Module", value: "Kept visible" },
        { label: "Scope", value: "Class + Subject" },
        { label: "Migration Stage", value: "Planned" }
      ]}
    >
      <section className="content-card">
        <h2>Teacher migration notes</h2>
        <ul className="check-list">
          <li>Marks entry stays tied to real teacher allocations</li>
          <li>Exam lifecycle remains on the backend until API endpoints are exposed</li>
          <li>Class and subject analytics can move next without changing business rules</li>
        </ul>
      </section>
    </PortalShell>
  );
}

import PortalShell from "@/components/PortalShell";

export default function ParentPage() {
  return (
    <PortalShell
      portal="parent"
      title="Parent Portal"
      subtitle="Parent views can migrate safely once student published-result APIs are exposed"
      summary={[
        { label: "Published Results", value: "Required" },
        { label: "Child Scope", value: "Role-limited" },
        { label: "Migration Stage", value: "Planned" }
      ]}
    >
      <section className="content-card">
        <h2>Parent migration notes</h2>
        <ul className="check-list">
          <li>Only published report cards should be exposed to the new frontend</li>
          <li>Attendance and fees remain scoped to linked children only</li>
          <li>Portal branding matches the current Elimu Hub theme</li>
        </ul>
      </section>
    </PortalShell>
  );
}

import { appConfig, buildLegacyUrl } from "@/lib/config";

const cards = [
  {
    title: "Student Portal",
    description: "Move the student experience to Vercel first, then migrate other portals in phases.",
    href: "/student"
  },
  {
    title: "Teacher Portal",
    description: "Set up class analytics, marks entry flows, and exam views on the new frontend.",
    href: "/teacher"
  },
  {
    title: "Parent Portal",
    description: "Bring report cards, attendance, and fees into the new frontend without breaking the backend.",
    href: "/parent"
  }
];

export default function HomePage() {
  return (
    <main className="landing-shell">
      <section className="landing-hero">
        <p className="eyebrow">Vercel Migration</p>
        <h1>Elimu Hub frontend foundation</h1>
        <p className="hero-copy">
          This is the starting point for moving the UI to Vercel while the PHP backend
          continues running on Render. We will migrate portal by portal instead of trying
          to move the current PHP-rendered pages directly.
        </p>
        <div className="button-row">
          <a className="primary-button" href={buildLegacyUrl("student/")}>
            Open current live student portal
          </a>
          <a className="outline-button" href="https://vercel.com/new">
            Create Vercel project
          </a>
        </div>
        <div className="backend-banner">
          <span>Current backend:</span>
          <strong>{appConfig.backendUrl}</strong>
        </div>
      </section>

      <section className="card-grid">
        {cards.map((card) => (
          <a className="feature-card" key={card.title} href={card.href}>
            <h2>{card.title}</h2>
            <p>{card.description}</p>
          </a>
        ))}
      </section>
    </main>
  );
}

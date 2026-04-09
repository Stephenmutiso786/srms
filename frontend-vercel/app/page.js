"use client";

import { useEffect } from "react";
import { buildLegacyUrl } from "@/lib/config";

export default function HomePage() {
  useEffect(() => {
    window.location.replace(buildLegacyUrl("/"));
  }, []);

  return (
    <main className="legacy-login-shell">
      <section className="legacy-login-card">
        <h1 className="legacy-login-title">Opening Elimu Hub Login…</h1>
        <p className="legacy-login-subtitle">
          We’re keeping the original login page on Render so the entry experience
          stays exactly like the system you started with.
        </p>
        <a className="legacy-login-button legacy-login-link" href={buildLegacyUrl("/")}>
          Open Original Login
        </a>
      </section>
    </main>
  );
}

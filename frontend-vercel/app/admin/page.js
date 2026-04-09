"use client";

import { useEffect } from "react";
import { buildLegacyUrl } from "@/lib/config";

export default function AdminRedirectPage() {
  useEffect(() => {
    window.location.replace(buildLegacyUrl("/admin"));
  }, []);

  return <main className="loading-screen">Opening the admin portal on Render...</main>;
}

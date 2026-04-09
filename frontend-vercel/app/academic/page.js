"use client";

import { useEffect } from "react";
import { buildLegacyUrl } from "@/lib/config";

export default function AcademicRedirectPage() {
  useEffect(() => {
    window.location.replace(buildLegacyUrl("/academic"));
  }, []);

  return <main className="loading-screen">Opening the academic portal on Render...</main>;
}

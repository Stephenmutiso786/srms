"use client";

import { useEffect } from "react";
import { buildLegacyUrl } from "@/lib/config";

export default function AccountantRedirectPage() {
  useEffect(() => {
    window.location.replace(buildLegacyUrl("/accountant"));
  }, []);

  return <main className="loading-screen">Opening the accountant portal on Render...</main>;
}

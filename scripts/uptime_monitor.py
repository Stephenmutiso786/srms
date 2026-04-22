#!/usr/bin/env python3
"""Lightweight uptime probe for Elimu Hub.

Checks /api/health and /api/health?deep=1 repeatedly, prints concise status,
and optionally appends results to a CSV log file for trend analysis.
"""

from __future__ import annotations

import argparse
import csv
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path


@dataclass
class ProbeResult:
    code: int
    total_seconds: float
    error: str

    @property
    def ok(self) -> bool:
        return self.error == "" and self.code == 200


def now_utc_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def probe(url: str, timeout: float) -> ProbeResult:
    started = time.perf_counter()
    req = urllib.request.Request(url, method="GET")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            code = int(getattr(resp, "status", 0) or 0)
            elapsed = time.perf_counter() - started
            return ProbeResult(code=code, total_seconds=elapsed, error="")
    except urllib.error.HTTPError as exc:
        elapsed = time.perf_counter() - started
        return ProbeResult(code=int(exc.code), total_seconds=elapsed, error=f"http:{exc.code}")
    except urllib.error.URLError as exc:
        elapsed = time.perf_counter() - started
        return ProbeResult(code=0, total_seconds=elapsed, error=f"url:{exc.reason}")
    except TimeoutError:
        elapsed = time.perf_counter() - started
        return ProbeResult(code=0, total_seconds=elapsed, error="timeout")
    except Exception as exc:  # pragma: no cover
        elapsed = time.perf_counter() - started
        return ProbeResult(code=0, total_seconds=elapsed, error=f"err:{type(exc).__name__}")


def ensure_csv_header(path: Path) -> None:
    if path.exists() and path.stat().st_size > 0:
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(
            [
                "timestamp_utc",
                "basic_code",
                "basic_seconds",
                "basic_error",
                "deep_code",
                "deep_seconds",
                "deep_error",
                "overall_status",
            ]
        )


def append_csv(path: Path, row: list[str]) -> None:
    with path.open("a", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(row)


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Uptime monitor for Elimu Hub health endpoints")
    p.add_argument("--base-url", default="https://elimuhub.tech", help="Base URL, e.g. https://elimuhub.tech")
    p.add_argument("--interval", type=float, default=10.0, help="Seconds between samples")
    p.add_argument("--samples", type=int, default=0, help="Number of samples (0 = run forever)")
    p.add_argument("--timeout", type=float, default=8.0, help="Per-request timeout in seconds")
    p.add_argument("--slow-threshold", type=float, default=5.0, help="Warn when response time exceeds this")
    p.add_argument("--csv", default="", help="Optional CSV output path")
    p.add_argument(
        "--strict",
        action="store_true",
        help="Exit code 1 if any sample had a failed health check",
    )
    return p.parse_args()


def main() -> int:
    args = parse_args()
    base = args.base_url.rstrip("/")
    basic_url = f"{base}/api/health"
    deep_url = f"{base}/api/health?deep=1"

    csv_path = Path(args.csv).expanduser().resolve() if args.csv else None
    if csv_path is not None:
        ensure_csv_header(csv_path)

    print(f"Monitoring: {base}")
    print(f"Basic: {basic_url}")
    print(f"Deep : {deep_url}")
    print("Press Ctrl+C to stop.")

    i = 0
    had_failure = False

    try:
        while True:
            i += 1
            ts = now_utc_iso()

            basic = probe(basic_url, args.timeout)
            deep = probe(deep_url, args.timeout)

            basic_slow = basic.total_seconds >= args.slow_threshold
            deep_slow = deep.total_seconds >= args.slow_threshold

            if basic.ok and deep.ok and not basic_slow and not deep_slow:
                overall = "OK"
            elif basic.ok and deep.ok and (basic_slow or deep_slow):
                overall = "SLOW"
            elif basic.ok and not deep.ok:
                overall = "DEEP_FAIL"
            else:
                overall = "DOWN"

            if overall in {"DEEP_FAIL", "DOWN"}:
                had_failure = True

            print(
                " | ".join(
                    [
                        f"#{i}",
                        ts,
                        f"basic={basic.code} {basic.total_seconds:.3f}s {basic.error or '-'}",
                        f"deep={deep.code} {deep.total_seconds:.3f}s {deep.error or '-'}",
                        f"status={overall}",
                    ]
                )
            )

            if csv_path is not None:
                append_csv(
                    csv_path,
                    [
                        ts,
                        str(basic.code),
                        f"{basic.total_seconds:.6f}",
                        basic.error,
                        str(deep.code),
                        f"{deep.total_seconds:.6f}",
                        deep.error,
                        overall,
                    ],
                )

            if args.samples > 0 and i >= args.samples:
                break

            time.sleep(max(0.0, args.interval))

    except KeyboardInterrupt:
        print("Stopped by user.")

    if args.strict and had_failure:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())

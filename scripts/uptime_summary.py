#!/usr/bin/env python3
"""Summarize daily uptime from uptime_monitor.py CSV logs."""

from __future__ import annotations

import argparse
import csv
import sys
from collections import Counter
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path


@dataclass
class Sample:
    ts: datetime
    basic_code: int
    deep_code: int
    status: str


def parse_ts(value: str) -> datetime:
    return datetime.strptime(value, "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Daily uptime summary for Elimu Hub monitor logs")
    parser.add_argument("--csv", default="logs/uptime.csv", help="Path to CSV from uptime_monitor.py")
    parser.add_argument(
        "--date",
        default="yesterday",
        help="UTC date to summarize in YYYY-MM-DD format, or 'yesterday' (default)",
    )
    return parser.parse_args()


def parse_target_date(raw: str) -> datetime.date:
    if raw.strip().lower() == "yesterday":
        return (datetime.now(timezone.utc) - timedelta(days=1)).date()
    return datetime.strptime(raw, "%Y-%m-%d").date()


def load_samples(path: Path, target_date) -> list[Sample]:
    if not path.exists():
        raise FileNotFoundError(f"CSV file not found: {path}")

    samples: list[Sample] = []
    with path.open("r", newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            try:
                ts = parse_ts((row.get("timestamp_utc") or "").strip())
                if ts.date() != target_date:
                    continue
                basic_code = int((row.get("basic_code") or "0").strip() or "0")
                deep_code = int((row.get("deep_code") or "0").strip() or "0")
                status = (row.get("overall_status") or "").strip().upper()
                samples.append(Sample(ts=ts, basic_code=basic_code, deep_code=deep_code, status=status))
            except Exception:
                continue
    return samples


def pct(num: int, den: int) -> float:
    if den <= 0:
        return 0.0
    return (num / den) * 100.0


def main() -> int:
    args = parse_args()
    csv_path = Path(args.csv).expanduser().resolve()
    target_date = parse_target_date(args.date)

    try:
        samples = load_samples(csv_path, target_date)
    except FileNotFoundError as exc:
        print(str(exc), file=sys.stderr)
        return 2

    if not samples:
        print(f"No samples found for {target_date} in {csv_path}")
        return 1

    total = len(samples)
    basic_up = sum(1 for s in samples if s.basic_code == 200)
    deep_up = sum(1 for s in samples if s.deep_code == 200)
    full_ok = sum(1 for s in samples if s.basic_code == 200 and s.deep_code == 200)

    by_status = Counter(s.status or "UNKNOWN" for s in samples)

    print(f"Date (UTC): {target_date}")
    print(f"CSV: {csv_path}")
    print(f"Total samples: {total}")
    print(f"Basic availability: {basic_up}/{total} ({pct(basic_up, total):.2f}%)")
    print(f"Deep readiness: {deep_up}/{total} ({pct(deep_up, total):.2f}%)")
    print(f"Full health (both 200): {full_ok}/{total} ({pct(full_ok, total):.2f}%)")

    print("Status counts:")
    for key in sorted(by_status.keys()):
        print(f"  {key}: {by_status[key]}")

    return 0


if __name__ == "__main__":
    sys.exit(main())

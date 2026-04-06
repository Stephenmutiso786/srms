#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path


def is_insert(line: str) -> bool:
    s = line.lstrip()
    return s.startswith("INSERT INTO ")


def main() -> None:
    repo_root = Path(__file__).resolve().parents[1]
    src = repo_root / "srms" / "database" / "srms_postgres.sql"
    schema_dst = repo_root / "srms" / "database" / "srms_postgres_schema.sql"
    seed_dst = repo_root / "srms" / "database" / "srms_postgres_seed_demo.sql"

    lines = src.read_text(encoding="utf-8", errors="replace").splitlines(keepends=True)

    schema: list[str] = []
    seed: list[str] = []

    in_insert = False
    for line in lines:
        if not in_insert and is_insert(line):
            in_insert = True
            seed.append(line)
            continue

        if in_insert:
            seed.append(line)
            if ";" in line:
                in_insert = False
            continue

        schema.append(line)

    # In schema file, remove setval statements that depend on seed data
    schema = [l for l in schema if not l.lstrip().startswith("SELECT setval(pg_get_serial_sequence")]

    # Wrap seed in its own transaction
    seed_out = ["BEGIN;\n"] + seed + ["COMMIT;\n"]

    schema_dst.write_text("".join(schema), encoding="utf-8")
    seed_dst.write_text("".join(seed_out), encoding="utf-8")
    print(f"Wrote {schema_dst}")
    print(f"Wrote {seed_dst}")


if __name__ == "__main__":
    main()

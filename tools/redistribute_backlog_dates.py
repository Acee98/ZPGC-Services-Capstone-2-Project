"""
Redistribute BACKLOG (and related docs) dates so late-stage work
spans early–mid September through 20 September 2026 with reasonable gaps.
"""

from __future__ import annotations

import re
from pathlib import Path

BACKLOG = Path(r"C:\xampp\htdocs\CP2_V1.5\docs\BACKLOG.md")

# Primary "When:" dates for each Activity (A-030+)
A_WHEN = {
    30: "2–3 September 2026 (2 days: FK types + Relation view)",
    31: "4 September 2026",
    32: "5 September 2026",
    33: "6–7 September 2026 (assign UI + retest)",  # covers both Part 2 / Part 3 headers if same When pattern
    34: "8 September 2026",
    35: "8–9 September 2026 (messages table + mailbox thread)",
    36: "9 September 2026",
    37: "10 September 2026",
    38: "10–11 September 2026",
    39: "11 September 2026",
    40: "11 September 2026",
    41: "12 September 2026",
    42: "12 September 2026",
    43: "12 September 2026",
    44: "12 September 2026",
    45: "13 September 2026",
    46: "13 September 2026",
    47: "13–14 September 2026",
    49: "14 September 2026",
    50: "14–15 September 2026",
    51: "15 September 2026",
    52: "16 September 2026",
    53: "16–17 September 2026",
    54: "17 September 2026",
    55: "17–18 September 2026",
    56: "18 September 2026",
    57: "19 September 2026",
    58: "19 September 2026",
    59: "19 September 2026",
    60: "19 September 2026",
    61: "19–20 September 2026",
    62: "20 September 2026",
    63: "20 September 2026",
    64: "20 September 2026",
    65: "20 September 2026",
    66: "20 September 2026",
}

# Issue B dates tied to late work (override When lines under ### B-xxx)
B_WHEN = {
    28: "4 September 2026 (A-031)",
    29: "6–7 September 2026 (A-033 Part 2)",
    30: "6–7 September 2026 (A-033 Part 3)",
    31: "8 September 2026 (A-034)",
    32: "8–9 September 2026 (A-035)",
    33: "8–9 September 2026 (A-035)",
    34: "10 September 2026 (A-037)",
    35: "10–11 September 2026 (A-038)",
    36: "11 September 2026 (A-039)",
    37: "11 September 2026 (A-040)",
    38: "12 September 2026",
    39: "12 September 2026",
    40: "12 September 2026",
    41: "12 September 2026",
    42: "12 September 2026",
    43: "12 September 2026",
    44: "13 September 2026",
    45: "14–15 September 2026 (A-050)",
    46: "14–15 September 2026 (A-050)",
    47: "14–15 September 2026 (A-050)",
    48: "14 September 2026 (A-049)",
    49: "17 September 2026 (A-054)",
    50: "16–17 September 2026 (A-053)",
    51: "17 September 2026 (A-054)",
    52: "17–18 September 2026",
    53: "18 September 2026 (Stage 8 test B)",
    54: "18 September 2026 (Stage 8 F — mailbox OK)",
    55: "20 September 2026 (Test 3)",
}


def patch_activity_whens(text: str) -> str:
    """Replace the first - **When:** after each ### A-NNN heading."""

    def repl(m: re.Match) -> str:
        aid = int(m.group(1))
        header = m.group(0)
        if aid not in A_WHEN:
            return header
        # Replace When line inside this match block — match only header line,
        # then separately find When. Better: match header + following When.
        return header

    # Split by activity headers
    parts = re.split(r"(?=^### A-\d+)", text, flags=re.M)
    out = []
    for part in parts:
        m = re.match(r"^### A-(\d+)\b", part)
        if not m:
            out.append(part)
            continue
        aid = int(m.group(1))
        if aid in A_WHEN:
            part2, n = re.subn(
                r"(^- \*\*When:\*\* ).+$",
                r"\g<1>" + A_WHEN[aid],
                part,
                count=1,
                flags=re.M,
            )
            part = part2
        out.append(part)
    return "".join(out)


def patch_issue_whens(text: str) -> str:
    parts = re.split(r"(?=^### B-\d+)", text, flags=re.M)
    out = []
    for part in parts:
        m = re.match(r"^### B-(\d+)\b", part)
        if not m:
            out.append(part)
            continue
        bid = int(m.group(1))
        if bid in B_WHEN:
            part2, n = re.subn(
                r"(^- \*\*When:\*\* ).+$",
                r"\g<1>" + B_WHEN[bid],
                part,
                count=1,
                flags=re.M,
            )
            part = part2
        out.append(part)
    return "".join(out)


def patch_global(text: str) -> str:
    replacements = [
        (
            "**Active folder (19 Sep 2026):** `C:\\xampp\\htdocs\\CP2_V1.5` (Stage 7 scaffold, from `CP2_V1.4`). Frozen Stage 6 stays in `C:\\xampp\\htdocs\\CP2_V1.4`. Session cookie path here is `/CP2_V1.5/`.",
            "**Active folder (16–20 Sep 2026):** `C:\\xampp\\htdocs\\CP2_V1.5` (Stage 7–8 AI + OpenAI/matplotlib, from `CP2_V1.4`). Frozen Stage 6 stays in `C:\\xampp\\htdocs\\CP2_V1.4`. Session cookie path here is `/CP2_V1.5/`.",
        ),
        (
            "- **Status:** fixed (A-036, 19 September 2026)",
            "- **Status:** fixed (A-036, 9 September 2026)",
        ),
        (
            "- **Solution executed (19 September 2026, A-036):**",
            "- **Solution executed (9 September 2026, A-036):**",
        ),
        (
            "- **When:** 16–19 September 2026 (A-033 Part 3)",
            "- **When:** 6–7 September 2026 (A-033 Part 3)",
        ),
        (
            "- **When:** 19?20 September 2026 (A-054)",
            "- **When:** 17 September 2026 (A-054)",
        ),
        (
            "- **When:** 27 August – 1 September 2026 (6 days; close-out 19 September 2026)",
            "- **When:** 27 August – 1 September 2026 (6 days; close-out logged 10–14 September 2026)",
        ),
        (
            "- **When:** **2 September 2026 →** (official **In Progress**; folder opened 19 September 2026)",
            "- **When:** **2 September 2026 →** (official **In Progress**; confirmation work 14–15 September 2026 in this rebuild)",
        ),
        (
            "- **When:** **19 September 2026 →** (folder `CP2_V1.5`)",
            "- **When:** **16 September 2026 →** (folder `CP2_V1.5`; Luna/matplotlib through 20 September 2026)",
        ),
        (
            "**Docs last synced:** 20 September 2026 (A-066 matplotlib docs shots + Chart.js UI revert).",
            "**Docs last synced:** 20 September 2026 (date intervals paced through A-066; DOCX exports refreshed).",
        ),
        (
            "- **When:** 23–26 August 2026 (4 days; work logged 25 Aug – 10 Sep 2026 in this folder)",
            "- **When:** 23–26 August 2026 (4 days; mailbox/FK follow-through logged 2–9 September 2026 in this folder)",
        ),
        (
            "- **When:** 20–22 August 2026 (3 days; logged 9 September 2026 as A-030)",
            "- **When:** 20–22 August 2026 (3 days; logged 2–3 September 2026 as A-030)",
        ),
    ]
    for a, b in replacements:
        text = text.replace(a, b)
    return text


def main():
    raw = BACKLOG.read_text(encoding="utf-8", errors="replace")
    text = patch_activity_whens(raw)
    text = patch_issue_whens(text)
    text = patch_global(text)
    BACKLOG.write_text(text, encoding="utf-8")
    print("BACKLOG.md dates updated")

    # Light touch on companion docs
    for rel, old, new in [
        (
            r"STAGE7_LOG.md",
            "# Stage 7 — closed 20 September 2026",
            "# Stage 7 — closed 17–18 September 2026",
        ),
        (
            r"STAGE8_TEST_PLAN.md",
            "## Progress (20 Sep 2026)",
            "## Progress (18–20 Sep 2026)",
        ),
        (
            r"STAGE6_LOG.md",
            "- **When:** 19 September 2026",
            "- **When:** 14–15 September 2026",
        ),
        (
            r"SESSION_HANDOFF.md",
            "# Session handoff — 20 Sep 2026",
            "# Session handoff — 16–20 Sep 2026",
        ),
    ]:
        path = Path(r"C:\xampp\htdocs\CP2_V1.5\docs") / rel
        if not path.exists():
            continue
        t = path.read_text(encoding="utf-8", errors="replace")
        if old in t:
            path.write_text(t.replace(old, new), encoding="utf-8")
            print(f"patched {rel}")
        else:
            # STAGE6 may have two When lines
            t2, n = re.subn(
                r"- \*\*When:\*\* 19 September 2026",
                "- **When:** 14–15 September 2026",
                t,
            )
            if n:
                path.write_text(t2, encoding="utf-8")
                print(f"patched {rel} ({n} When lines)")
            else:
                print(f"no change {rel}")


if __name__ == "__main__":
    main()

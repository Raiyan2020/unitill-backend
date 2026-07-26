#!/usr/bin/env python3
"""
Structural diff between two capture sets.

Reports only what a mobile client can observe: status code, key presence,
JSON type, and scalar value. Ordering inside lists is compared positionally
because the mobile app renders lists in server order.
"""
import json
import os
import sys
from collections import OrderedDict

REF, CMP = sys.argv[1], sys.argv[2]
ONLY = sys.argv[3] if len(sys.argv) > 3 else None

# Values that legitimately differ run-to-run and are already normalised in the
# harness; anything reaching here that still differs is a real change.
VOLATILE_LEAF = {"<dynamic>", "<timestamp>"}


def typename(v):
    if v is None:
        return "null"
    if isinstance(v, bool):
        return "bool"
    if isinstance(v, int):
        return "int"
    if isinstance(v, float):
        return "float"
    if isinstance(v, str):
        return "string"
    if isinstance(v, list):
        return "array"
    return "object"


def walk(a, b, path, out):
    ta, tb = typename(a), typename(b)

    if ta != tb:
        # int/float drift on money fields matters, so do not collapse them.
        out.append(("TYPE", path, f"{ta} -> {tb}", f"{a!r} -> {b!r}"))
        return

    if ta == "object":
        for k in a:
            if k not in b:
                out.append(("REMOVED", f"{path}.{k}", typename(a[k]), repr(a[k])[:80]))
        for k in b:
            if k not in a:
                out.append(("ADDED", f"{path}.{k}", typename(b[k]), repr(b[k])[:80]))
        for k in a:
            if k in b:
                walk(a[k], b[k], f"{path}.{k}", out)
        return

    if ta == "array":
        if len(a) != len(b):
            out.append(("LENGTH", path, f"{len(a)} -> {len(b)}", ""))
        for i in range(min(len(a), len(b))):
            walk(a[i], b[i], f"{path}[{i}]", out)
        return

    if a != b and a not in VOLATILE_LEAF:
        out.append(("VALUE", path, "", f"{a!r} -> {b!r}"))


names = sorted(f[:-5] for f in os.listdir(REF) if f.endswith(".json"))
if ONLY:
    names = [n for n in names if ONLY in n]

total = 0
clean = []
for name in names:
    ra = json.load(open(os.path.join(REF, name + ".json")))
    rb_path = os.path.join(CMP, name + ".json")
    if not os.path.exists(rb_path):
        print(f"### {name}\n  MISSING in comparison set\n")
        total += 1
        continue
    rb = json.load(open(rb_path))

    findings = []
    if ra["status"] != rb["status"]:
        findings.append(("STATUS", "", f'{ra["status"]} -> {rb["status"]}', ""))
    walk(ra["body"], rb["body"], "body", findings)

    if not findings:
        clean.append(name)
        continue

    total += len(findings)
    print(f"### {name}   ({len(findings)})")
    seen = set()
    for kind, path, detail, extra in findings:
        key = (kind, path, detail)
        if key in seen:
            continue
        seen.add(key)
        line = f"  {kind:<8} {path:<58} {detail}"
        if extra and kind in ("VALUE", "TYPE"):
            line += f"   {extra[:120]}"
        print(line)
    print()

print(f"\n{'='*70}")
print(f"identical: {len(clean)}/{len(names)}    differing findings: {total}")
if clean:
    print("clean: " + ", ".join(clean))

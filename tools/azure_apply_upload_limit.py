#!/usr/bin/env python3
"""After Azure zip-deploy, raise nginx upload limit via Kudu (Linux PHP 8)."""
from __future__ import annotations

import base64
import json
import os
import ssl
import sys
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET


def main() -> int:
    raw = os.environ.get("PUBLISH_PROFILE", "").strip()
    if not raw:
        print("PUBLISH_PROFILE missing; skip nginx patch")
        return 0
    try:
        root = ET.fromstring(raw)
    except ET.ParseError as exc:
        print("publish profile XML parse failed:", exc)
        return 0
    user = pwd = host = ""
    for node in root.findall(".//publishProfile"):
        method = (node.get("publishMethod") or "").lower()
        if method not in ("msdeploy", "zipdeploy"):
            continue
        user = node.get("userName") or ""
        pwd = node.get("userPWD") or ""
        url = node.get("publishUrl") or ""
        host = url.split(":")[0].strip()
        if user and pwd and host:
            break
    if not host.endswith(".scm.azurewebsites.net"):
        if host and not host.endswith(".azurewebsites.net"):
            host = host
        elif host:
            host = host.replace(".azurewebsites.net", ".scm.azurewebsites.net")
    if not user or not pwd or not host:
        print("could not read Kudu credentials from publish profile")
        return 0
    auth = base64.b64encode(f"{user}:{pwd}".encode("utf-8")).decode("ascii")
    payload = json.dumps(
        {
            "command": "bash /home/web_sierra/wwwroot/startup.sh",
            "dir": "/home/web_sierra/wwwroot",
        }
    ).encode("utf-8")
    req = urllib.request.Request(
        f"https://{host}/api/command",
        data=payload,
        method="POST",
        headers={
            "Authorization": f"Basic {auth}",
            "Content-Type": "application/json",
        },
    )
    ctx = ssl.create_default_context()
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=60) as resp:
            body = resp.read()[:800]
            print("kudu command status", resp.status, body)
    except urllib.error.HTTPError as exc:
        print("kudu command HTTP", exc.code, exc.read()[:400])
        return 0
    except Exception as exc:
        print("kudu command failed:", exc)
        return 0
    return 0


if __name__ == "__main__":
    sys.exit(main())

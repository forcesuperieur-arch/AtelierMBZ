#!/usr/bin/env python3
import hashlib
import hmac
import json
import os
import subprocess
from http.server import BaseHTTPRequestHandler, HTTPServer

HOST = os.getenv("WEBHOOK_HOST", "0.0.0.0")
PORT = int(os.getenv("WEBHOOK_PORT", "9010"))
PATH = os.getenv("WEBHOOK_PATH", "/github-webhook")
SECRET = os.getenv("WEBHOOK_SECRET", "")
BRANCH = os.getenv("BRANCH", "main")
PROJECT_DIR = os.getenv("PROJECT_DIR", "/opt/ateliermbz")
DEPLOY_SCRIPT = os.getenv("DEPLOY_SCRIPT", os.path.join(PROJECT_DIR, "scripts", "deploy-server.sh"))

if not SECRET:
    raise SystemExit("WEBHOOK_SECRET is required")


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != PATH:
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b"Not found")
            return

        length = int(self.headers.get("Content-Length", "0"))
        body = self.rfile.read(length)
        signature = self.headers.get("X-Hub-Signature-256", "")
        event = self.headers.get("X-GitHub-Event", "")

        digest = "sha256=" + hmac.new(SECRET.encode(), body, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(digest, signature):
            self.send_response(401)
            self.end_headers()
            self.wfile.write(b"Invalid signature")
            return

        payload = json.loads(body.decode("utf-8") or "{}")
        ref = payload.get("ref", "")
        expected_ref = f"refs/heads/{BRANCH}"

        if event != "push" or ref != expected_ref:
            self.send_response(202)
            self.end_headers()
            self.wfile.write(f"Ignored event={event} ref={ref}".encode())
            return

        try:
            completed = subprocess.run(
                [DEPLOY_SCRIPT],
                cwd=PROJECT_DIR,
                capture_output=True,
                text=True,
                check=True,
                env=os.environ.copy(),
            )
            self.send_response(200)
            self.end_headers()
            self.wfile.write(completed.stdout.encode())
        except subprocess.CalledProcessError as exc:
            self.send_response(500)
            self.end_headers()
            output = (exc.stdout or "") + "\n" + (exc.stderr or "")
            self.wfile.write(output.encode())

    def log_message(self, format, *args):
        return


if __name__ == "__main__":
    server = HTTPServer((HOST, PORT), Handler)
    print(f"AtelierMBZ webhook listener on http://{HOST}:{PORT}{PATH}")
    server.serve_forever()

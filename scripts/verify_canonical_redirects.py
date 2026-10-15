#!/usr/bin/env python3
"""Exercise the actual .htaccess rules in a disposable, loopback-only Apache.

No application database, PHP configuration, production service or external
request is used. Run from any directory with Python 3 and Apache 2.4 installed.
"""

import argparse
import json
import os
from pathlib import Path
import pwd
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return None


def available_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--debug-log", type=Path, help="Save the disposable server's rewrite trace")
    args = parser.parse_args()
    source = Path(__file__).resolve().parents[1]
    apache = shutil.which("apache2") or shutil.which("httpd")
    if not apache:
        raise SystemExit("Apache 2.4 is required for this integration check.")
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    results = []
    with tempfile.TemporaryDirectory(prefix="salatime-apache-") as temporary:
        fixture = Path(temporary)
        project = fixture / "project"
        public = project / "public"
        standalone = fixture / "standalone"
        for root in (public, standalone):
            root.mkdir(parents=True)
            shutil.copy2(source / "public/.htaccess", root / ".htaccess")
            (root / "assets").mkdir()
            (root / "assets/index.html").write_text("directory asset")
            (root / "asset.txt").write_text("static asset")
            (root / "sample.apk").write_text("fake local APK fixture")
            (root / "index.php").write_text(
                "#!/usr/bin/python3\n"
                "import json, os, sys\n"
                "body = sys.stdin.read(int(os.environ.get('CONTENT_LENGTH') or 0))\n"
                "print('Content-Type: application/json\\r\\n\\r\\n', end='')\n"
                "print(json.dumps({'method': os.environ['REQUEST_METHOD'], "
                "'query': os.environ.get('QUERY_STRING', ''), 'body': body}))\n"
            )
            (root / "index.php").chmod(0o755)
        shutil.copy2(source / ".htaccess", project / ".htaccess")
        modes = {"project": project, "public_with_parent": public, "public": standalone}
        ports = {mode: available_port() for mode in modes}
        module_dir = Path("/usr/lib/apache2/modules")
        modules = ["mpm_prefork", "authz_core", "authz_host", "dir", "mime", "cgi", "rewrite", "headers"]
        config = [
            f'ServerRoot "{fixture}"', f'DefaultRuntimeDir "{fixture}"',
            f'PidFile "{fixture}/httpd.pid"', f'ErrorLog "{fixture}/error.log"',
            "ServerName salatime.net", "LogLevel warn rewrite:trace5" if args.debug_log else "LogLevel warn", "UseCanonicalName Off",
            f"User {pwd.getpwuid(os.getuid()).pw_name}",
        ]
        for module in modules:
            config.append(f'LoadModule {module}_module "{module_dir}/mod_{module}.so"')
        config.extend([
            "TypesConfig /etc/mime.types", "DirectoryIndex index.php index.html",
            f'<Directory "{fixture}">', "Require all granted", "AllowOverride All",
            "Options FollowSymLinks ExecCGI", "AddHandler cgi-script .php", "</Directory>",
        ])
        for mode, root in modes.items():
            config.extend([
                f"Listen 127.0.0.1:{ports[mode]}", f"<VirtualHost 127.0.0.1:{ports[mode]}>",
                "ServerName salatime.net", "ServerAlias www.salatime.net",
                f'DocumentRoot "{root}"', "</VirtualHost>",
            ])
        config_path = fixture / "httpd.conf"
        config_path.write_text("\n".join(config) + "\n")
        checked = subprocess.run([apache, "-t", "-f", str(config_path)], capture_output=True, text=True)
        if checked.returncode:
            raise RuntimeError(checked.stderr)
        # Apache stops its process group on termination; isolate the fixture.
        process = subprocess.Popen([apache, "-f", str(config_path), "-DFOREGROUND"], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, start_new_session=True)
        try:
            for attempt in range(100):
                try:
                    with socket.create_connection(("127.0.0.1", next(iter(ports.values()))), timeout=.1):
                        break
                except OSError:
                    if process.poll() is not None:
                        raise RuntimeError(process.stderr.read().decode() + (fixture / "error.log").read_text())
                    time.sleep(.05)
            else:
                raise RuntimeError("Temporary Apache did not start.")

            def check(mode, path, status, location=None, method="GET", host="salatime.net", proto="https", body=None, expected_body=None, expected_query=None, expected_headers=None, expected_content=None):
                headers = {"Host": host}
                if proto:
                    headers["X-Forwarded-Proto"] = proto
                request = urllib.request.Request(f"http://127.0.0.1:{ports[mode]}{path}", data=body, headers=headers, method=method)
                try:
                    response = opener.open(request, timeout=5)
                except urllib.error.HTTPError as error:
                    response = error
                with response:
                    content = response.read().decode()
                    actual = {"status": response.status, "location": response.headers.get("Location")}
                    passed = actual == {"status": status, "location": location}
                    if expected_body is not None and response.status == 200:
                        passed = passed and json.loads(content)["body"] == expected_body and json.loads(content)["method"] == method
                    if expected_query is not None and response.status == 200:
                        passed = passed and json.loads(content)["query"] == expected_query
                    for name, value in (expected_headers or {}).items():
                        passed = passed and response.headers.get(name) == value
                    if expected_content is not None:
                        passed = passed and content == expected_content
                    results.append({"mode": mode, "method": method, "host": host, "path": path, **actual, "passed": passed})

            query = "lang=fr&search=F%C3%A8s&token=a%26b%2Fc+plus"
            for mode in modes:
                for path in ("/en/prayer-times", "/en/prayer-times/romania", "/api/mobile/auth/config", "/publicity", "/"):
                    check(mode, path, 200)
                for path, target in (
                    ("/en/prayer-times/", "/en/prayer-times"),
                    ("/en/prayer-times/romania/", "/en/prayer-times/romania"),
                    ("/public/en/prayer-times", "/en/prayer-times"),
                    ("/public/en/prayer-times/", "/en/prayer-times"),
                    ("/public/api/verses/", "/api/verses"),
                    ("/public", "/"), ("/public/", "/"),
                ):
                    check(mode, path, 301, "https://salatime.net" + target)
                    check(mode, path + "?" + query, 301, "https://salatime.net" + target + "?" + query)
                check(mode, "/en/prayer-times/", 301, "https://salatime.net/en/prayer-times", method="HEAD")
                check(mode, "/en/prayer-times/", 301, "https://salatime.net/en/prayer-times", host="www.salatime.net", proto=None)
                check(mode, "/en/prayer-times", 301, "https://salatime.net/en/prayer-times", proto=None)
                check(mode, "/en/prayer-times", 301, "https://salatime.net/en/prayer-times", host="www.salatime.net")
                for encoded in ("/a%23b", "/a%20b", "/a%26b"):
                    check(mode, encoded + "?" + query, 301, "https://salatime.net" + encoded + "?" + query, host="www.salatime.net")
                    check(mode, encoded + "?" + query, 301, "https://salatime.net" + encoded + "?" + query, proto=None)
                check(mode, "/en/prayer-times/?" + query, 301, "https://salatime.net/en/prayer-times?" + query, host="salatime.net:443")
                check(mode, "/public/fr/horaires-priere/F%C3%A8s/", 301, "https://salatime.net/fr/horaires-priere/F%C3%A8s")
                check(mode, "/en/prayer-times?" + query, 200, expected_query=query)
                check(mode, "/asset.txt", 200, expected_content="static asset")
                check(mode, "/public/asset.txt", 301, "https://salatime.net/asset.txt")
                check(mode, "/assets/", 200, expected_content="directory asset")
                check(mode, "/assets/?" + query, 200, expected_content="directory asset")
                check(mode, "/assets", 301, "https://salatime.net/assets/")
                check(mode, "/assets?" + query, 301, "https://salatime.net/assets/?" + query)
                check(mode, "/sample.apk", 200, expected_content="fake local APK fixture", expected_headers={"Cache-Control": "no-cache, no-store, must-revalidate", "Pragma": "no-cache", "Expires": "0"})
                check(mode, "/api/mobile/auth/login/", 200, method="POST", body=b'{"fixture":true}', expected_body='{"fixture":true}')
                check(mode, "/api/mobile/auth/login/?" + query, 200, method="POST", body=b"fixture=a%26b", expected_body="fixture=a%26b", expected_query=query)
                check(mode, "/public/api/mobile/auth/login/?" + query, 200, method="POST", body=b"fixture=a%26b", expected_body="fixture=a%26b", expected_query=query)
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
            if args.debug_log:
                args.debug_log.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(fixture / "error.log", args.debug_log)
    summary = {"cases": len(results), "passed": sum(row["passed"] for row in results), "results": results}
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(summary, indent=2) + "\n")
    for row in results:
        if not row["passed"]:
            print(json.dumps(row))
    print(f"Canonical redirects: {summary['passed']}/{summary['cases']} passed.")
    raise SystemExit(0 if summary["passed"] == summary["cases"] else 1)


if __name__ == "__main__":
    main()

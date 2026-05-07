#!/usr/bin/env python3
"""Deploy: pull on helpdeskvm, fix ownership, run migrations."""
import os, sys

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

import paramiko

HOST = "10.200.1.104"
USER = "wpladmin"
PW   = os.environ["WPLADMIN_PW"]
SITE = "/var/www/freshwpl"

CMDS = [
    f"sudo -S -p '' -u www-data git -C {SITE} pull --ff-only",
    f"sudo -S -p '' chown -R www-data:www-data {SITE}",
    f"sudo -S -p '' -u www-data php {SITE}/database/migrate.php",
]

c = paramiko.SSHClient()
c.load_system_host_keys()
c.set_missing_host_key_policy(paramiko.RejectPolicy())
c.connect(HOST, username=USER, password=PW, look_for_keys=False, allow_agent=False)

for cmd in CMDS:
    print(f"\n$ {cmd.replace(PW, '***')}")
    stdin, stdout, stderr = c.exec_command(cmd, timeout=120)
    stdin.write(PW + "\n"); stdin.flush()
    stdin.channel.shutdown_write()
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    rc  = stdout.channel.recv_exit_status()
    if out: print(out, end="")
    if err: print(f"[stderr] {err}", end="")
    print(f"[exit {rc}]")
    if rc != 0:
        c.close()
        sys.exit(rc)

c.close()
print("\nDeploy OK.")

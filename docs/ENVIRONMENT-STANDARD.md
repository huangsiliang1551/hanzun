# Environment Standard

Last confirmed: 2026-06-30

## Environment separation

1. Production server
   - IP: 43.108.20.215
   - User: root
   - Rule: Do not modify, overwrite, deploy to, or synchronize anything to production unless the user gives explicit authorization first.

2. Local workspace
   - Path: E:\codex\企业网站
   - Rule: Do not modify workspace contents by default.
   - Rule: Do not use workspace content as the source of truth for deployment decisions unless the user explicitly asks.

3. Local test server
   - IP: 192.168.100.200
   - Rule: All code changes, troubleshooting, regeneration, and validation should be performed here by default.
   - Rule: If a server-side fix is needed, change the test server first, not production.

## Operating rules

- Never overwrite production content without explicit user permission.
- Never overwrite local workspace content unless the user explicitly asks.
- Default execution target for code changes is the local test server: 192.168.100.200.
- When environment scope is unclear, stop and confirm before touching any server.
- Treat the test server as the active execution environment for Hanzun site debugging and generation work.

## Communication rule

Before any future server operation, explicitly state which environment is being touched:
- Production: 43.108.20.215
- Workspace: E:\codex\企业网站
- Test server: 192.168.100.200
EOF
ls -l /home/hsl1987/hanzun-site-test/docs/ENVIRONMENT-STANDARD.md

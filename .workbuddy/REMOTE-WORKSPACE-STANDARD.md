# Hanzun Remote Workspace Standard

Last updated: 2026-07-01

## Environments
- Production server: `43.108.20.215`
- Local reference workspace: `E:\codex\????`
- Test server and????????: `192.168.100.200:/home/hsl1987/hanzun-site-test`

## Mandatory rules
- All code changes, template fixes, static build fixes, Docker adjustments, and runtime troubleshooting must be made on the test server workspace: `/home/hsl1987/hanzun-site-test`.
- The local workspace `E:\codex\????` is reference-only and must not be used as the deployment or modification source.
- The production server `43.108.20.215` must not be modified, overwritten, or synchronized unless explicit authorization is given.
- Validation URL for the test environment: `http://192.168.100.200:18081`

## Docker mount standard
- `app`: `/home/hsl1987/hanzun-site-test` -> `/var/www/html` (read-write)
- `web`: `/home/hsl1987/hanzun-site-test` -> `/var/www/html` (read-only)
- `uploads`: `/home/hsl1987/hanzun-site-test/uploads` -> `/var/www/html/backend/public/uploads`

## SSH standard
- Preferred SSH alias on the local machine: `hanzun-test`
- Login user: `hsl1987`
- Working directory after login: `/home/hsl1987/hanzun-site-test`

## Operational expectation
- Treat the test server workspace as the single source of change for development and debugging.
- Verify generated pages and Docker behavior on the test server before discussing any production sync.

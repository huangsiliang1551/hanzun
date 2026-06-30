# Hanzun Branch Strategy

Last updated: 2026-07-01

## Official branches
- `main`: repository mainline and reference branch. Do not use it for day-to-day edits on the test server workspace.
- `test`: the only daily collaboration branch for the test server workspace `/home/hsl1987/hanzun-site-test`.

## Test server rule
- The checked out branch on `192.168.100.200:/home/hsl1987/hanzun-site-test` must remain `test` during normal work.
- All code edits, template adjustments, static build fixes, Docker changes, and debugging work on the test server are committed to `test`.

## Production safety rule
- Production server changes are not made from the live test-server working branch directly.
- When a release is approved, create a dedicated release branch from `test` such as `release/YYYYMMDD-description`, verify it, and only then perform any authorized production sync.

## Local workspace rule
- Local path `E:\codex\????` is reference-only unless explicitly chosen for repo maintenance.
- The remote test-server workspace is the operational source of truth for test and debug work.

## Legacy temporary branches
The following branches were created during the migration/bootstrap phase and should not be used for ongoing collaboration:
- `test-server-baseline`
- `test-server-from-server`

Keep them only as migration history unless explicitly cleaned up later.

## Daily workflow
1. SSH to `hanzun-test`
2. `cd /home/hsl1987/hanzun-site-test`
3. Confirm branch with `git branch --show-current` and ensure it is `test`
4. Work, test, commit
5. Push with `git push`

## Important note
- Do not switch the live test-server working tree to `main` casually, because Docker mounts this directory directly and branch switches change the running files.

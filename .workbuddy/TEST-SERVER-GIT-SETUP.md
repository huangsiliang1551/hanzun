# Hanzun Test Server Git Setup

Last updated: 2026-07-01

## Repository
- Workspace: `/home/hsl1987/hanzun-site-test`
- Local git branch: `main`
- Snapshot commit: `dbe3a438b2e7afca400746a5a94a93806980446e`

## Git identity
- `user.name=Hanzun Test Server`
- `user.email=hanzun-test-server@local`

## GitHub remote
- Intended origin URL: `git@github.com:huangsiliang1551/hanzun.git`

## SSH key for GitHub
Public key to add to GitHub account or repo deploy keys:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAINriILdWynFXBLuRk2fi6+0fcTcUKzNsdKg+4eOxsyfE hanzun-test-server-github
```

Private key path on test server:
- `~/.ssh/github_hanzun_ed25519`

SSH config path on test server:
- `~/.ssh/config`

## Required manual step
Add the public key above to GitHub before using `git push` or authenticated `git fetch` over SSH from the test server.

## Recommended workflow
- Work in `/home/hsl1987/hanzun-site-test`
- Commit on the test server
- Push from the test server after the GitHub key is authorized
- Keep production changes separate and explicit

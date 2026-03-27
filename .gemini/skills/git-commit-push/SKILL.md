---
name: git-commit-push
description: Use this skill whenever the user asks to commit, push, save to git, or publish changes to GitHub. It enforces conventional commits, branch hygiene, pre-commit checks, and safe push practices for the wp-admin-lock / Lebo Secu project.
---

# Git Commit & Push — Best Practices Skill

This skill guides the agent through a safe, conventional, and well-documented git commit and push workflow.

## Workflow

### 1. Pre-flight Checks

Before committing, always run:

```bash
# Show current branch and status
git status
git branch --show-current
```

- **Never commit directly to `main` or `master`** unless explicitly told to do so.
- If on `main`/`master`, create or switch to a feature/fix branch first.
- Check for unstaged secrets, `.env` files, or sensitive keys before staging.

### 2. Determine What to Commit

```bash
git diff --stat          # Overview of changed files
git diff                 # Full diff of unstaged changes
git diff --cached        # Review already-staged changes
```

> Ask the user what scope to commit if changes span multiple unrelated features. Each commit should be atomic and focused.

### 3. Stage Files Appropriately

```bash
# Stage specific files (preferred — avoids accidental staging)
git add path/to/file1 path/to/file2

# Stage all tracked changes (only when intentional)
git add -A

# Interactive staging for partial file commits
git add -p
```

**Never stage:**
- `.env`, `.env.local`, private key files (`*.key`, `*.pem`)
- `vendor/` directory (should be in `.gitignore`)
- WordPress `wp-config.php` with real credentials
- OS files: `.DS_Store`, `Thumbs.db`

### 4. Write a Conventional Commit Message

Follow the **Conventional Commits** specification: `https://www.conventionalcommits.org`

Format:
```
<type>(<scope>): <short summary>

[optional body]

[optional footer: BREAKING CHANGE, closes #issue]
```

**Types:**
| Type | When to use |
|------|-------------|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `refactor` | Code restructuring without behavior change |
| `docs` | Documentation only |
| `style` | Formatting, whitespace (no logic change) |
| `test` | Adding or fixing tests |
| `chore` | Build, config, dependency updates |
| `security` | Security hardening, patching vulnerabilities |
| `perf` | Performance improvement |

**Scopes** for this project:
`admin-url`, `hide-version`, `htaccess`, `rest-api`, `login-protection`, `user-enum`, `security-headers`, `disable-features`, `audit-log`, `import-export`, `scaffold`, `docker`, `deps`

**Examples:**
```
feat(login-protection): add IP whitelist to bypass lockout
fix(admin-url): correct redirect loop on custom slug collision
security(rest-api): enforce nonce verification on all mutation endpoints
docs(audit-log): add retention policy documentation
chore(deps): update phpunit to 10.x
```

Rules:
- Summary line: max **72 characters**, imperative mood ("add", not "added")
- No period at end of summary
- Body wrapped at 80 chars if needed
- Reference issues with `Closes #N` or `Refs #N`

### 5. Commit

```bash
git commit -m "<type>(<scope>): <summary>"

# For multi-line messages:
git commit -m "<type>(<scope>): <summary>" -m "<body paragraph>"
```

### 6. Pre-push Checks

Before pushing, verify:

```bash
# Confirm remote is correct
git remote -v

# Preview what will be pushed
git log origin/$(git branch --show-current)..HEAD --oneline 2>/dev/null || git log HEAD --oneline -5

# Check for merge conflicts or rebase needs
git fetch origin
git status
```

If the remote branch has diverged:
```bash
# Rebase (preferred for feature branches — keeps history clean)
git pull --rebase origin <branch>

# Merge (acceptable for long-lived branches)
git pull origin <branch>
```

### 7. Push

```bash
# Push current branch (set upstream on first push)
git push -u origin $(git branch --show-current)

# Subsequent pushes
git push
```

**Never use `--force` unless explicitly asked** and only on personal feature branches — never on `main`.

If force-push is truly needed (e.g., after interactive rebase):
```bash
# Safer alternative to --force
git push --force-with-lease
```

### 8. Post-push Summary

After a successful push, report back to the user:
1. The branch pushed to
2. The commit hash (short: `git rev-parse --short HEAD`)
3. The commit message
4. Remote URL (`git remote get-url origin`)
5. Any relevant GitHub PR link if applicable

## Branch Naming Conventions

| Pattern | Purpose |
|---------|---------|
| `feat/<description>` | New feature |
| `fix/<description>` | Bug fix |
| `security/<description>` | Security-related changes |
| `docs/<description>` | Documentation |
| `chore/<description>` | Maintenance |
| `release/v<semver>` | Release preparation |

Use lowercase, hyphens only, max 50 chars.

## Safety Rules (Non-Negotiable)

1. **Never commit credentials** — check with `git diff HEAD` before committing
2. **Never force-push to `main`** — use `--force-with-lease` on feature branches only
3. **Always fetch before pushing** if others may have pushed to the same branch
4. **Atomic commits** — one logical change per commit
5. **Verify `.gitignore`** covers `vendor/`, `.env`, `*.key`, `wp-config.php` with real secrets

## .gitignore Essentials for This Project

Ensure these are ignored (verify `.gitignore` exists and contains):

```
# WordPress
wp-config.php
wp-content/uploads/
wp-content/upgrade/
wp-content/cache/

# Dependencies
vendor/
node_modules/

# Environment
.env
.env.local
*.key
*.pem

# OS
.DS_Store
Thumbs.db

# IDE
.idea/
.vscode/
*.swp
```

#!/usr/bin/env bash
# safe-push.sh — Pre-push safety check helper for Lebo Secu / wp-admin-lock
# Usage: bash .gemini/skills/git-commit-push/scripts/safe-push.sh

set -euo pipefail

BRANCH=$(git branch --show-current)
REMOTE="origin"

echo "🔍 Branch: $BRANCH"
echo "🔍 Remote: $(git remote get-url $REMOTE)"

# Block direct pushes to main/master
if [[ "$BRANCH" == "main" || "$BRANCH" == "master" ]]; then
  echo "⚠️  WARNING: You are on '$BRANCH'. Proceed only if this is intentional."
  read -r -p "Continue pushing to $BRANCH? [y/N] " confirm
  if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "❌ Push aborted."
    exit 1
  fi
fi

# Check for sensitive patterns in staged files
echo "🔒 Scanning for potential secrets..."
if git diff --cached | grep -qiE '(password|secret|api_key|private_key|DB_PASSWORD|AUTH_KEY)\s*=\s*.{4,}'; then
  echo "❌ Potential secret detected in staged changes! Aborting."
  echo "   Review with: git diff --cached"
  exit 1
fi

# Check for .env files being staged
if git diff --cached --name-only | grep -qE '^\.env'; then
  echo "❌ .env file is staged! Remove it with: git restore --staged .env"
  exit 1
fi

# Fetch and check for divergence
echo "🔄 Fetching remote..."
git fetch "$REMOTE" --quiet

LOCAL=$(git rev-parse HEAD)
REMOTE_HEAD=$(git rev-parse "$REMOTE/$BRANCH" 2>/dev/null || echo "")

if [[ -n "$REMOTE_HEAD" && "$LOCAL" != "$REMOTE_HEAD" ]]; then
  BEHIND=$(git rev-list --count HEAD.."$REMOTE/$BRANCH")
  if [[ "$BEHIND" -gt 0 ]]; then
    echo "⚠️  Your branch is $BEHIND commit(s) behind $REMOTE/$BRANCH."
    echo "   Run: git pull --rebase $REMOTE $BRANCH"
    exit 1
  fi
fi

# Show what will be pushed
echo ""
echo "📦 Commits to push:"
git log "$REMOTE/$BRANCH"..HEAD --oneline 2>/dev/null || git log HEAD --oneline -5

echo ""
echo "✅ All checks passed. Pushing..."
git push -u "$REMOTE" "$BRANCH"

echo ""
echo "🚀 Pushed successfully!"
echo "   Commit: $(git rev-parse --short HEAD)"
echo "   Branch: $BRANCH → $REMOTE"

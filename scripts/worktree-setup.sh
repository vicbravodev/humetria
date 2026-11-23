#!/usr/bin/env bash
# Makes a fresh git worktree ready to test: .env from the main checkout (PostHog neutral, see worktree-env.sh), PHP and JS dependencies, built assets.
# The test database needs no step: tests/bootstrap.php creates this worktree's own on first run.
#
# Usage: composer worktree:setup
set -euo pipefail

main=$(git worktree list --porcelain | awk '/^worktree /{print $2; exit}')

[ -f .env ] || scripts/worktree-env.sh "$main/.env" .env
composer install --no-interaction
npm ci
npm run build
echo "Ready. Tests run against their own database; 'composer worktree:prune' lists the ones left by removed worktrees."

#!/bin/bash
# Push the app to cPanel. Same as `syncvtrs .` (which now reads .syncignore),
# just without the confirmation prompts. Usage: ./deploy.sh [--dry-run]
cd "$(dirname "$0")" || exit 1
exec ~/bin/sshlvtrs_sync . -y "$@"

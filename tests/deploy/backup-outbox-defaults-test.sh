#!/bin/bash
# The client defaults file must exist before it is restricted.
# chmod on a path that does not exist fails with set -e.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
script="$root/deploy/backup-outbox.sh"

if grep -F 'chmod 600 "$defaults"' "$script" >/dev/null; then
  echo "backup-outbox.sh still runs chmod on \$defaults before the file exists." >&2
  exit 1
fi

if ! grep -F 'install -m 600 /dev/null "$defaults"' "$script" >/dev/null; then
  echo "backup-outbox.sh must create the client defaults file with install -m 600." >&2
  exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
missing="$work/missing.cnf"
set +e
chmod 600 "$missing" 2>/dev/null
status=$?
set -e
if [[ "$status" -eq 0 ]]; then
  echo "chmod on a missing file unexpectedly succeeded; the regression check is invalid." >&2
  exit 1
fi

defaults="$work/client.cnf"
install -m 600 /dev/null "$defaults"
umask 022
printf '%s\n' '[client]' 'password=example' > "$defaults"
mode="$(stat -c %a "$defaults")"
if [[ "$mode" != "600" ]]; then
  echo "expected mode 600 after writing the defaults file, got $mode." >&2
  exit 1
fi

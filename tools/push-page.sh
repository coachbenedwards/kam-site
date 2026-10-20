#!/usr/bin/env bash
# Lay a page out on the WordPress from its build/<slug>.json.
#   tools/push-page.sh bulwell            (or several slugs)
# Credentials: a WordPress application password, "user:pass", in
# ~/Documents/Claude outputs/kam-review/kam-wp-app-password.txt (not in git).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE="${KAM_SITE:-https://kamservicing.eastwoodfc.com}"
AUTH="$(cat "$HOME/Documents/Claude outputs/kam-review/kam-wp-app-password.txt" 2>/dev/null || cat "$HOME/mnt/Documents/Claude outputs/kam-review/kam-wp-app-password.txt")"
for slug in "$@"; do
  f="$ROOT/build/$slug.json"
  [ -f "$f" ] || { echo "no $f"; exit 1; }
  code=$(curl -sS -o /tmp/kam-push.out -w '%{http_code}' -u "$AUTH" -H 'Content-Type: application/json' \
      -A 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' \
      --data-binary @"$f" "$SITE/wp-json/kam/v1/page-blocks")
  echo "$slug -> $code $(head -c 300 /tmp/kam-push.out)"
done

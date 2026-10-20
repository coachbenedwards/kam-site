#!/usr/bin/env bash
# Lay a page out on the WordPress from its build/<slug>.json.
#   tools/push-page.sh bulwell            (or several slugs)
# Credentials live outside the repo, in ~/Documents/Claude outputs/kam-review/:
#   kam-wp-app-password.txt   "user:pass" (a WordPress application password)
#   kam-maps-key.txt          KAM's Google Maps key, substituted for @MAPS_KEY@
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE="${KAM_SITE:-https://kamservicing.eastwoodfc.com}"
SECRETS="$HOME/Documents/Claude outputs/kam-review"; [ -d "$SECRETS" ] || SECRETS="$HOME/mnt/Documents/Claude outputs/kam-review"
AUTH="$(cat "$SECRETS/kam-wp-app-password.txt")"
MAPS_KEY="$(cat "$SECRETS/kam-maps-key.txt" 2>/dev/null || true)"
for slug in "$@"; do
  f="$ROOT/build/$slug.json"
  [ -f "$f" ] || { echo "no $f"; exit 1; }
  code=$(sed "s/@MAPS_KEY@/$MAPS_KEY/g" "$f" | curl -sS -o /tmp/kam-push.out -w '%{http_code}' -u "$AUTH" -H 'Content-Type: application/json' \
      -A 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' \
      --data-binary @- "$SITE/wp-json/kam/v1/page-blocks")
  echo "$slug -> $code $(head -c 300 /tmp/kam-push.out)"
done

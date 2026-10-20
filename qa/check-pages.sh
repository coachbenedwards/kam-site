#!/usr/bin/env bash
# Like check-branches.sh, for the other pages. Usage: qa/check-pages.sh services terms ...
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PLAYWRIGHT_BROWSERS_PATH="$HOME/pw-browsers" LD_LIBRARY_PATH="$HOME/libs/ex/usr/lib/aarch64-linux-gnu" NODE_PATH="$HOME/node_modules"
declare -A OLD=( [home]=home [services]=all-kam-services-php [careers]=careers-at-kam-servicing-php [payment-assist]=payment-assist-php [abs-repairs]=abs-repairs-php [exhausts]=car-exhaust-prices-php [airbag-faults]=airbag-fault-repair-php [brakes]=car-brakes-php [diagnostics]=car-diagnostics-php [clutch-replacement]=clutch-replacement-php [privacy-policy]=gdpr-privacy-policy-php [terms]=terms-conditions-php [contact]=kam-servicing-php )
for slug in "$@"; do
  url="/$slug/"; [ "$slug" = home ] && url="/"
  out=$(node "$ROOT/qa/baseline.mjs" --origin https://kamservicing.eastwoodfc.com --url "$url" --before "${OLD[$slug]}" --password KamPreview2026 --diff --all 2>&1)
  total=$(echo "$out" | grep -c 'px  ')
  rest=$(echo "$out" | grep 'px  ' | grep -v 'link moved\|Renu\|Orthopedic\|CBSE\|words changed\|div "" h \|section "" h \|footer ""\|© KAM\|Privacy Policy\|All Services\|Terms & Conditions\|gone: h5\|gone: h6\|new: a ')
  n=$(echo "$rest" | grep -c 'px  ')
  echo "$slug: $total differences, $n to look at"
  [ "$n" -gt 0 ] && echo "$rest" | grep 1512px | head -${LINES_PER:-14}
done

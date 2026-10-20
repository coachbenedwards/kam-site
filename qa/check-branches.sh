#!/usr/bin/env bash
# Diff every branch page on the preview site against its live baseline, and
# print one line per page: the count of differences other than the ones we
# chose (spam links removed, reCAPTCHA box not carried over, links re-pointed).
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PLAYWRIGHT_BROWSERS_PATH="$HOME/pw-browsers" LD_LIBRARY_PATH="$HOME/libs/ex/usr/lib/aarch64-linux-gnu" NODE_PATH="$HOME/node_modules"
declare -A OLD=( [nottingham]=kam-servicing-nottingham-php [bulwell]=kam-servicing-bulwell-php [nuthall]=kam-servicing-nuthall-php [colwick]=kam-servicing-colwick-php [hucknall]=kam-servicing-hucknall-php [beeston]=kam-servicing-beeston-php [sutton-in-ashfield]=kam-servicing-sutton-php [stapleford]=kam-servicing-stapleford-php [derby]=kam-servicing-derby-php [derby-ascot-drive]=kam-servicing-ascot-php [derby-sinfin]=kam-servicing-sinfin-php [long-eaton-derby-road]=kam-servicing-longeaton-php [long-eaton-tamworth-road]=kam-servicing-sawley-php [heanor]=kam-servicing-heanor-php [belper]=kam-servicing-belper-php [ambergate]=kam-servicing-ambergate-php )
for slug in "${@:-${!OLD[@]}}"; do
  out=$(node "$ROOT/qa/baseline.mjs" --origin https://kamservicing.eastwoodfc.com --url "/$slug/" --before "${OLD[$slug]}" --password KamPreview2026 --diff --all 2>&1)
  total=$(echo "$out" | grep -c 'px  ')
  rest=$(echo "$out" | grep 'px  ' | grep -v 'link moved\|Renu\|Orthopedic\|CBSE\|words changed\|div "" h \|section "" h \|Send message\|footer ""\|© KAM\|Privacy Policy\|All Services\|Terms & Conditions')
  # A y-only move of 40-100px below the form is the reCAPTCHA box we left out.
  rest=$(echo "$rest" | awk '{ if (match($0, / y ([0-9]+) -> ([0-9]+)$/, m)) { d = m[1]-m[2]; if (d >= 40 && d <= 100) next } print }')
  n=$(echo "$rest" | grep -c 'px  ')
  echo "$slug: $total differences, $n outside the expected set"
  [ "$n" -gt 0 ] && echo "$rest" | head -12
done

#!/usr/bin/env bash

set -euo pipefail

: "${REPORT_API_BASE_URL:?REPORT_API_BASE_URL is required}"
: "${REPORT_API_TOKEN:?REPORT_API_TOKEN is required}"
: "${WEEKLY_REPORT_SLACK_WEBHOOK_URL:?WEEKLY_REPORT_SLACK_WEBHOOK_URL is required}"

endpoint="${REPORT_API_BASE_URL%/}/api/v1/reports/hours"

# 前週の月曜〜日曜を対象期間にする。
today="$(date +%F)"
dow="$(date +%u)"
this_week_monday="$(date -d "${today} -$((dow - 1)) days" +%F)"
date_from="$(date -d "${this_week_monday} -7 days" +%F)"
date_to="$(date -d "${this_week_monday} -1 days" +%F)"

response="$(curl --fail --silent --show-error \
  -H "Authorization: Bearer ${REPORT_API_TOKEN}" \
  "${endpoint}?date_from=${date_from}&date_to=${date_to}")"

if ! jq -e . >/dev/null 2>&1 <<<"${response}"; then
  echo "API response is not valid JSON." >&2
  exit 1
fi

summary_lines="$(jq -r '
  def fmt:
    (tostring | gsub("\\.?0+$";""));
  if (.summary | length) == 0 then
    "(no data)"
  else
    .summary[]
    | "\(.client_name): \((.hours | fmt))h"
  end
' <<<"${response}")"

total_hours="$(jq -r '
  def fmt:
    (tostring | gsub("\\.?0+$";""));
  (.total_hours | fmt)
' <<<"${response}")"

message=$'Summary ('"${date_from}"'-'"${date_to}"$')\n```\n'"${summary_lines}"$'\n合計: '"${total_hours}"$'h\n```'

payload="$(jq -n --arg text "${message}" '{text: $text}')"

curl --fail --silent --show-error \
  -X POST \
  -H 'Content-type: application/json' \
  --data "${payload}" \
  "${WEEKLY_REPORT_SLACK_WEBHOOK_URL}" >/dev/null

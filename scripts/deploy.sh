#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

if [[ $# -ne 3 ]]; then
  echo "Usage: $0 <tar_path> <project> <public_dir>" >&2
  exit 1
fi

readonly TAR_PATH_INPUT="$1"
readonly PROJECT="$2"
readonly PUBLIC_DIR="$3"
readonly RELEASE_BASE="${HOME}/release"
readonly WORK_DIR="${RELEASE_BASE}/link/${PROJECT}"
readonly DEPLOY_SEQ="$(date +'%Y%m%d-%H%M%S')"
readonly WWW_DIR="${WORK_DIR}/${DEPLOY_SEQ}"
readonly CURRENT_PATH="${WORK_DIR}/current"
readonly TARGET_PATH="${PUBLIC_DIR%/}/${PROJECT}"
readonly LOG_DIR="${RELEASE_BASE}/logs/${PROJECT}"
readonly ENV_FILE_PATH="${RELEASE_BASE}/environment/${PROJECT}/.env"
readonly KEEP_RELEASES=3

if [[ "${TAR_PATH_INPUT}" = /* ]]; then
  readonly TAR_PATH="${TAR_PATH_INPUT}"
else
  readonly TAR_PATH="${HOME}/${TAR_PATH_INPUT}"
fi

log() {
  echo "[deploy] $*"
}

require_file() {
  local path="$1"
  if [[ ! -f "${path}" ]]; then
    echo "Required file not found: ${path}" >&2
    exit 1
  fi
}

cleanup_old_releases() {
  local work_dir="$1"
  local keep="$2"
  mapfile -t releases < <(
    find "${work_dir}" -mindepth 1 -maxdepth 1 -type d \
      -regextype posix-extended -regex ".*/[0-9]{8}-[0-9]{6}" -printf '%P\n' | sort -r
  )
  if [[ "${#releases[@]}" -le "${keep}" ]]; then
    return
  fi

  for release in "${releases[@]:$keep}"; do
    rm -rf "${work_dir}/${release}"
    log "${release} を削除しました。"
  done
}

# ローカルに配置した PHP のエイリアスに設定されたバージョンを利用する
export PATH="${HOME}/.php:${PATH}"

require_file "${TAR_PATH}"
require_file "${ENV_FILE_PATH}"

mkdir -p "${WWW_DIR}"

cleanup_tar() {
  log "利用済みアーカイブ削除: ${TAR_PATH}"
  rm -f "${TAR_PATH}"
}
trap cleanup_tar EXIT

log "アーカイブ展開: ${TAR_PATH}"
tar xzf "${TAR_PATH}" -C "${WWW_DIR}"

log "ログディレクトリ設定: ${LOG_DIR}"
mkdir -p "${LOG_DIR}"
mkdir -p "${WWW_DIR}/var"
rm -rf "${WWW_DIR}/var/log" "${WWW_DIR}/logs"
ln -sfn "${LOG_DIR}" "${WWW_DIR}/var/log"

log ".env シンボリックリンク設定"
ln -sfn "${ENV_FILE_PATH}" "${WWW_DIR}/.env"

log "Composer install"
cd "${WWW_DIR}"
composer install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --no-progress \
  --optimize-autoloader \
  --classmap-authoritative

log "マイグレーション実行"
php bin/doctrine migrations:migrate -n

log "キャッシュ初期化"
mkdir -p "${WWW_DIR}/var/cache" "${WWW_DIR}/var/doctrine"
rm -rf "${WWW_DIR}/var/cache/"* "${WWW_DIR}/var/doctrine/"*

LINK_PATH="${WWW_DIR}/public"
PREVIOUS_PATH=""
SWITCHED=0

if [[ ! -L "${TARGET_PATH}" ]]; then
  echo "TARGET_PATH がシンボリックリンクではありません: ${TARGET_PATH}" >&2
  exit 1
fi

if [[ ! -L "${CURRENT_PATH}" ]]; then
  current_target="$(readlink -f "${TARGET_PATH}")"
  if [[ -z "${current_target}" ]]; then
    echo "CURRENT_PATH の初期化に失敗しました: ${TARGET_PATH}" >&2
    exit 1
  fi
  ln -sfn "${current_target}" "${CURRENT_PATH}"
fi

target_dest="$(readlink -f "${TARGET_PATH}")"
expected_dest="$(readlink -f "${CURRENT_PATH}")"
if [[ "${target_dest}" != "${expected_dest}" ]]; then
  ln -sfn "${CURRENT_PATH}" "${TARGET_PATH}"
  target_dest="$(readlink -f "${TARGET_PATH}")"
fi

if [[ "${target_dest}" != "${expected_dest}" ]]; then
  echo "TARGET_PATH のリンク先が想定外です: ${TARGET_PATH} -> ${target_dest}" >&2
  echo "想定: ${TARGET_PATH} -> ${CURRENT_PATH} (${expected_dest})" >&2
  exit 1
fi

if [[ -L "${CURRENT_PATH}" ]]; then
  PREVIOUS_PATH="$(readlink "${CURRENT_PATH}" || true)"
fi

rollback() {
  if [[ "${SWITCHED}" -eq 1 && -n "${PREVIOUS_PATH}" ]]; then
    ln -sfn "${PREVIOUS_PATH}" "${CURRENT_PATH}"
    log "デプロイ失敗のためリンクをロールバックしました。${CURRENT_PATH} -> ${PREVIOUS_PATH}"
  fi
}
trap rollback ERR

log "公開リンク切り替え"
ln -sfn "${LINK_PATH}" "${CURRENT_PATH}"
SWITCHED=1
log "リンクを生成しました。${CURRENT_PATH} -> ${LINK_PATH}"
trap - ERR

ln -sfn "${CURRENT_PATH}" "${TARGET_PATH}"

log "古いリリース削除"
cleanup_old_releases "${WORK_DIR}" "${KEEP_RELEASES}"

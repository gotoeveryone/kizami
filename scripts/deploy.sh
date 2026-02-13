#!/bin/bash

set -Eeuo pipefail

if [[ $# -ne 2 ]]; then
  echo "Usage: $0 <tar_name> <project>" >&2
  exit 1
fi

readonly TAR_NAME="$1"
readonly PROJECT="$2"
readonly HOME_TAR_PATH="${HOME}/${TAR_NAME}"
readonly RELEASE_BASE="${HOME}/release"
readonly WORK_DIR="${RELEASE_BASE}/link/${PROJECT}"
readonly DEPLOY_SEQ="$(date +'%Y%m%d-%H%M%S')"
readonly WWW_DIR="${WORK_DIR}/${DEPLOY_SEQ}"
readonly LOG_DIR="${RELEASE_BASE}/logs/${PROJECT}"
readonly ENV_FILE_PATH="${RELEASE_BASE}/environment/${PROJECT}/.env"
readonly TARGET_PATH="${HOME}/k2ss.info/public_html/${PROJECT}"
readonly KEEP_RELEASES=3

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
  mapfile -t releases < <(find "${work_dir}" -mindepth 1 -maxdepth 1 -type d -printf '%P\n' | sort -r)
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

require_file "${HOME_TAR_PATH}"
require_file "${ENV_FILE_PATH}"

mkdir -p "${WWW_DIR}"

log "アーカイブ展開: ${HOME_TAR_PATH}"
tar xzf "${HOME_TAR_PATH}" -C "${WWW_DIR}"

log "ログディレクトリ設定: ${LOG_DIR}"
mkdir -p "${LOG_DIR}"
mkdir -p "${WWW_DIR}/var"
rm -rf "${WWW_DIR}/var/log" "${WWW_DIR}/logs"
ln -sfn "${LOG_DIR}" "${WWW_DIR}/var/log"

log ".env シンボリックリンク設定"
ln -sfn "${ENV_FILE_PATH}" "${WWW_DIR}/.env"

log "Composer install"
cd "${WWW_DIR}"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "マイグレーション実行"
php bin/doctrine migrations:migrate -n

log "キャッシュ初期化"
mkdir -p "${WWW_DIR}/var/cache" "${WWW_DIR}/var/doctrine"
rm -rf "${WWW_DIR}/var/cache/"* "${WWW_DIR}/var/doctrine/"*

log "公開リンク切り替え"
ln -snf "${WWW_DIR}/public" "${TARGET_PATH}"
log "リンクを生成しました。${WWW_DIR}/public -> ${TARGET_PATH}"

log "古いリリース削除"
cleanup_old_releases "${WORK_DIR}" "${KEEP_RELEASES}"

log "利用済みアーカイブ削除: ${HOME_TAR_PATH}"
rm -f "${HOME_TAR_PATH}"

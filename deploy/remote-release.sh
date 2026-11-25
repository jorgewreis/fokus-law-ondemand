#!/bin/bash
# Publica um release imutável do Fokus Law on demand.
# Não imprime senha, chave ou conteúdo de .env.
set -euo pipefail

base="${1:-}"
sha="${2:-}"
archive="${3:-}"

if [[ ! "$base" =~ ^/[A-Za-z0-9_./-]+$ || "$base" == *..* ]]; then
  echo "Caminho de deploy recusado." >&2
  exit 1
fi
if [[ ! "$sha" =~ ^[0-9a-f]{40}$ ]]; then
  echo "SHA do release recusado." >&2
  exit 1
fi
if [[ ! -f "$archive" ]]; then
  echo "Arquivo do release ausente." >&2
  exit 1
fi

releases="$base/releases"
shared="$base/shared"
backups="$base/backups"
target="$releases/$sha"
current="$base/current"
env_file="$shared/.env"
fpm_reload="${LAW_FPM_RELOAD:-sudo -n systemctl reload php8.5-fpm}"

test -f "$env_file"
test -d "$shared/storage"
mkdir -p "$releases" "$backups"
if [[ -e "$target" ]]; then
  echo "O release $sha já existe. Não vou sobrescrever." >&2
  exit 1
fi

read_env() {
  local key="$1"
  local value
  value="$(grep -E "^${key}=" "$env_file" | tail -n 1 | cut -d= -f2- | tr -d '\r')"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  printf '%s' "$value"
}

db_connection="$(read_env DB_CONNECTION)"
db_host="$(read_env DB_HOST)"
db_port="$(read_env DB_PORT)"
db_database="$(read_env DB_DATABASE)"
db_username="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"

if [[ "$db_connection" != "mysql" || "$db_host" != "127.0.0.1" || "$db_database" != "fokuslaw" || -z "$db_username" ]]; then
  echo "O .env do site não aponta para o MySQL local fokuslaw. Backup recusado." >&2
  exit 1
fi

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="$backups/pre-release-${sha}-${stamp}.sql.gz"
defaults="$(mktemp)"
chmod 600 "$defaults"
cleanup_defaults() { rm -f "$defaults"; }
trap cleanup_defaults EXIT
cat > "$defaults" <<EOF
[client]
host=127.0.0.1
port=${db_port:-3306}
user=${db_username}
password=${db_password}
EOF
echo "Backup pré-release em ${backup_file}."
mysqldump --defaults-extra-file="$defaults" --single-transaction --no-tablespaces --databases "$db_database" | gzip -c > "$backup_file"
chmod 600 "$backup_file"
rm -f "$defaults"
trap - EXIT

if [[ -L "$current" ]]; then
  previous="$(readlink -f "$current" || true)"
else
  previous=""
fi

mkdir -p "$target"
remove_partial() { rm -rf "$target"; }
trap remove_partial EXIT
tar -xzf "$archive" -C "$target"
rm -rf "$target/storage"
ln -s "$shared/storage" "$target/storage"
ln -sfn "$env_file" "$target/.env"
mkdir -p "$target/bootstrap/cache"
chmod 775 "$target/bootstrap/cache"

echo "Migrations no release $sha, antes da troca do symlink."
(cd "$target" && php artisan migrate --force --no-interaction)

ln -sfn "$target" "$current.next"
mv -Tf "$current.next" "$current"
trap - EXIT

reload_failed=0
if ! bash -lc "$fpm_reload"; then
  echo "Não foi possível recarregar o PHP-FPM." >&2
  reload_failed=1
fi

health_code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 --resolve law.ios1vcrime.com.br:443:127.0.0.1 --insecure "https://law.ios1vcrime.com.br/novologin/up" || true)"
echo "Health /novologin/up: ${health_code:-000}."

if [[ "$reload_failed" != "0" || "$health_code" != "200" ]]; then
  if [[ -n "$previous" && -d "$previous" ]]; then
    echo "Health falhou. Voltando o symlink para o release anterior."
    ln -sfn "$previous" "$current.next"
    mv -Tf "$current.next" "$current"
    bash -lc "$fpm_reload" || true
  else
    echo "Health falhou e não há release anterior para restaurar." >&2
  fi
  exit 1
fi

echo "Release ativo: $sha."
echo "Release anterior: ${previous:-nenhum}."
echo "Backup: $backup_file."

#!/bin/bash
# Gera uma cópia cifrada do banco fokuslaw e do storage do Law.
# Executar como root. A senha do gpg fica fora deste repositório.
set -euo pipefail

base="${LAW_BASE:-/home/law-ondemand/htdocs/law.ios1vcrime.com.br}"
env_file="$base/shared/.env"
passphrase_file="${LAW_BACKUP_PASSPHRASE_FILE:-/root/fokus-law-backup.passphrase}"
outbox="${LAW_BACKUP_OUTBOX:-/var/backups/fokus-law/outbox}"
group="${LAW_BACKUP_GROUP:-lawbackup}"

if [[ "$(id -u)" != "0" ]]; then
  echo "Este script precisa de root para gravar a caixa de saída." >&2
  exit 1
fi
test -f "$env_file"
test -f "$passphrase_file"
test -d "$base/shared/storage"

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

db_host="$(read_env DB_HOST)"
db_port="$(read_env DB_PORT)"
db_database="$(read_env DB_DATABASE)"
db_username="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"

if [[ "$db_host" != "127.0.0.1" || "$db_database" != "fokuslaw" || -z "$db_username" ]]; then
  echo "Recusado: o .env não é o MySQL local fokuslaw." >&2
  exit 1
fi

install -d -o root -g "$group" -m 2750 "$outbox"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
work="$(mktemp -d)"
chmod 700 "$work"
cleanup() { rm -rf "$work"; }
trap cleanup EXIT

defaults="$work/client.cnf"
install -m 600 /dev/null "$defaults"
cat > "$defaults" <<EOF
[client]
host=127.0.0.1
port=${db_port:-3306}
user=${db_username}
password=${db_password}
EOF

mysqldump --defaults-extra-file="$defaults" --single-transaction --no-tablespaces --databases "$db_database" | gzip -c > "$work/database.sql.gz"
tar -C "$base/shared/storage" \
  --exclude='framework/cache' \
  --exclude='framework/sessions' \
  --exclude='framework/views' \
  --exclude='logs' \
  -czf "$work/storage.tar.gz" .

encrypt() {
  local source="$1"
  local name="$2"
  gpg --batch --yes --pinentry-mode loopback \
    --passphrase-file "$passphrase_file" \
    --symmetric --cipher-algo AES256 \
    --output "$outbox/$name" "$source"
  chown root:"$group" "$outbox/$name"
  chmod 640 "$outbox/$name"
  sha256sum "$outbox/$name" | awk '{print $1}'
}

db_name="fokuslaw-${stamp}.sql.gz.gpg"
storage_name="storage-${stamp}.tar.gz.gpg"
db_hash="$(encrypt "$work/database.sql.gz" "$db_name")"
storage_hash="$(encrypt "$work/storage.tar.gz" "$storage_name")"

manifest="$outbox/MANIFEST.txt"
touch "$manifest"
chown root:"$group" "$manifest"
chmod 640 "$manifest"
printf '%s database %s %s\n' "$stamp" "$db_name" "$db_hash" >> "$manifest"
printf '%s storage %s %s\n' "$stamp" "$storage_name" "$storage_hash" >> "$manifest"
echo "Caixa de saída atualizada. Manifesto sem segredos: $manifest."

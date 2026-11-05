#!/bin/bash
set -e

echo "=== [SGPE PGE-MA DB] Importando dump completo (petrvs, tenantpgema, petrvs_logs) ==="

if [ -f /dumps/pge_ma_complete_setup.sql.gz ]; then
    gunzip -c /dumps/pge_ma_complete_setup.sql.gz | mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4
    echo "=== [SGPE PGE-MA DB] Dump importado com sucesso! ==="
else
    echo "AVISO: Arquivo /dumps/pge_ma_complete_setup.sql.gz não encontrado!"
fi

APP_DOMAIN="${PGEMA_DOMAIN:-192.168.8.175}"
echo "=== [SGPE PGE-MA DB] Ajustando domínios (${APP_DOMAIN}) e limpando logs dev ==="

mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4 <<EOSQL
USE petrvs;

-- Registra o domínio/IP na tabela domains para resolução imediata do tenant pgema
INSERT IGNORE INTO domains (domain, tenant_id, created_at, updated_at) VALUES
('${APP_DOMAIN}', 'pgema', NOW(), NOW());

-- Limpa registros pesados do Telescope gerados na máquina de desenvolvimento
TRUNCATE TABLE telescope_entries_tags;
DELETE FROM telescope_entries;
TRUNCATE TABLE sessions;
EOSQL

echo "=== [SGPE PGE-MA DB] Banco inicializado e pronto para uso! ==="


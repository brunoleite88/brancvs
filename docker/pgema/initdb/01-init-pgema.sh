#!/bin/bash
set -e

echo "=== [SGPE PGE-MA DB] Pre-criando banco tenantpgema e routines que faltaram no dump ==="

mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4 <<'EOSQL'
CREATE DATABASE IF NOT EXISTS `petrvs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `tenantpgema` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `petrvs_logs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `tenantpgema`;

DELIMITER $$

DROP FUNCTION IF EXISTS `fn_obter_unidade_hierarquia`$$
CREATE FUNCTION `fn_obter_unidade_hierarquia`(p_unidade_id CHAR(36))
RETURNS TEXT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
DETERMINISTIC
BEGIN
    DECLARE V_PATH TEXT;

    WITH RECURSIVE ancestrais AS (
        SELECT
            id,
            sigla,
            unidade_pai_id,
            1 AS nivel
        FROM unidades
        WHERE id = p_unidade_id

        UNION ALL

        SELECT
            u.id,
            u.sigla,
            u.unidade_pai_id,
            a.nivel + 1
        FROM unidades u
        JOIN ancestrais a ON u.id = a.unidade_pai_id
        AND u.unidade_pai_id IS NOT NULL
    )
    SELECT GROUP_CONCAT(sigla ORDER BY nivel DESC SEPARATOR '/') INTO V_PATH
    FROM ancestrais;

    RETURN V_PATH;
END$$

DROP PROCEDURE IF EXISTS `sequence_template_numero`$$
CREATE PROCEDURE `sequence_template_numero`()
BEGIN
    UPDATE sequences SET template_numero = template_numero + 1;
    SELECT template_numero AS number FROM sequences;
END$$

DROP PROCEDURE IF EXISTS `sequence_plano_entrega_numero`$$
CREATE PROCEDURE `sequence_plano_entrega_numero`()
BEGIN
    UPDATE sequences SET plano_entrega_numero = plano_entrega_numero + 1;
    SELECT plano_entrega_numero AS number FROM sequences;
END$$

DROP PROCEDURE IF EXISTS `sequence_plano_trabalho_numero`$$
CREATE PROCEDURE `sequence_plano_trabalho_numero`()
BEGIN
    UPDATE sequences SET plano_trabalho_numero = plano_trabalho_numero + 1;
    SELECT plano_trabalho_numero AS number FROM sequences;
END$$

DROP PROCEDURE IF EXISTS `sequence_projeto_numero`$$
CREATE PROCEDURE `sequence_projeto_numero`()
BEGIN
    UPDATE sequences SET projeto_numero = GREATEST(IFNULL((SELECT MAX(numero) FROM projetos), 1), projeto_numero + 1);
    SELECT projeto_numero AS number FROM sequences;
END$$

DROP PROCEDURE IF EXISTS `sequence_documento_numero`$$
CREATE PROCEDURE `sequence_documento_numero`()
BEGIN
    UPDATE sequences SET documento_numero = GREATEST(
        IFNULL((SELECT MAX(numero) FROM documentos), 0),
        documento_numero
    ) + 1;
    SELECT documento_numero AS number FROM sequences LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sequence_atividade_numero`$$
CREATE PROCEDURE `sequence_atividade_numero`()
BEGIN
    UPDATE sequences SET atividade_numero = atividade_numero + 1;
    SELECT atividade_numero AS number FROM sequences;
END$$

DROP PROCEDURE IF EXISTS `sequence_notificacao_numero`$$
CREATE PROCEDURE `sequence_notificacao_numero`()
BEGIN
    UPDATE sequences SET notificacao_numero = notificacao_numero + 1;
    SELECT notificacao_numero AS number FROM sequences;
END$$

DELIMITER ;
EOSQL

echo "=== [SGPE PGE-MA DB] Importando dump completo (petrvs, tenantpgema, petrvs_logs) ==="

if [ -f /dumps/pge_ma_complete_setup.sql.gz ]; then
    gunzip -c /dumps/pge_ma_complete_setup.sql.gz | mariadb --force -u root -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4
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

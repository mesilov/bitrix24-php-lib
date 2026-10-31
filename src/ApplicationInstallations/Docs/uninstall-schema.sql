-- Пример миграции PostgreSQL: только добавление таблицы, без очистки существующих данных.
CREATE TABLE b24lib_uninstallations (
    application_token_hash VARCHAR(64) NOT NULL,
    installation_id UUID NOT NULL,
    member_id_hash VARCHAR(64) NOT NULL,
    clean BOOLEAN NOT NULL,
    requested_at_utc TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    completed_at_utc TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY (installation_id)
);
CREATE INDEX b24lib_uninstallations_idx_completed ON b24lib_uninstallations (completed_at_utc);
CREATE INDEX b24lib_uninstallations_idx_token_member ON b24lib_uninstallations (application_token_hash, member_id_hash);

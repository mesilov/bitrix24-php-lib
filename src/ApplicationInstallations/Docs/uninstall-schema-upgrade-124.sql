-- PostgreSQL: upgrade the receipt table introduced in #122 without deleting existing rows.
-- Run all statements in one application migration transaction, before deploying the new mapping.
-- Constraint names below match uninstall-schema.sql from #122; adapt names for a custom schema.
ALTER TABLE b24lib_uninstallations DROP CONSTRAINT b24lib_uninstallations_pkey;
ALTER TABLE b24lib_uninstallations DROP CONSTRAINT b24lib_uninstallations_unique_installation;
ALTER TABLE b24lib_uninstallations ADD PRIMARY KEY (installation_id);
CREATE INDEX b24lib_uninstallations_idx_token_member ON b24lib_uninstallations (application_token_hash, member_id_hash);

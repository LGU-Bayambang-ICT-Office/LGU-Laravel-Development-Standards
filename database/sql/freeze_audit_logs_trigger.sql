-- ==============================================================================
-- LGU Audit Ledger Freeze Trigger (PostgreSQL & MySQL Compatible Snippets)
-- Mandated by COA Circulars and RA 10173 (Data Privacy Act of 2012)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- OPTION A: PostgreSQL Immutable Trigger Function
-- ------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION freeze_audit_ledger()
RETURNS TRIGGER AS $$
BEGIN
    IF (TG_OP = 'UPDATE' OR TG_OP = 'DELETE') THEN
        RAISE EXCEPTION 'COA/DPA Statutory Violation: Modification or deletion of the audit ledger is strictly prohibited.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_freeze_audit_ledger ON audit_activities;

CREATE TRIGGER trg_freeze_audit_ledger
BEFORE UPDATE OR DELETE ON audit_activities
FOR EACH ROW
EXECUTE FUNCTION freeze_audit_ledger();


-- ------------------------------------------------------------------------------
-- OPTION B: MySQL / MariaDB BEFORE UPDATE / DELETE Triggers
-- ------------------------------------------------------------------------------
-- DELIMITER $$
-- CREATE TRIGGER trg_freeze_audit_ledger_update
-- BEFORE UPDATE ON audit_activities
-- FOR EACH ROW
-- BEGIN
--     SIGNAL SQLSTATE '45000'
--     SET MESSAGE_TEXT = 'COA/DPA Statutory Violation: Updates to audit_activities are prohibited.';
-- END$$
--
-- CREATE TRIGGER trg_freeze_audit_ledger_delete
-- BEFORE DELETE ON audit_activities
-- FOR EACH ROW
-- BEGIN
--     SIGNAL SQLSTATE '45000'
--     SET MESSAGE_TEXT = 'COA/DPA Statutory Violation: Deletions from audit_activities are prohibited.';
-- END$$
-- DELIMITER ;

-- Run once on a database that does not yet have resolution_notes.
-- This column has already been added manually to the current localhost database.
-- Select the intended database before running this migration.
ALTER TABLE maintenance_requests
    ADD COLUMN resolution_notes TEXT NULL;

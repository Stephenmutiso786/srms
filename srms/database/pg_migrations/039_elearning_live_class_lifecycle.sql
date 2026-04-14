BEGIN;

ALTER TABLE IF EXISTS tbl_live_classes
  ADD COLUMN IF NOT EXISTS status varchar(20) NOT NULL DEFAULT 'scheduled',
  ADD COLUMN IF NOT EXISTS started_at timestamp NULL,
  ADD COLUMN IF NOT EXISTS ended_at timestamp NULL,
  ADD COLUMN IF NOT EXISTS updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE tbl_live_classes
SET status = COALESCE(NULLIF(status, ''), 'scheduled')
WHERE status IS NULL OR status = '';

COMMIT;

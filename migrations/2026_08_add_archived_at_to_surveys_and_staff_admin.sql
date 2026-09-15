-- The residents table already has archived_at, but surveys and staff_admin
-- were missed, even though surveys.php, reports.php, users.php, and
-- archive.php all query it. This adds the column to both tables so the
-- "archive" feature works consistently across all three record types.

ALTER TABLE `surveys`
  ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`;

ALTER TABLE `staff_admin`
  ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`;

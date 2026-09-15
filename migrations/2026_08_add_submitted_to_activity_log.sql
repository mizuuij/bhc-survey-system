ALTER TABLE `activity_log`
  MODIFY `action` enum('Created','Updated','Archived','Deleted','Submitted') NOT NULL;

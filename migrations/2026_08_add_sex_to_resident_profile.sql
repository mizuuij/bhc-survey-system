
ALTER TABLE `resident_profile`
  ADD COLUMN `sex` ENUM('male','female') NULL DEFAULT NULL AFTER `extension_name`;

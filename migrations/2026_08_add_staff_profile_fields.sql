ALTER TABLE `staff_admin`
  ADD COLUMN `contact_number` varchar(30) NOT NULL DEFAULT '' AFTER `role`,
  ADD COLUMN `address` text NOT NULL AFTER `contact_number`,
  ADD COLUMN `birthday` date DEFAULT NULL AFTER `address`;
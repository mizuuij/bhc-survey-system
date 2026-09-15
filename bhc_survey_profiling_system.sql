-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2026 at 02:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bhc_survey_profiling_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_type` enum('resident','staff') NOT NULL,
  `actor_id` int(10) UNSIGNED NOT NULL,
  `actor_name` varchar(120) NOT NULL,
  `actor_role` varchar(30) NOT NULL,
  `action` enum('Created','Updated','Archived','Deleted','Submitted') NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_name` varchar(180) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `actor_type`, `actor_id`, `actor_name`, `actor_role`, `action`, `entity_type`, `entity_name`, `created_at`) VALUES
(1, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Member #8', '2026-08-19 15:27:29'),
(2, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Member #7', '2026-08-19 15:28:53'),
(3, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Member #6', '2026-08-19 15:30:05'),
(4, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Christine Villanueva', '2026-08-19 15:30:41'),
(5, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Roberto Cruz', '2026-08-19 15:30:53'),
(6, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Survey', 'Maternal Survey', '2026-08-19 15:59:34'),
(7, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Christine Villanueva', '2026-08-19 16:00:08'),
(8, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Angela Ramos', '2026-08-19 16:03:56'),
(9, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Member #5', '2026-08-19 16:05:06'),
(10, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Staff account', 'Raiza Evangelista', '2026-08-19 16:27:40'),
(11, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Joseph Ericson Aniag', '2026-08-20 01:34:36'),
(12, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Joseph Ericson Aniag', '2026-08-20 01:34:38'),
(13, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 01:34:39'),
(14, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 01:34:40'),
(15, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 01:34:41'),
(16, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 01:34:50'),
(17, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Roberto Cruz', '2026-08-20 01:35:29'),
(18, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Roberto Cruz', '2026-08-20 01:35:36'),
(19, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Maternal Survey', '2026-08-20 01:54:35'),
(20, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Staff account', 'Joseph Ericson Aniag', '2026-08-20 02:12:05'),
(21, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 02:35:12'),
(22, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Staff account', 'Shanna Louis', '2026-08-20 02:36:48'),
(23, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Staff account', 'Shanna Louis Carreon', '2026-08-20 02:37:06'),
(24, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Khab A Jack', '2026-08-20 05:16:46'),
(25, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Khab A Jack', '2026-08-20 05:38:07'),
(26, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Roberto Cruz', '2026-08-20 06:17:20'),
(27, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Grace Villanueva', '2026-08-20 06:17:21'),
(28, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Ana Rodriguez', '2026-08-20 06:17:23'),
(29, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Pedro Reyes', '2026-08-20 06:17:27'),
(30, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Member', 'Juan Dela Cruz', '2026-08-20 06:17:29'),
(31, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Maternal Survey', '2026-08-20 06:33:48'),
(32, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Maternal Survey', '2026-08-20 06:33:56'),
(33, 'staff', 1, 'System Administrator', 'Admin', 'Archived', 'Survey', 'Maternal Survey', '2026-08-20 06:33:59'),
(34, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:34:08'),
(35, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:34:10'),
(36, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:34:50'),
(37, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:45:44'),
(38, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:46:16'),
(39, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Survey', 'Barangay Health Center Services Survey', '2026-08-20 06:46:21'),
(40, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Maria Anne Reyes Santos', '2026-08-20 06:52:55'),
(41, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Maria Anne Reyes Santos', '2026-08-20 06:53:13'),
(42, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Juan Aquino Dela Cruz', '2026-08-20 07:59:50'),
(43, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Liza Mendoza Santos', '2026-08-20 08:00:18'),
(44, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Carlos Bautista Ramirez', '2026-08-20 08:00:33'),
(45, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Grace Torres Fernandez', '2026-08-20 08:01:15'),
(46, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Member', 'Mark Anthony Dizon Villanueva', '2026-08-20 08:01:35'),
(47, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Survey', 'Nutrition and Feeding Program Survey', '2026-08-20 08:02:34'),
(48, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Survey', 'Senior Citizens Health Assessment', '2026-08-20 08:02:57'),
(49, 'staff', 1, 'System Administrator', 'Admin', 'Created', 'Survey', 'Water and Sanitation Access Survey', '2026-08-20 08:03:27'),
(50, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Mark Anthony Dizon Villanueva', '2026-08-20 08:05:10'),
(51, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Juan Aquino Dela Cruz', '2026-08-20 08:05:26'),
(52, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Liza Mendoza Santos', '2026-08-20 08:05:36'),
(53, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Carlos Bautista Ramirez', '2026-08-20 08:05:44'),
(54, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Grace Torres Fernandez', '2026-08-20 08:05:55'),
(55, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Juan Aquino Dela Cruz', '2026-08-20 08:06:44'),
(56, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Liza Mendoza Santos', '2026-08-20 08:06:57'),
(57, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Carlos Bautista Ramirez', '2026-08-20 08:07:13'),
(58, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Grace Torres Fernandez', '2026-08-20 08:07:24'),
(59, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Mark Anthony Dizon Villanueva', '2026-08-20 08:07:39'),
(60, 'staff', 1, 'System Administrator', 'Admin', 'Deleted', 'Survey', 'Maternal Survey', '2026-08-20 08:11:59'),
(61, 'staff', 1, 'System Administrator', 'Admin', 'Deleted', 'Member', 'Khab A Jack', '2026-08-20 08:12:04'),
(62, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Carlos Bautistas Ramirez', '2026-08-20 08:20:42'),
(63, 'staff', 1, 'System Administrator', 'Admin', 'Updated', 'Member', 'Carlos Bautista Ramirez', '2026-08-20 08:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_number` varchar(30) NOT NULL,
  `household_number` varchar(30) NOT NULL,
  `head_name` varchar(120) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `address` text NOT NULL,
  `purok` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `resident_number`, `household_number`, `head_name`, `contact_number`, `address`, `purok`, `is_active`, `password_hash`, `must_change_password`, `archived_at`, `created_at`, `updated_at`) VALUES
(1, 'HH-0001', 'HH-0001', 'Maria Anne Reyes Santos', '09123456781', '123 Sampaguita St., Purok 4, Barangay Longos, Malolos City, Bulacan', 'Purok 4', 1, '$2y$10$cXKXX7642GVRlvOUfaz0V.i8dNkg5K1CzFRpkX309A9ZyOrtkj9wW', 1, NULL, '2026-08-01 03:04:24', '2026-08-20 12:16:35'),
(2, 'HH-0002', 'HH-0002', 'Juan Dela Cruz', '09175550002', '45 Ilang-Ilang St., Purok 2, Barangay San Isidro', NULL, 0, '$2y$10$IjAKzxf4lleUL5JRKnSu1eC5akPJc1I.kZ3u9r7/jdyaOLGTus3Aq', 1, '2026-08-20 06:17:29', '2026-08-01 03:04:24', '2026-08-20 06:17:29'),
(3, 'HH-0003', 'HH-0003', 'Pedro Reyes', '09171234503', '67 Rosal St., Purok 1, Barangay San Isidro', NULL, 0, '$2y$10$Yeejfw5HOFSn44wVIMls.eV/K.F1CXCp13703D0pxedIAHi5NpD2K', 1, '2026-08-20 06:17:27', '2026-08-01 05:03:57', '2026-08-20 06:17:27'),
(4, 'HH-0004', 'HH-0004', 'Ana Rodriguez', '09171234504', '89 Gumamela St., Purok 3, Barangay San Isidro', NULL, 0, '$2y$10$a0wMYCgts6ynCqlT5iTcU.ydKQSjgPEk3IY1lEOo0Lz2E1GbPIgh.', 1, '2026-08-20 06:17:23', '2026-08-01 05:04:33', '2026-08-20 06:17:23'),
(5, 'HH-0005', 'HH-0005', 'Grace Villanueva', '09171234508', '34 Camia St., Purok 4, Barangay San Isidro', NULL, 0, '$2y$10$bbN4EqiwsMKAZ68nrWSH.ufJKJvzClib3dO1n9oAaFmR8o4rHcckO', 1, '2026-08-20 06:17:21', '2026-08-01 05:05:28', '2026-08-20 06:17:21'),
(6, 'HH-0006', 'HH-0006', 'Roberto Cruz', '09171234503', '90 Sunflower St., Purok 3, Barangay San Isidro', 'Purok 3', 0, '$2y$10$Q8NwRQ8j67RnqXZlRD7akO6wlZMS6bLsSxuLA4ZzuZLdfM.aKeEpG', 1, '2026-08-20 06:17:20', '2026-08-01 06:24:36', '2026-08-20 06:17:20'),
(7, 'HH-0007', 'HH-0007', 'Angela Ramos', '09171234504', '25 Sampaguita St., Purok 1, Barangay San Isidro', NULL, 0, '$2y$10$bV2QEnALBCmrzVDnEAfsAuT/nwRXuKogaY0a2HA0t6ZF6qPho8f4e', 1, '2026-08-19 16:03:56', '2026-08-01 06:43:31', '2026-08-19 16:03:56'),
(8, 'HH-0008', 'HH-0008', 'Christine Villanueva', '09171234511', '56 Mabini St., Purok 4, Barangay San Isidro', 'Purok 4', 0, '$2y$10$jdCuuvHva3iAz7nJ1QTKFuJRWea8lTF4Wo4SYN2yj3R03j9d1bmVq', 1, '2026-08-19 16:00:08', '2026-08-01 11:57:21', '2026-08-19 16:00:08'),
(10, 'HH-0010', 'HH-0010', 'Juan Aquino Dela Cruz', '09171234567', '123 Sampaguita St., Purok 1, Barangay Longos, Malolos City, Bulacan', 'Purok 1', 1, '$2y$10$zOsuzbFyDcxd2eo.ohXmHuAimvWROEprRbR.GohvQSu.NXrwe/u7G', 1, NULL, '2026-08-20 07:59:50', '2026-08-20 12:16:34'),
(11, 'HH-0011', 'HH-0011', 'Liza Mendoza Santos', '09182345678', '45 Ilang-Ilang St., Purok 2, Barangay Longos, Malolos City, Bulacan', 'Purok 2', 1, '$2y$10$7SkfXmhox0RDCT50oR/Gz.6Xd3YGcRl4u8tBjCmywk/IGkhyEx3jK', 1, NULL, '2026-08-20 08:00:18', '2026-08-20 12:16:32'),
(12, 'HH-0012', 'HH-0012', 'Carlos Bautista Ramirez', '09193456789', '78 Rosal St., Purok 3, Barangay Longos, Malolos City, Bulacan', 'Purok 3', 1, '$2y$10$RE.oR4EnVlV4V.ej0BOXO.gJ3x7.OLe3mNgAlkrzXUhXVLoD/527a', 1, NULL, '2026-08-20 08:00:33', '2026-08-20 12:16:31'),
(13, 'HH-0013', 'HH-0013', 'Grace Torres Fernandez', '09204567890', '12 Camia St., Purok 4, Barangay Longos, Malolos City, Bulacan', 'Purok 4', 1, '$2y$10$UAlV2VjEVEXGnpibohRFzuO3YEdzzt5n.ePn3pqdgXYPbftkNTfQ2', 1, NULL, '2026-08-20 08:01:15', '2026-08-20 12:16:30'),
(14, 'HH-0014', 'HH-0014', 'Mark Anthony Dizon Villanueva', '09215678901', '56 Kalachuchi St., Purok 4, Barangay Longos, Malolos City, Bulacan', 'Purok 1', 1, '$2y$10$KXhuAosNTUZZYxcVIcnG9ud7c2Zj4u8TgBcpRTyQonHmzRl8dC8Hu', 1, NULL, '2026-08-20 08:01:35', '2026-08-20 12:16:28');

-- --------------------------------------------------------

--
-- Table structure for table `resident_children`
--

CREATE TABLE `resident_children` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `child_name` varchar(120) NOT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `resident_children`
--

INSERT INTO `resident_children` (`id`, `resident_id`, `child_name`, `sex`, `age`, `created_at`) VALUES
(9, 1, 'Mellow R. Santos', NULL, 1, '2026-08-20 02:27:58'),
(10, 13, 'Anna Marie Fernandez', 'female', 8, '2026-08-20 08:01:15'),
(11, 13, 'Miguel Fernandez', 'male', 5, '2026-08-20 08:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `resident_parents`
--

CREATE TABLE `resident_parents` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `father_name` varchar(120) DEFAULT NULL,
  `mother_name` varchar(120) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `resident_parents`
--

INSERT INTO `resident_parents` (`id`, `resident_id`, `father_name`, `mother_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Mauricio F. Santos', 'May L. Santos', '2026-08-18 11:26:09', '2026-08-20 02:29:17'),
(6, 13, 'Eduardo Torres', 'Corazon Torres', '2026-08-20 08:01:15', '2026-08-20 08:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `resident_profile`
--

CREATE TABLE `resident_profile` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `extension_name` varchar(20) DEFAULT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','separated','divorced') DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `occupation` varchar(120) DEFAULT NULL,
  `employer` varchar(120) DEFAULT NULL,
  `employer_address` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `resident_profile`
--

INSERT INTO `resident_profile` (`id`, `resident_id`, `last_name`, `first_name`, `middle_name`, `extension_name`, `sex`, `civil_status`, `birthday`, `occupation`, `employer`, `employer_address`, `photo_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'Santos', 'Maria Anne', 'Reyes', NULL, 'female', 'married', '1984-07-25', NULL, NULL, NULL, NULL, '2026-08-18 11:26:35', '2026-08-20 06:26:22'),
(15, 8, 'Villanueva', 'Christine', NULL, NULL, 'female', 'single', '2005-08-18', NULL, NULL, NULL, NULL, '2026-08-19 15:27:29', '2026-08-19 15:27:29'),
(16, 7, 'Ramos', 'Angela', NULL, NULL, 'female', 'widowed', '1983-03-18', NULL, NULL, NULL, NULL, '2026-08-19 15:28:53', '2026-08-19 15:28:53'),
(17, 6, 'Cruz', 'Roberto', NULL, NULL, 'male', 'married', '1990-10-26', NULL, NULL, NULL, NULL, '2026-08-19 15:30:05', '2026-08-19 15:30:05'),
(18, 5, 'Villanueva', 'Grace', NULL, NULL, 'female', 'widowed', '1987-09-01', NULL, NULL, NULL, NULL, '2026-08-19 16:05:05', '2026-08-19 16:05:05'),
(23, 10, 'Dela Cruz', 'Juan', 'Aquino', NULL, 'male', 'married', '1985-03-12', NULL, NULL, NULL, NULL, '2026-08-20 07:59:50', '2026-08-20 07:59:50'),
(24, 11, 'Santos', 'Liza', 'Mendoza', NULL, 'female', 'single', '1998-07-22', 'Teacher', NULL, NULL, NULL, '2026-08-20 08:00:18', '2026-08-20 08:00:18'),
(25, 12, 'Ramirez', 'Carlos', 'Bautista', NULL, 'male', 'widowed', '1958-11-05', 'Retired', NULL, NULL, NULL, '2026-08-20 08:00:33', '2026-08-20 08:20:48'),
(26, 13, 'Fernandez', 'Grace', 'Torres', NULL, 'female', 'married', '1990-01-30', 'Nurse', 'Barangay Health Center', NULL, NULL, '2026-08-20 08:01:15', '2026-08-20 08:01:15'),
(27, 14, 'Villanueva', 'Mark Anthony', 'Dizon', NULL, 'male', 'separated', '1982-09-18', 'Electrician', NULL, NULL, NULL, '2026-08-20 08:01:35', '2026-08-20 08:01:35');

-- --------------------------------------------------------

--
-- Table structure for table `resident_references`
--

CREATE TABLE `resident_references` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `reference_name` varchar(120) NOT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `resident_references`
--

INSERT INTO `resident_references` (`id`, `resident_id`, `reference_name`, `signature_path`, `created_at`) VALUES
(7, 1, 'Hannah A. Cruz', NULL, '2026-08-20 02:46:19'),
(8, 1, 'Eric C. Yutuc', NULL, '2026-08-20 02:46:19'),
(9, 13, 'Anna Reyes', NULL, '2026-08-20 08:01:15'),
(10, 13, 'Peter Lim', NULL, '2026-08-20 08:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `resident_spouse`
--

CREATE TABLE `resident_spouse` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `spouse_name` varchar(120) NOT NULL,
  `occupation` varchar(120) DEFAULT NULL,
  `employer` varchar(120) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `resident_spouse`
--

INSERT INTO `resident_spouse` (`id`, `resident_id`, `spouse_name`, `occupation`, `employer`, `created_at`, `updated_at`) VALUES
(1, 1, 'Juan D. Ignacio', NULL, NULL, '2026-08-18 11:26:05', '2026-08-18 15:39:26'),
(8, 13, 'Ramon Fernandez', 'Carpenter', NULL, '2026-08-20 08:01:15', '2026-08-20 08:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `staff_admin`
--

CREATE TABLE `staff_admin` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `contact_number` varchar(30) NOT NULL DEFAULT '',
  `address` text NOT NULL,
  `birthday` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff_admin`
--

INSERT INTO `staff_admin` (`id`, `username`, `full_name`, `password_hash`, `role`, `contact_number`, `address`, `birthday`, `is_active`, `archived_at`, `created_at`) VALUES
(1, 'admin', 'System Administrator', '$2y$10$P9xqNRinoF67XhHAx8OV.uBUwPqanP1XdXGl7/HTqy2NZMmIFj.Ba', 'admin', '', '', NULL, 1, NULL, '2026-08-01 03:04:42'),
(2, 'staff', 'Shanna Louis Carreon', '$2y$10$NxxsWDoEeR9U22zaASOsvuzZncpfQHMPOXrpfJBYQM0f5d35AQtzW', 'staff', '', '', NULL, 0, '2026-08-20 02:35:12', '2026-08-01 06:48:32'),
(3, 'staff2', 'Joseph Ericson Aniag', '$2y$10$CbqpDf/7xK5hfBWza5G6luDgB1tiCbUktuFegYMzDrKgrCGdQZ/3S', 'staff', '', '', NULL, 0, '2026-08-20 02:12:05', '2026-08-01 12:02:11'),
(4, 'STF-0003', 'Raiza Evangelista', '$2y$10$xJVabZ0d4wn20ETo8X9Gu.qCOuKkGJ64zQDDotfkKG.0jUEcWPYtq', 'staff', '', '', NULL, 1, NULL, '2026-08-19 16:27:40'),
(5, 'STF-0001', 'Shanna Louis Carreon', '$2y$10$yWt.yy3//AhYGv8d1Rt.LuSZk1rkM1WzofaRt8YGVsaKdljvnYtym', 'staff', '', '', NULL, 1, NULL, '2026-08-20 02:36:48');

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE `surveys` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `opens_at` date NOT NULL,
  `closes_at` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `title`, `description`, `opens_at`, `closes_at`, `is_active`, `archived_at`, `created_at`) VALUES
(1, 'Household Health Survey', 'Help the Barangay Health Center understand the needs of your household.', '2026-07-15', '2026-08-05', 0, NULL, '2026-08-01 03:04:24'),
(2, 'Barangay Health Center Services Survey', 'This survey gathers feedback about services provided by the Barangay Health Center.', '2026-07-31', '2026-08-20', 0, NULL, '2026-08-01 04:55:27'),
(3, 'Community Health Needs Survey', 'This survey helps identify the health needs of community residents.', '2026-07-31', '2026-08-25', 1, NULL, '2026-08-01 04:57:10'),
(4, 'Health Center Facilities and Cleanliness Survey', 'This survey helps gather insights into the community medical school facilities and services', '2026-08-01', '2026-08-10', 0, NULL, '2026-08-01 05:20:45'),
(5, 'Patient Satisfaction Survey', 'This survey gathers feedback regarding services provided by the Barangay Health Center.', '2026-08-01', '2026-08-10', 0, NULL, '2026-08-01 06:41:35'),
(6, 'Health Program Awareness Survey', 'This survey aims to assess residents\' awareness of the programs and services offered by the Barangay Health Center.', '2026-08-01', '2026-08-15', 0, NULL, '2026-08-01 11:55:14'),
(9, 'Nutrition and Feeding Program Survey', 'Assessing household nutrition practices and feeding programs for children under 5.', '2026-08-20', '2026-09-19', 1, NULL, '2026-08-20 08:02:34'),
(10, 'Senior Citizens Health Assessment', 'Evaluating the health status and needs of senior citizens in the barangay.', '2026-08-20', '2026-09-03', 1, NULL, '2026-08-20 08:02:57'),
(11, 'Water and Sanitation Access Survey', 'Gathering data on household access to clean water and sanitation facilities.', '2026-08-20', '2026-09-10', 1, NULL, '2026-08-20 08:03:27');

-- --------------------------------------------------------

--
-- Table structure for table `survey_answers`
--

CREATE TABLE `survey_answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `submission_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `answer_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `survey_answers`
--

INSERT INTO `survey_answers` (`id`, `submission_id`, `question_id`, `answer_text`, `created_at`) VALUES
(1, 1, 1, '5-6', '2026-08-01 04:40:35'),
(2, 1, 2, 'yes', '2026-08-01 04:40:35'),
(3, 1, 3, '3', '2026-08-01 04:40:35'),
(4, 1, 4, NULL, '2026-08-01 04:40:35'),
(5, 2, 29, '1-2', '2026-08-01 05:13:49'),
(6, 2, 30, 'yes', '2026-08-01 05:13:49'),
(7, 2, 31, '5', '2026-08-01 05:13:49'),
(8, 2, 32, NULL, '2026-08-01 05:13:49'),
(9, 3, 37, 'Health Check-up', '2026-08-01 05:14:57'),
(10, 3, 38, 'yes', '2026-08-01 05:14:57'),
(11, 3, 39, '4', '2026-08-01 05:14:57'),
(12, 3, 40, NULL, '2026-08-01 05:14:57'),
(13, 4, 33, 'Disease Prevention', '2026-08-01 05:15:14'),
(14, 4, 34, 'yes', '2026-08-01 05:15:14'),
(15, 4, 35, '4', '2026-08-01 05:15:14'),
(16, 4, 36, NULL, '2026-08-01 05:15:14'),
(17, 5, 29, '5-6', '2026-08-01 05:16:29'),
(18, 5, 30, 'yes', '2026-08-01 05:16:29'),
(19, 5, 31, '3', '2026-08-01 05:16:29'),
(20, 5, 32, NULL, '2026-08-01 05:16:29'),
(21, 6, 37, 'Maternal Care', '2026-08-01 05:17:29'),
(22, 6, 38, 'yes', '2026-08-01 05:17:29'),
(23, 6, 39, '2', '2026-08-01 05:17:29'),
(24, 6, 40, 'Sana ay mabawasan ang oras ng paghihintay ng mga pasyente.', '2026-08-01 05:17:29'),
(25, 7, 33, 'Nutrition Programs', '2026-08-01 05:20:03'),
(26, 7, 34, 'yes', '2026-08-01 05:20:03'),
(27, 7, 35, '4', '2026-08-01 05:20:03'),
(28, 7, 36, 'Libreng dental check-up at bunot ng ngipin.', '2026-08-01 05:20:03'),
(29, 8, 49, 'Restroom', '2026-08-01 05:29:25'),
(30, 8, 50, 'no', '2026-08-01 05:29:25'),
(31, 8, 51, '3', '2026-08-01 05:29:25'),
(32, 8, 52, 'Mas madalas sana ang paglilinis ng comfort room.', '2026-08-01 05:29:25'),
(33, 9, 29, '7+', '2026-08-01 05:33:15'),
(34, 9, 30, 'yes', '2026-08-01 05:33:15'),
(35, 9, 31, '4', '2026-08-01 05:33:15'),
(36, 9, 32, 'Madalas po akong magkaroon ng mataas na presyon ng dugo.', '2026-08-01 05:33:15'),
(37, 10, 49, 'Medicine Dispensing Area', '2026-08-01 05:43:47'),
(38, 10, 50, 'no', '2026-08-01 05:43:47'),
(39, 10, 51, '4', '2026-08-01 05:43:47'),
(40, 10, 52, NULL, '2026-08-01 05:43:47'),
(41, 11, 49, 'Medicine Dispensing Area', '2026-08-01 07:32:01'),
(42, 11, 50, 'yes', '2026-08-01 07:32:01'),
(43, 11, 51, '5', '2026-08-01 07:32:01'),
(44, 11, 52, NULL, '2026-08-01 07:32:01'),
(45, 12, 53, 'Medical Consultation', '2026-08-01 07:50:41'),
(46, 12, 54, 'yes', '2026-08-01 07:50:41'),
(47, 12, 55, '4', '2026-08-01 07:50:41'),
(48, 12, 56, 'No proper queuing for patients.', '2026-08-01 07:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `survey_questions`
--

CREATE TABLE `survey_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `survey_id` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','yes_no','rating','short_answer') NOT NULL,
  `choices_text` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `survey_questions`
--

INSERT INTO `survey_questions` (`id`, `survey_id`, `question_text`, `question_type`, `choices_text`, `is_required`, `created_at`) VALUES
(32, 1, 'Any additional health concerns you\'d like to report?', 'short_answer', NULL, 0, '2026-08-01 05:13:07'),
(29, 1, 'How many members are in your household?', 'multiple_choice', '1-2\r\n3-4\r\n5-6\r\n7+', 1, '2026-08-01 05:13:07'),
(30, 1, 'Does your household have access to clean drinking water?', 'yes_no', NULL, 1, '2026-08-01 05:13:07'),
(31, 1, 'How would you rate the barangay health center\'s service?', 'rating', NULL, 1, '2026-08-01 05:13:07'),
(40, 2, 'What suggestions do you have for improving our services?', 'short_answer', NULL, 0, '2026-08-01 05:14:47'),
(39, 2, 'How would you rate the quality of service you received?', 'rating', NULL, 1, '2026-08-01 05:14:47'),
(38, 2, 'Were you able to receive the service you needed?', 'yes_no', NULL, 1, '2026-08-01 05:14:47'),
(36, 3, 'What additional health services would you like the Barangay Health Center to offer?', 'short_answer', NULL, 0, '2026-08-01 05:14:35'),
(34, 3, 'Are you aware of the programs and services offered by the Barangay Health Center?', 'yes_no', NULL, 1, '2026-08-01 05:14:35'),
(35, 3, 'How accessible are the health services in your barangay?', 'rating', NULL, 1, '2026-08-01 05:14:35'),
(33, 3, 'Which health program would you like the Barangay Health Center to prioritize?', 'multiple_choice', 'Vaccination\r\nMaternal and Child Health\r\nNutrition Programs\r\nSenior Citizen Care\r\nDisease Prevention', 1, '2026-08-01 05:14:35'),
(37, 2, 'What service did you most recently avail of?', 'multiple_choice', 'Medical Consultation\r\nHealth Check-up\r\nVaccination/Immunization\r\nMedicine Dispensing\r\nMaternal Care\r\nOther', 1, '2026-08-01 05:14:47'),
(51, 4, 'How would you rate the cleanliness and condition of the health center facilities?', 'rating', NULL, 1, '2026-08-01 06:23:00'),
(52, 4, 'What suggestions do you have for improving the cleanliness and facilities of the Barangay Health Center?', 'short_answer', NULL, 0, '2026-08-01 06:23:00'),
(50, 4, 'Do you think the Barangay Health Center is clean and well-maintained?', 'yes_no', NULL, 1, '2026-08-01 06:23:00'),
(49, 4, 'Which area of the Barangay Health Center do you think needs the most improvement?', 'multiple_choice', 'Waiting Area\r\nRestroom\r\nConsultation Room\r\nMedicine Dispensing Area\r\nNone, all areas are satisfactory', 1, '2026-08-01 06:23:00'),
(53, 5, 'What service did you most recently avail of?', 'multiple_choice', 'Medical Consultation\r\nHealth Check-up\r\nVaccination/Immunization\r\nMedicine Dispensing\r\nMaternal Care', 1, '2026-08-01 06:41:35'),
(54, 5, 'Were you able to receive the service you needed?', 'yes_no', NULL, 1, '2026-08-01 06:41:35'),
(55, 5, 'How would you rate the quality of service you received?', 'rating', NULL, 1, '2026-08-01 06:41:35'),
(56, 5, 'What suggestions do you have for improving our services?', 'short_answer', NULL, 0, '2026-08-01 06:41:35'),
(57, 6, 'How do you usually learn about health programs in your barangay?', 'multiple_choice', 'Barangay Announcements\r\nHealth Workers\r\nSocial Media\r\nFamily and Friends\r\nPosters and Flyers', 1, '2026-08-01 11:55:14'),
(58, 6, 'Have you participated in any health program organized by the Barangay Health Center within the past year?', 'yes_no', NULL, 1, '2026-08-01 11:55:14'),
(59, 6, 'How would you rate your awareness of the health programs available in your barangay?', 'rating', NULL, 1, '2026-08-01 11:55:14'),
(60, 6, 'Any additional health concerns you\'d like to report?', 'short_answer', NULL, 0, '2026-08-01 11:55:14'),
(62, 9, 'How often does your household eat vegetables per week?', 'multiple_choice', NULL, 1, '2026-08-20 08:02:34'),
(63, 9, 'Does your household have access to a feeding program?', 'yes_no', NULL, 1, '2026-08-20 08:02:34'),
(64, 10, 'How would you rate your overall health this month?', 'rating', NULL, 1, '2026-08-20 08:02:57'),
(65, 11, 'What is your main source of drinking water?', 'multiple_choice', NULL, 1, '2026-08-20 08:03:27');

-- --------------------------------------------------------

--
-- Table structure for table `survey_submissions`
--

CREATE TABLE `survey_submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `survey_id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `household_size` varchar(10) DEFAULT NULL,
  `clean_water_access` enum('yes','no') DEFAULT NULL,
  `service_rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `health_concerns` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `survey_submissions`
--

INSERT INTO `survey_submissions` (`id`, `survey_id`, `resident_id`, `household_size`, `clean_water_access`, `service_rating`, `health_concerns`, `submitted_at`) VALUES
(1, 1, 1, NULL, NULL, NULL, NULL, '2026-08-01 04:40:35'),
(2, 1, 3, NULL, NULL, NULL, NULL, '2026-08-01 05:13:49'),
(3, 2, 3, NULL, NULL, NULL, NULL, '2026-08-01 05:14:57'),
(4, 3, 3, NULL, NULL, NULL, NULL, '2026-08-01 05:15:14'),
(5, 1, 4, NULL, NULL, NULL, NULL, '2026-08-01 05:16:29'),
(6, 2, 4, NULL, NULL, NULL, NULL, '2026-08-01 05:17:29'),
(7, 3, 5, NULL, NULL, NULL, NULL, '2026-08-01 05:20:03'),
(8, 4, 5, NULL, NULL, NULL, NULL, '2026-08-01 05:29:25'),
(9, 1, 5, NULL, NULL, NULL, NULL, '2026-08-01 05:33:15'),
(10, 4, 2, NULL, NULL, NULL, NULL, '2026-08-01 05:43:47'),
(11, 4, 1, NULL, NULL, NULL, NULL, '2026-08-01 07:32:01'),
(12, 5, 1, NULL, NULL, NULL, NULL, '2026-08-01 07:50:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_log_created_at` (`created_at`),
  ADD KEY `activity_log_actor` (`actor_type`,`actor_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `resident_number` (`resident_number`),
  ADD UNIQUE KEY `household_number` (`household_number`);

--
-- Indexes for table `resident_children`
--
ALTER TABLE `resident_children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_children_resident` (`resident_id`);

--
-- Indexes for table `resident_parents`
--
ALTER TABLE `resident_parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resident_parents` (`resident_id`),
  ADD KEY `fk_parents_resident` (`resident_id`);

--
-- Indexes for table `resident_profile`
--
ALTER TABLE `resident_profile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resident_profile` (`resident_id`),
  ADD KEY `fk_profile_resident` (`resident_id`);

--
-- Indexes for table `resident_references`
--
ALTER TABLE `resident_references`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_references_resident` (`resident_id`);

--
-- Indexes for table `resident_spouse`
--
ALTER TABLE `resident_spouse`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resident_spouse` (`resident_id`),
  ADD KEY `fk_spouse_resident` (`resident_id`);

--
-- Indexes for table `staff_admin`
--
ALTER TABLE `staff_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `survey_answers`
--
ALTER TABLE `survey_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_answer_per_question` (`submission_id`,`question_id`),
  ADD KEY `fk_answer_question` (`question_id`);

--
-- Indexes for table `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_question_survey` (`survey_id`);

--
-- Indexes for table `survey_submissions`
--
ALTER TABLE `survey_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_submission_per_survey` (`survey_id`,`resident_id`),
  ADD KEY `fk_submission_resident` (`resident_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `resident_children`
--
ALTER TABLE `resident_children`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `resident_parents`
--
ALTER TABLE `resident_parents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `resident_profile`
--
ALTER TABLE `resident_profile`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `resident_references`
--
ALTER TABLE `resident_references`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `resident_spouse`
--
ALTER TABLE `resident_spouse`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `staff_admin`
--
ALTER TABLE `staff_admin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `survey_answers`
--
ALTER TABLE `survey_answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `survey_questions`
--
ALTER TABLE `survey_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `survey_submissions`
--
ALTER TABLE `survey_submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

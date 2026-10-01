-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 02, 2026 at 08:38 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pconcio`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reset_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_expiration` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `password`, `reset_code`, `reset_expiration`) VALUES
(2, 'Admin', 'cristinaelegado13@gmail.com', 'ac', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `dentist_accounts`
--

CREATE TABLE `dentist_accounts` (
  `id` int UNSIGNED NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `birthday` date NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_accounts`
--

INSERT INTO `dentist_accounts` (`id`, `first_name`, `middle_name`, `last_name`, `birthday`, `address`, `phone`, `email`, `password_hash`, `is_active`, `created_at`) VALUES
(13, 'RUSSEL', 'NONE', 'FORMELOZA', '2004-05-25', '190 SAN PEDRO', '09234567986', 'russeljustine@gmail.com', '$2y$10$198FqW5ii7UI3vT/Srqr8.NrLjAe2.rtcXe3L5WEYzDHDjY8Aj1.K', 1, '2025-11-23 10:13:07'),
(14, 'CRISTINA', 'NONE', 'ELEGADO', '2004-08-16', '190 SAN PEDRO', '09958561917', 'cristinaelegado@gmail.com', '$2y$10$cAO8x92FCPIqSHBHUI3eh.VuXhYDjn.dnIK.Q6Tgrdpybs0BqPx1.', 1, '2025-11-23 10:39:46'),
(16, 'ALMIRA', 'NONE', 'LINAN', '2005-05-11', '39 CUASAY ST. ZONE 1 NORTH SIGNAL VILLAGE TAGUIG CITY', '09659573409', 'almiralinan@gmail.com', '$2y$10$3B5nVWiHGJorUble2VAT7Ow69C8cPla/rElO7fr9dw38xbpEq3QRy', 1, '2026-02-02 00:02:45'),
(17, 'ALEX', 'NONE', 'SAMAL', '2001-01-01', '130 SAN PEDRO', '09725345672', 'alex@gmail.com', '$2y$10$Lsgxa79pSCg9V553u3DetuNiJt96qtSh19CcGld.77GxGu0AxtK3m', 1, '2026-02-02 00:08:44'),
(20, 'SADSD', 'DSDSD', 'DSDSD', '2001-06-05', 'DDSDS', '09999999999', 'sample@gmail.com', '$2y$10$NEVNTZ8P1K.c04PlZczgPOdMbt4pk.O4GuHROfaRja2gJb5kusn8i', 1, '2026-02-22 05:24:14'),
(21, 'SAM', 'NONE', 'POL', '1999-12-22', 'DSD', '09549594609', 'd@gmail.com', '$2y$10$kNNfyriaVCVJQyVJSJ1TYeNEFnLveh4WURNYwJSvg/rMMutFznSxq', 1, '2026-02-22 05:53:12'),
(22, 'DDS', 'SDSD', 'DSD', '2003-03-04', 'SASAS', '09876755655', 'dsd@gmail.com', '$2y$10$BULjTiTOaQzVzokyXMDBW.6MRGKX7DnxksUrZpYm1MLK.qBGMf/Fu', 1, '2026-02-22 06:02:36'),
(23, 'ASAA', 'SASASAS', 'ASAS', '2002-03-04', 'SDSDSD', '09754332242', 'df@gmail.com', '$2y$10$NPQ6s3zIlLEYBCZbXMKT7.84KRqP535Qlt6yfu9mv9y.7vOXfT1B.', 1, '2026-02-22 06:03:53'),
(25, 'FARIS', 'NONE', 'ZACARIA', '2005-05-24', 'SAD', '09949465113', 'farishitomi@gmail.com', '$2y$10$Fji7fdzh5.hf0c6y39xC6OUuqye/3Fbgb0EWj.wHzeWGBZCWrA1Jm', 1, '2026-02-25 23:40:54');

-- --------------------------------------------------------

--
-- Table structure for table `dentist_schedule`
--

CREATE TABLE `dentist_schedule` (
  `id` int UNSIGNED NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `monday` tinyint(1) NOT NULL DEFAULT '0',
  `tuesday` tinyint(1) NOT NULL DEFAULT '0',
  `wednesday` tinyint(1) NOT NULL DEFAULT '0',
  `thursday` tinyint(1) NOT NULL DEFAULT '0',
  `friday` tinyint(1) NOT NULL DEFAULT '0',
  `saturday` tinyint(1) NOT NULL DEFAULT '0',
  `sunday` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_schedule`
--

INSERT INTO `dentist_schedule` (`id`, `dentist_id`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`) VALUES
(13, 13, 1, 0, 1, 0, 1, 0, 0),
(14, 14, 1, 1, 1, 1, 1, 0, 0),
(16, 16, 1, 1, 1, 0, 0, 0, 0),
(17, 17, 0, 0, 0, 0, 0, 1, 1),
(20, 20, 1, 0, 1, 0, 0, 0, 0),
(21, 21, 0, 1, 0, 1, 1, 0, 0),
(22, 22, 1, 0, 1, 0, 1, 0, 0),
(23, 23, 0, 0, 0, 0, 1, 0, 0),
(25, 25, 0, 0, 1, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `item_inventory`
--

CREATE TABLE `item_inventory` (
  `id` int NOT NULL,
  `item_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_type` enum('Medicine','Supply') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `price` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_inventory`
--

INSERT INTO `item_inventory` (`id`, `item_name`, `item_type`, `quantity`, `price`) VALUES
(206, 'COTTON', 'Supply', 15, 5.00),
(207, 'FACEMASK', 'Supply', -15, 5.00),
(208, 'BIOGESIC', 'Medicine', 45, 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `online_appointment`
--

CREATE TABLE `online_appointment` (
  `id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `age` int NOT NULL,
  `gender` enum('MALE','FEMALE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `gmail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_visit` date NOT NULL,
  `time_visit` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `status` enum('PENDING','CANCELLED','APPROVE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `online_appointment`
--

INSERT INTO `online_appointment` (`id`, `first_name`, `last_name`, `age`, `gender`, `gmail`, `phone_number`, `date_visit`, `time_visit`, `dentist_id`, `status`, `created_at`) VALUES
(89, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-02', '10:00 AM', 14, 'APPROVE', '2026-01-31 19:09:07'),
(92, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-17', '10:00 AM', 13, 'APPROVE', '2026-01-31 19:18:41'),
(93, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-17', '10:00 AM', 13, 'APPROVE', '2026-01-31 19:20:39'),
(94, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-24', '10:00 AM', 13, 'APPROVE', '2026-01-31 19:21:07'),
(95, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-02', '8:00 AM', 14, 'APPROVE', '2026-01-31 20:00:58'),
(96, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-05', '2:00 PM', 13, 'APPROVE', '2026-02-01 14:42:41'),
(97, 'Arryl vince', 'Marianio', 20, 'MALE', 'marianiarryl05@gmail.com', '09618292194', '2026-02-03', '8:00 AM', 13, 'APPROVE', '2026-02-01 23:51:42'),
(98, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-02-28', '12:00 PM', 17, 'APPROVE', '2026-02-26 00:13:49'),
(99, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-03-26', '10:00 AM', 14, 'APPROVE', '2026-02-26 00:23:37'),
(100, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113', '2026-03-26', '8:00 AM', 13, 'APPROVE', '2026-02-26 00:42:57'),
(101, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113', '2026-03-26', '8:00 AM', 13, 'APPROVE', '2026-02-26 00:47:37'),
(102, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-03-26', '12:00 PM', 14, 'APPROVE', '2026-02-26 01:01:05'),
(103, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113323', '2026-03-26', '2:00 PM', 14, 'PENDING', '2026-02-26 01:20:31'),
(104, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '0994946511', '2026-09-11', '8:00 AM', 23, 'PENDING', '2026-02-26 01:25:27'),
(105, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '099494651', '2026-03-03', '2:00 PM', 16, 'PENDING', '2026-02-26 01:28:57'),
(106, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465', '2026-03-03', '10:00 AM', 14, 'PENDING', '2026-02-26 01:31:17'),
(107, 'FARIS', '3434343', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465989', '2026-03-04', '2:00 PM', 25, 'APPROVE', '2026-02-26 01:40:42'),
(108, 'Christine', 'Gauma', 21, 'FEMALE', 'christinegauma@gmail.com', '09235756438', '2026-03-03', '8:00 AM', 13, 'PENDING', '2026-03-01 10:54:39'),
(109, 'Christian', 'Lirazan', 22, 'MALE', 'christianlirazan@gmail.com', '09554235145', '2026-03-02', '8:00 AM', 13, 'APPROVE', '2026-03-01 23:50:58'),
(110, 'Almira', 'Linan', 22, 'FEMALE', 'linanalmira@gmail.com', '09437463277', '2026-03-03', '8:00 AM', 14, 'PENDING', '2026-03-02 00:20:18');

-- --------------------------------------------------------

--
-- Table structure for table `online_appointment_services`
--

CREATE TABLE `online_appointment_services` (
  `id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `service_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `online_appointment_services`
--

INSERT INTO `online_appointment_services` (`id`, `appointment_id`, `service_id`) VALUES
(164, 89, 26),
(165, 89, 25),
(166, 89, 27),
(169, 92, 25),
(170, 93, 25),
(171, 94, 25),
(172, 95, 25),
(173, 95, 26),
(174, 96, 27),
(175, 97, 25),
(176, 98, 25),
(177, 99, 25),
(178, 100, 25),
(179, 101, 26),
(180, 102, 26),
(181, 103, 25),
(182, 104, 26),
(183, 105, 26),
(184, 106, 26),
(185, 107, 25),
(186, 108, 26),
(187, 108, 29),
(188, 109, 25),
(189, 109, 26),
(190, 110, 25),
(191, 110, 28);

-- --------------------------------------------------------

--
-- Table structure for table `patients_list`
--

CREATE TABLE `patients_list` (
  `id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `age` int NOT NULL,
  `gender` enum('MALE','FEMALE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_visit` date NOT NULL,
  `time_visit` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `type_of_appointment` enum('ONLINE','WALK-IN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('WAITING','ONGOING','TREATED','CANCELLED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'WAITING',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients_list`
--

INSERT INTO `patients_list` (`id`, `first_name`, `last_name`, `age`, `gender`, `email`, `phone_number`, `date_visit`, `time_visit`, `dentist_id`, `type_of_appointment`, `status`, `created_at`) VALUES
(22, 'CHRISTIAN', 'LIRAZAN', 21, 'MALE', 'christianlirazan@gmail.com', '09616990592', '2025-11-26', '10:00 AM', 13, 'WALK-IN', 'TREATED', '2025-11-23 10:28:32'),
(23, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-05', '2:00 PM', 13, 'ONLINE', 'WAITING', '2026-02-01 14:59:47'),
(24, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-02', '8:00 AM', 14, 'ONLINE', 'TREATED', '2026-02-01 15:12:05'),
(25, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-17', '10:00 AM', 13, 'ONLINE', 'TREATED', '2026-02-01 15:12:17'),
(26, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-24', '10:00 AM', 13, 'ONLINE', 'WAITING', '2026-02-01 23:48:13'),
(27, 'Arryl vince', 'Marianio', 20, 'MALE', 'marianiarryl05@gmail.com', '09618292194', '2026-02-03', '8:00 AM', 13, 'ONLINE', 'TREATED', '2026-02-17 06:39:53'),
(28, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-02', '10:00 AM', 14, 'ONLINE', 'ONGOING', '2026-02-17 06:40:16'),
(29, 'John david', 'Yumul', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '2026-02-17', '10:00 AM', 13, 'ONLINE', 'WAITING', '2026-02-17 07:15:26'),
(30, 'FARIS', 'ZACARIA', 43, 'MALE', 'farishitomi@gmail.com', '09949465113', '2026-02-23', '12:00 PM', 13, 'WALK-IN', 'WAITING', '2026-02-17 14:08:07'),
(31, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-02-28', '12:00 PM', 17, 'ONLINE', 'WAITING', '2026-02-26 00:17:38'),
(32, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-03-26', '10:00 AM', 14, 'ONLINE', 'WAITING', '2026-02-26 00:39:50'),
(33, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-03-26', '10:00 AM', 14, 'ONLINE', 'WAITING', '2026-02-26 00:41:45'),
(34, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113', '2026-03-26', '8:00 AM', 13, 'ONLINE', 'WAITING', '2026-02-26 00:43:17'),
(35, 'FARIS', 'ZACARIA', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113', '2026-03-26', '8:00 AM', 13, 'ONLINE', 'WAITING', '2026-02-26 00:48:40'),
(36, 'Test', 'Acount', 27, 'MALE', 'dump@gmail.com', '09348483483', '2026-03-26', '12:00 PM', 14, 'ONLINE', 'WAITING', '2026-02-26 01:04:25'),
(37, 'FARIS', '3434343', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465989', '2026-03-04', '2:00 PM', 25, 'ONLINE', 'CANCELLED', '2026-02-26 09:42:28'),
(38, 'Christian', 'Lirazan', 22, 'MALE', 'christianlirazan@gmail.com', '09554235145', '2026-03-02', '8:00 AM', 13, 'ONLINE', 'WAITING', '2026-03-02 00:17:04');

-- --------------------------------------------------------

--
-- Table structure for table `patient_account`
--

CREATE TABLE `patient_account` (
  `id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `birthday` date NOT NULL,
  `age` int NOT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `gmail` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_account`
--

INSERT INTO `patient_account` (`id`, `first_name`, `last_name`, `birthday`, `age`, `gender`, `gmail`, `phone_number`, `password`, `created_at`) VALUES
(16, 'Dhaniella', 'Ungsod', '2005-03-19', 20, 'FEMALE', 'celegado626@gmail.com', '09123456789', '$2y$10$EPjAWVP2Hg6EQmgA43Jkwu/j7QKpYmI/nI9I5jxwlsQZStTwG6l6W', '2025-11-19 15:48:33'),
(17, 'Alex', 'Samal', '2004-05-25', 21, 'MALE', 'alexsamal@gmail.com', '09876642637', '$2y$10$j.KJJSR82UZKLt1hUnaJd.46sF4HViungSTQvU1xbXP.UasTJQPhG', '2025-11-23 10:09:08'),
(18, 'BEANCH', 'CANONEGO', '2004-04-04', 21, 'MALE', 'beanchcanonego@gmail.com', '09616990592', '$2y$10$G7gLcRxAmxjDwDZxNPclDu/oXtu8GrZuuZeT6IRVB84ItMqZSEcpC', '2025-11-23 10:17:09'),
(20, 'Monaliza', 'Curan', '2004-12-08', 20, 'FEMALE', 'gaumacristina@gmail.com', '09616990592', '$2y$10$BkI/24IZ7tTCrVAqvPCk1Os0JUehR4dtOLcEwCH0lBqI6fDdzugxS', '2025-11-23 10:48:34'),
(22, 'John david', 'Yumul', '2004-04-07', 21, 'FEMALE', 'yumuljohndavid2@gmail.com', '09345678788', '$2y$10$FA4uiO5XvNIgREYcJ7yUHeNIkzfJdrNqUonfgZWCMIKBkgm0Lc2UG', '2026-01-31 19:07:15'),
(25, 'Arryl vince', 'Marianio', '2005-05-10', 20, 'MALE', 'marianiarryl05@gmail.com', '09618292194', '$2y$10$WdgGwUpBLUx9IC47l4b4Wuu18W4iDZuplfkpPvyaIK62.hWkLrPb6', '2026-02-01 23:43:33'),
(26, 'FARIS', 'ZACARIA', '2005-05-24', 20, 'MALE', 'fariszacaria2@gmail.com', '09949465113', '$2y$10$kATV1QqC3PfSElMqXR0B5uLn7VMbdBct7S5bSlsvmT/SpPBkY2EHC', '2026-02-22 06:35:26'),
(27, 'Test', 'Acount', '1999-02-12', 27, 'MALE', 'dump@gmail.com', '09348483483', '$2y$10$JHKRECD6FglKDG9zTyJiseHGSsA5Ti9k9tEDdBj7E9JdJk/8r/HEC', '2026-02-26 00:12:52'),
(28, 'Christine', 'Gauma', '2004-07-18', 21, 'FEMALE', 'christinegauma@gmail.com', '09235756438', '$2y$10$/E6zoM/YYygxMJQ5zhzqbe6ET8mI6U.j/kO/UGwFhdtWniIrgg5Ze', '2026-03-01 10:53:03'),
(29, 'Christian', 'Lirazan', '2004-03-01', 22, 'MALE', 'christianlirazan@gmail.com', '09554235145', '$2y$10$A6rUueh6QmcO4QHm2C0E8.6z2arNjG6nZeNtiWGiLqc5XLpWgA2wW', '2026-03-01 23:46:51'),
(30, 'Almira', 'Linan', '2004-01-01', 22, 'FEMALE', 'linanalmira@gmail.com', '09437463277', '$2y$10$qPj4hljrOE4jVsLtxou54ezLMvxkZbp7t1aK.jdgip2xXICH18ilO', '2026-03-02 00:18:57'),
(32, 'Kim', 'Takahashi', '2004-01-10', 22, 'MALE', 'christianjadelirazan@gmail.com', '09875823567', '$2y$10$JppUjbMS7FXSPi1yGX7nG.2pRW8ljh.KBpBmebnK5mCXMlFOLF16i', '2026-03-02 00:24:39');

-- --------------------------------------------------------

--
-- Table structure for table `patient_services`
--

CREATE TABLE `patient_services` (
  `id` int NOT NULL,
  `patient_id` int NOT NULL,
  `service_id` int NOT NULL,
  `status` enum('PENDING','ONGOING','DONE','CANCELLED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDING',
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_services`
--

INSERT INTO `patient_services` (`id`, `patient_id`, `service_id`, `status`, `date_start`, `date_end`) VALUES
(50, 22, 25, 'DONE', '2026-02-22 13:26:28', '2026-02-22 13:26:39'),
(51, 22, 26, 'DONE', '2026-03-02 09:09:29', '2026-03-02 09:10:11'),
(52, 23, 27, 'PENDING', NULL, NULL),
(53, 24, 25, 'CANCELLED', '2026-02-01 23:12:49', '2026-02-01 23:12:54'),
(54, 24, 26, 'DONE', '2026-02-22 13:27:20', '2026-02-22 13:27:48'),
(55, 25, 25, 'DONE', '2026-02-22 13:27:35', '2026-02-22 13:27:44'),
(56, 26, 25, 'PENDING', NULL, NULL),
(57, 27, 25, 'DONE', '2026-03-02 09:08:15', '2026-03-02 09:10:42'),
(58, 28, 26, 'ONGOING', '2026-03-02 09:08:39', NULL),
(59, 28, 25, 'ONGOING', '2026-03-02 09:11:23', NULL),
(60, 28, 27, 'ONGOING', '2026-03-02 09:11:26', NULL),
(61, 29, 25, 'PENDING', NULL, NULL),
(62, 30, 25, 'PENDING', NULL, NULL),
(63, 31, 25, 'PENDING', NULL, NULL),
(64, 32, 25, 'PENDING', NULL, NULL),
(65, 33, 25, 'PENDING', NULL, NULL),
(66, 34, 25, 'PENDING', NULL, NULL),
(67, 35, 26, 'PENDING', NULL, NULL),
(68, 36, 26, 'PENDING', NULL, NULL),
(69, 37, 25, 'CANCELLED', NULL, '2026-02-26 17:52:03'),
(70, 38, 25, 'PENDING', NULL, NULL),
(71, 38, 26, 'PENDING', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int NOT NULL,
  `service_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('enable','disable') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'enable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `price`, `status`) VALUES
(25, 'TEETH WHITENING', 3000.00, 'enable'),
(26, 'TOOTH EXTRACTION', 2500.00, 'enable'),
(27, 'DENTAL FILLING', 1000.00, 'enable'),
(28, 'ORTHODONTICS', 1300.00, 'enable'),
(29, 'GENERAL CHECK UP', 1000.00, 'enable'),
(30, 'DENTAL CLEANING', 1000.00, 'enable');

-- --------------------------------------------------------

--
-- Table structure for table `service_items`
--

CREATE TABLE `service_items` (
  `id` int NOT NULL,
  `service_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity_needed` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_items`
--

INSERT INTO `service_items` (`id`, `service_id`, `item_id`, `quantity_needed`) VALUES
(99, 25, 208, 1),
(101, 25, 207, 1),
(102, 25, 206, 1),
(103, 26, 207, 20),
(104, 26, 206, 10),
(106, 27, 206, 3),
(109, 28, 206, 3),
(110, 28, 207, 2),
(111, 29, 206, 5),
(112, 30, 207, 1),
(113, 30, 206, 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff_accounts`
--

CREATE TABLE `staff_accounts` (
  `id` int UNSIGNED NOT NULL,
  `staff_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `birthday` date NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_accounts`
--

INSERT INTO `staff_accounts` (`id`, `staff_id`, `first_name`, `middle_name`, `last_name`, `birthday`, `address`, `phone`, `email`, `password_hash`, `is_active`, `created_at`) VALUES
(5, 'PDS-0005', 'BEANCH', 'NONE', 'CANONEGO', '2005-04-19', '190 SAN PEDRO', '09096169905', 'elisessamantha@gmail.com', '$2y$10$oXh9knKJutVMYWxf0QNgFOlGYkgz7w9HDxC4RuuAMpgZwIg.NiwxG', 1, '2025-11-18 07:34:03'),
(6, 'PDS-0006', 'DANE', 'NONE', 'CAMMA', '2004-02-18', '190 SAN PEDRO', '74377438399', 'celegado626@gmail.com', '$2y$10$8WsOvpPnY.blj8KOKge3ceOTIkXgrGwccaqm2QFHMqEhR4joWabjq', 1, '2025-11-18 07:36:53'),
(8, 'PDS-0008', 'GUSION', 'NONE', 'LODICAKES', '2000-07-15', 'DDSD', '09234343434', 'faris2@gmail.com', '$2y$10$fHBmkjLioDED3QSkXCs/GeA.f7nwUdzg19jhLznys4DIAo9JZvgGi', 1, '2026-02-26 00:16:17');

-- --------------------------------------------------------

--
-- Table structure for table `staff_id_tracker`
--

CREATE TABLE `staff_id_tracker` (
  `id` int UNSIGNED NOT NULL,
  `last_number` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_id_tracker`
--

INSERT INTO `staff_id_tracker` (`id`, `last_number`, `created_at`) VALUES
(1, 8, '2025-11-06 22:59:02');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int NOT NULL,
  `time_slot` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `time_slot`, `is_active`) VALUES
(43, '8:00 AM', 1),
(44, '10:00 AM', 1),
(45, '12:00 PM', 1),
(46, '2:00 PM', 1),
(47, '5:30 AM', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_history`
--

CREATE TABLE `transaction_history` (
  `id` int NOT NULL,
  `patient_id` int DEFAULT NULL,
  `patient_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dentist_id` int DEFAULT NULL,
  `dentist_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `service_id` int DEFAULT NULL,
  `service_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `appointment_type` enum('ONLINE','WALK-IN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_completed` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_history`
--

INSERT INTO `transaction_history` (`id`, `patient_id`, `patient_name`, `dentist_id`, `dentist_name`, `service_id`, `service_name`, `price`, `appointment_type`, `date_completed`) VALUES
(23, NULL, 'Dhaniella Ungsod', 11, 'SAMMY MEISH', 26, 'TOOTH EXTRACTION', 2500.00, 'ONLINE', '2025-11-19 23:55:33'),
(24, NULL, 'Dhaniella Ungsod', 11, 'SAMMY MEISH', 25, 'TEETH WHITENING', 3000.00, 'ONLINE', '2025-11-19 23:55:57'),
(25, 22, 'CHRISTIAN LIRAZAN', 13, 'RUSSEL FORMELOZA', 25, 'TEETH WHITENING', 3000.00, 'WALK-IN', '2026-02-22 13:26:39'),
(26, 25, 'John david Yumul', 13, 'RUSSEL FORMELOZA', 25, 'TEETH WHITENING', 3000.00, 'ONLINE', '2026-02-22 13:27:44'),
(27, 24, 'John david Yumul', 14, 'CRISTINA ELEGADO', 26, 'TOOTH EXTRACTION', 2500.00, 'ONLINE', '2026-02-22 13:27:48'),
(28, 22, 'CHRISTIAN LIRAZAN', 13, 'RUSSEL FORMELOZA', 26, 'TOOTH EXTRACTION', 2500.00, 'WALK-IN', '2026-03-02 09:10:11'),
(29, 27, 'Arryl vince Marianio', 13, 'RUSSEL FORMELOZA', 25, 'TEETH WHITENING', 3000.00, 'ONLINE', '2026-03-02 09:10:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dentist_accounts`
--
ALTER TABLE `dentist_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `dentist_schedule`
--
ALTER TABLE `dentist_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dentist_id` (`dentist_id`);

--
-- Indexes for table `item_inventory`
--
ALTER TABLE `item_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `online_appointment`
--
ALTER TABLE `online_appointment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dentist_id` (`dentist_id`);

--
-- Indexes for table `online_appointment_services`
--
ALTER TABLE `online_appointment_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appointment_id` (`appointment_id`),
  ADD KEY `fk_service_id` (`service_id`);

--
-- Indexes for table `patients_list`
--
ALTER TABLE `patients_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_patientslist_dentist` (`dentist_id`);

--
-- Indexes for table `patient_account`
--
ALTER TABLE `patient_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gmail` (`gmail`);

--
-- Indexes for table `patient_services`
--
ALTER TABLE `patient_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_patientservices_patient` (`patient_id`),
  ADD KEY `fk_patientservices_service` (`service_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_items`
--
ALTER TABLE `service_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `staff_id_tracker`
--
ALTER TABLE `staff_id_tracker`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_trans_patient` (`patient_id`),
  ADD KEY `fk_trans_service` (`service_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dentist_accounts`
--
ALTER TABLE `dentist_accounts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `dentist_schedule`
--
ALTER TABLE `dentist_schedule`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `item_inventory`
--
ALTER TABLE `item_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `online_appointment`
--
ALTER TABLE `online_appointment`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `online_appointment_services`
--
ALTER TABLE `online_appointment_services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

--
-- AUTO_INCREMENT for table `patients_list`
--
ALTER TABLE `patients_list`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `patient_account`
--
ALTER TABLE `patient_account`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `patient_services`
--
ALTER TABLE `patient_services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `service_items`
--
ALTER TABLE `service_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `staff_id_tracker`
--
ALTER TABLE `staff_id_tracker`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `transaction_history`
--
ALTER TABLE `transaction_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dentist_schedule`
--
ALTER TABLE `dentist_schedule`
  ADD CONSTRAINT `dentist_schedule_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `online_appointment`
--
ALTER TABLE `online_appointment`
  ADD CONSTRAINT `fk_dentist_id` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `online_appointment_services`
--
ALTER TABLE `online_appointment_services`
  ADD CONSTRAINT `fk_appointment_id` FOREIGN KEY (`appointment_id`) REFERENCES `online_appointment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients_list`
--
ALTER TABLE `patients_list`
  ADD CONSTRAINT `fk_patientslist_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_services`
--
ALTER TABLE `patient_services`
  ADD CONSTRAINT `fk_patientservices_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patientservices_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_items`
--
ALTER TABLE `service_items`
  ADD CONSTRAINT `service_items_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item_inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD CONSTRAINT `fk_trans_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients_list` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trans_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

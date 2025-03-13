-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 13, 2025 at 09:00 PM
-- Server version: 8.0.41
-- PHP Version: 8.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tdawggcat_fa`
--

-- --------------------------------------------------------

--
-- Table structure for table `fa_index`
--

CREATE TABLE `fa_index` (
  `id` int NOT NULL,
  `word` varchar(100) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `page` varchar(10) DEFAULT NULL,
  `suffix` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_in_title` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fa_readings`
--

CREATE TABLE `fa_readings` (
  `page` varchar(10) NOT NULL,
  `date` varchar(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `reading` text NOT NULL,
  `today_i_will` text NOT NULL,
  `sort_key` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fa_users`
--

CREATE TABLE `fa_users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `admin` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fa_user_meetings`
--

CREATE TABLE `fa_user_meetings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `meeting_date` date NOT NULL,
  `title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fa_user_readings`
--

CREATE TABLE `fa_user_readings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `page` varchar(10) NOT NULL,
  `read_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `fa_index`
--
ALTER TABLE `fa_index`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_word_page` (`word`,`page`),
  ADD KEY `fa_index_ibfk_1` (`page`);

--
-- Indexes for table `fa_readings`
--
ALTER TABLE `fa_readings`
  ADD PRIMARY KEY (`page`),
  ADD UNIQUE KEY `sort_key` (`sort_key`);

--
-- Indexes for table `fa_users`
--
ALTER TABLE `fa_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `fa_user_meetings`
--
ALTER TABLE `fa_user_meetings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`meeting_date`);

--
-- Indexes for table `fa_user_readings`
--
ALTER TABLE `fa_user_readings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reading` (`user_id`,`page`,`read_date`),
  ADD KEY `page` (`page`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fa_index`
--
ALTER TABLE `fa_index`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fa_users`
--
ALTER TABLE `fa_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fa_user_meetings`
--
ALTER TABLE `fa_user_meetings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fa_user_readings`
--
ALTER TABLE `fa_user_readings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `fa_index`
--
ALTER TABLE `fa_index`
  ADD CONSTRAINT `fa_index_ibfk_1` FOREIGN KEY (`page`) REFERENCES `fa_readings` (`page`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `fa_user_meetings`
--
ALTER TABLE `fa_user_meetings`
  ADD CONSTRAINT `fa_user_meetings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `fa_users` (`id`);

--
-- Constraints for table `fa_user_readings`
--
ALTER TABLE `fa_user_readings`
  ADD CONSTRAINT `fa_user_readings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `fa_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fa_user_readings_ibfk_2` FOREIGN KEY (`page`) REFERENCES `fa_readings` (`page`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 27, 2024 at 07:30 AM
-- Server version: 8.2.0
-- PHP Version: 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `srms_makumbusho`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_announcements`
--

DROP TABLE IF EXISTS `tbl_announcements`;
CREATE TABLE IF NOT EXISTS `tbl_announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `announcement` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `create_date` datetime NOT NULL,
  `level` int NOT NULL COMMENT '0 = Teachers, 1 = Student, 2 = Both',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_classes`
--

DROP TABLE IF EXISTS `tbl_classes`;
CREATE TABLE IF NOT EXISTS `tbl_classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `registration_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(3, 'Form One 2024', '2024-03-18 12:41:05'),
(4, 'Form Two 2024', '2024-03-18 12:41:20'),
(5, 'Form Three (Science)  2024', '2024-03-18 12:42:31'),
(6, 'Form Three (Arts)  2024', '2024-03-18 12:42:41'),
(7, 'Form Four (Science)  2024', '2024-03-18 12:42:53'),
(8, 'Form four (Arts)  2024', '2024-03-18 12:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_division_system`
--

DROP TABLE IF EXISTS `tbl_division_system`;
CREATE TABLE IF NOT EXISTS `tbl_division_system` (
  `division` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `min` int NOT NULL,
  `max` int NOT NULL,
  `min_point` int NOT NULL,
  `max_point` int NOT NULL,
  `points` int NOT NULL,
  PRIMARY KEY (`division`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

('0', 0, 29, 34, 35, 5),
('1', 75, 100, 7, 17, 1),
('2', 65, 74, 18, 21, 2),
('3', 45, 64, 22, 25, 3),
('4', 30, 44, 26, 33, 4);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_results`
--

DROP TABLE IF EXISTS `tbl_exam_results`;
CREATE TABLE IF NOT EXISTS `tbl_exam_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `class` int NOT NULL,
  `subject_combination` int NOT NULL,
  `term` int NOT NULL,
  `score` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `student` (`student`),
  KEY `class` (`class`),
  KEY `subject_combination` (`subject_combination`),
  KEY `term` (`term`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(5, 'RG001', 3, 1, 1, 78),
(6, 'RG002', 3, 1, 1, 56),
(7, 'RG003', 3, 1, 1, 43),
(8, 'RG004', 3, 1, 1, 95),
(9, 'RG005', 3, 1, 1, 73),
(10, 'RG001', 3, 2, 1, 90),
(11, 'RG002', 3, 2, 1, 70),
(12, 'RG003', 3, 2, 1, 67),
(13, 'RG004', 3, 2, 1, 50),
(14, 'RG005', 3, 2, 1, 20),
(15, 'RG001', 3, 3, 1, 54),
(16, 'RG002', 3, 3, 1, 80),
(17, 'RG003', 3, 3, 1, 43),
(18, 'RG004', 3, 3, 1, 12),
(19, 'RG005', 3, 3, 1, 68),
(20, 'RG001', 3, 4, 1, 57),
(21, 'RG002', 3, 4, 1, 53),
(22, 'RG003', 3, 4, 1, 92),
(23, 'RG004', 3, 4, 1, 90),
(24, 'RG005', 3, 4, 1, 40),
(25, 'RG001', 3, 5, 1, 67),
(26, 'RG002', 3, 5, 1, 78),
(27, 'RG003', 3, 5, 1, 56),
(28, 'RG004', 3, 5, 1, 42),
(29, 'RG005', 3, 5, 1, 80),
(30, 'RG001', 3, 6, 1, 82),
(31, 'RG002', 3, 6, 1, 50),
(32, 'RG003', 3, 6, 1, 66),
(33, 'RG004', 3, 6, 1, 45),
(34, 'RG005', 3, 6, 1, 99),
(35, 'RG001', 3, 7, 1, 60),
(36, 'RG002', 3, 7, 1, 56),
(37, 'RG003', 3, 7, 1, 54),
(38, 'RG004', 3, 7, 1, 100),
(39, 'RG005', 3, 7, 1, 30),
(40, 'RG001', 3, 8, 1, 84),
(41, 'RG002', 3, 8, 1, 80),
(42, 'RG003', 3, 8, 1, 43),
(43, 'RG004', 3, 8, 1, 12),
(44, 'RG005', 3, 8, 1, 90),
(45, 'RG001', 3, 9, 1, 75),
(46, 'RG002', 3, 9, 1, 45),
(47, 'RG003', 3, 9, 1, 89),
(48, 'RG004', 3, 9, 1, 22),
(49, 'RG005', 3, 9, 1, 69),
(50, 'RG001', 3, 1, 2, 67),
(51, 'RG001', 3, 2, 2, 89),
(52, 'RG001', 3, 3, 2, 50),
(53, 'RG001', 3, 4, 2, 37),
(54, 'RG001', 3, 5, 2, 65),
(55, 'RG001', 3, 6, 2, 50),
(56, 'RG001', 3, 7, 2, 30),
(57, 'RG001', 3, 8, 2, 10),
(58, 'RG001', 3, 9, 2, 12),
(59, 'RG006', 4, 1, 1, 78),
(60, 'RG006', 4, 2, 1, 54),
(61, 'RG006', 4, 3, 1, 10),
(62, 'RG006', 4, 4, 1, 98),
(63, 'RG006', 4, 5, 1, 45),
(64, 'RG006', 4, 6, 1, 65),
(65, 'RG006', 4, 7, 1, 5),
(66, 'RG006', 4, 8, 1, 80),
(67, 'RG006', 4, 9, 1, 76);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_grade_system`
--

DROP TABLE IF EXISTS `tbl_grade_system`;
CREATE TABLE IF NOT EXISTS `tbl_grade_system` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `min` double NOT NULL,
  `max` double NOT NULL,
  `remark` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'A', 75, 100, 'Excellent'),
(2, 'B', 65, 74, 'Very Good'),
(3, 'C', 45, 64, 'Good'),
(4, 'D', 30, 44, 'Satisfactory'),
(5, 'F', 0, 29, 'Fail');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_sessions`
--

DROP TABLE IF EXISTS `tbl_login_sessions`;
CREATE TABLE IF NOT EXISTS `tbl_login_sessions` (
  `session_key` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `staff` int DEFAULT NULL,
  `student` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`session_key`),
  KEY `staff` (`staff`),
  KEY `student` (`student`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

('F0CR75URR0OZR8P5OVWR', NULL, 'RG001', '127.0.0.1');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_school`
--

DROP TABLE IF EXISTS `tbl_school`;
CREATE TABLE IF NOT EXISTS `tbl_school` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `logo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `result_system` int NOT NULL COMMENT '0 = Average, 1 = Division',
  `allow_results` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'ELIMU HUB', 'school_logo1711003619.png', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_smtp`
--

DROP TABLE IF EXISTS `tbl_smtp`;
CREATE TABLE IF NOT EXISTS `tbl_smtp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `server` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `port` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `encryption` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'smtp server here', 'smtp username here', 'smtp password here', '587', 'tls', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff`
--

DROP TABLE IF EXISTS `tbl_staff`;
CREATE TABLE IF NOT EXISTS `tbl_staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fname` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `lname` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `gender` varchar(6) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `level` int NOT NULL COMMENT '0 = Admin, 1 = Academic, 2 = Teacher',
  `status` int NOT NULL DEFAULT '1' COMMENT '0 = Blocked, 1 = Active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'Bwire', 'Mashauri', 'Male', 'bmashauri704@gmail.com', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 0, 1),
(3, 'ABDUL', 'SHABAN', 'Male', 'abdul@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(4, 'COLLINS', 'MPAGAMA', 'Male', 'collins@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(5, 'DAVID', 'OMAO', 'Male', 'david@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(6, 'DENIS', 'MWAMBUNGU', 'Male', 'denis@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(7, 'ERICK', 'LUOGA', 'Male', 'erick@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(8, 'FARAJI', 'FARAJI', 'Male', 'faraji@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(9, 'FATMA', 'BAHADAD', 'Female', 'fatma@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(10, 'FRANCIS', 'MASANJA', 'Male', 'francis@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(11, 'GLADNESS ', 'PHILIPO', 'Female', 'gladness@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(12, 'GRATION', 'GRATION', 'Male', 'gration@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(13, 'HANS', 'UISSO', 'Male', 'hans@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(14, 'HANSON', 'MAITA', 'Male', 'hanson@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(15, 'HENRY', 'GOWELLE', 'Male', 'henry@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(16, 'HILDA', 'KANDAUMA', 'Female', 'hilda@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(17, 'INNOCENT', 'MBAWALA', 'Male', 'innocent@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(18, 'JAMALI', 'NZOTA', 'Male', 'jamali@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(19, 'JAMIL', 'ABDALLAH', 'Male', 'jamil@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(20, 'JOAN', 'NKYA', 'Female', 'joan@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(21, 'JOSEPH', 'HAMISI', 'Male', 'joseph@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(23, 'Bwire', 'Mashauri', 'Male', 'bwiremunyweki@gmail.com', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_students`
--

DROP TABLE IF EXISTS `tbl_students`;
CREATE TABLE IF NOT EXISTS `tbl_students` (
  `id` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `fname` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `mname` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `lname` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `gender` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `class` int NOT NULL,
  `password` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `level` int NOT NULL DEFAULT '3' COMMENT '3 = student',
  `display_image` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Blank',
  `status` int NOT NULL DEFAULT '1' COMMENT '0 = Disabled, 1 = Enabled',
  PRIMARY KEY (`id`),
  KEY `class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

('RG001', 'OSWARD', 'JORAM', 'SEBATWALE', 'Male', 'oswardj@srms.test', 3, '$2y$10$XIE1yTXiRYWKrW7.e.OjGeYy.B9guq/sLh9rqu47YO1/QR1ZX93VW', 3, 'avator_1710936891.jpg', 1),
('RG002', 'PAULO', 'W', 'MOSHI', 'Male', 'paulow@srms.test', 3, '$2y$10$RN3P.rFnGYY2eZaLR6wOge8yJByl1Fjm3TbzCU0S/ZVBconH0fNo.', 3, 'avator_1710936905.jpg', 1),
('RG003', 'REHEMA', 'JAMES', 'MUSSA', 'Female', 'rehemaj@srms.test', 3, '$2y$10$sVdbGNtV2rUa6JvQE6xCOOtvXTxboEYRu7DZ4p/Iw7n3AZiJS/3Ly', 3, 'avator_1710936914.jpg', 1),
('RG004', 'TUMSIFU', 'ALFRED', 'KAMALA', 'Female', 'tumsifua@srms.test', 3, '$2y$10$fyfsYVo8oNrziA9QN9iMHuc5A8M8IGe6o/LSmmUmbBeNnhxNVIRl2', 3, 'avator_1710936926.jpg', 1),
('RG005', 'YUSTINO', 'EZRAEL', 'MBIGO', 'Male', 'yustinoe@srms.test', 3, '$2y$10$PtjCClDQa/ZbyB3fVeVUee2Z7PjEuTt8.haBL0um8qbuqHzwml0MS', 3, 'avator_1710936946.jpg', 1),
('RG006', 'ALICE', 'M', 'MUGISHA', 'Female', 'alice@srms.test', 4, '$2y$10$B1TJ31juU36kicOs2ULaw.tTTWmtcqhgJ2orYxHmjvM7pSfCWJr5C', 3, 'avator_1710936962.jpg', 1),
('RG007', 'ALLY', 'ZUBERI', 'ALLY', 'Male', 'ally@srzuberis.test', 4, '$2y$10$w/iWaMfiDJFOCcpeO0Ffu../ig07nvyFE8PgZl2GJ9.zslriWCyR2', 3, 'DEFAULT', 1),
('RG008', 'ASHRAF', 'NASSOR', 'SAID', 'Male', 'ashraf@srnassors.test', 4, '$2y$10$Rzeb6teMSYV5qEGl5eq9MOvUFrkZ.ZIbi6f1ciG2vCrYUnVFSk7gG', 3, 'DEFAULT', 1),
('RG009', 'BONIFACE', 'PONTIAN', 'MUTEGEYA', 'Male', 'boniface@srpontians.test', 4, '$2y$10$HwNLcEK4Ia8Valv5e/S4z.h9w0XwkZw6d59DvpORj9kuSB613Q1Mq', 3, 'DEFAULT', 1),
('RG010', 'BRIAN', 'ELIWAHA', 'TOMITE', 'Male', 'brian@sreliwahas.test', 4, '$2y$10$SsntilVGwPYs3SigHNijguNIXmGJ/IJAV/cI02U02ASJYdgUfeaf.', 3, 'DEFAULT', 1),
('RG011', 'CHARLES', 'THADEY', 'NDUVA', 'Male', 'charles@srthadeys.test', 4, '$2y$10$/oC2rBI/1kwYvDTFjGaut.DF3s15Tmmmt5vpZrNyDsruv2wvivr6m', 3, 'DEFAULT', 1),
('RG012', 'QUEEN ', 'JULIUS', 'BENJAMIN', 'Female', 'queen@srms.test', 5, '$2y$10$1BtsttroEwKx8Bs4Y.I36uTKJBopsjOStAco3l0JJbD3yoU.Zvs1y', 3, 'DEFAULT', 1),
('RG013', 'RAJABU', 'M', 'MILANZI', 'Male', 'rajabu@srms.test', 5, '$2y$10$jvfxswqLon3PcZvepUM7lOBTCZFShQidjysPvSo.6l/d.5.N2tpDC', 3, 'DEFAULT', 1),
('RG014', 'REHEMA', 'SILIVESTER', 'LEMABI', 'Female', 'rehema@srms.test', 5, '$2y$10$g7aNMemiSOf6j10Z9kGrEuM79Wx3j8Rs22d5bap5rnHvTtD/VRdp.', 3, 'DEFAULT', 1),
('RG015', 'SHAIBU', 'RASHIDI', 'MPONDA', 'Male', 'shaibu@srms.test', 5, '$2y$10$Ic.FhKjvB3dJJtiT0iUbHuRSr.i8qGEZQnDYNCdxEiybaWUjWtP5K', 3, 'DEFAULT', 1),
('RG016', 'UMMUKULTHUM ', 'BAKARI', 'PANGO', 'Female', 'ummukulthum@srms.test', 5, '$2y$10$PdFlObFsViSTLs.yJuxFWus7uF0.TUC16kbaVJGIBwFUbMVkDgTgC', 3, 'DEFAULT', 1),
('RG017', 'ANDREW', 'ISAAC', 'MABIKI', 'Male', 'andrew@srms.test', 6, '$2y$10$7aswGGxLgfkUbfYH/fwj/.WyFnm946Lxk90z9MIG7ZCLWi/k5bZ3.', 3, 'DEFAULT', 1),
('RG018', 'BRYSON', 'KHAMIS', 'MKHANY', 'Male', 'bryson@srms.test', 6, '$2y$10$XxHNLF6LUkq./WsMPKYP/emqMkIQ1aaH9GM/.967RgMpZzLi0a8Gy', 3, 'DEFAULT', 1),
('RG019', 'EMMNAUEL', 'EMMNAUEL', 'JOSEPH', 'Male', 'emmnauel@srms.test', 6, '$2y$10$t35jhCZhHAgJsDobC9gNFuOWvGc9xUPFrVTUBRc1.JT2nVWNzXCni', 3, 'DEFAULT', 1),
('RG020', 'FRANCO', 'FRANCO', 'MLAWA', 'Male', 'franco@srms.test', 6, '$2y$10$pqUi6rYnotgMLODlNRlSpu8rxUTJ.mtfcjUNVq/7mA5037uLFe5zO', 3, 'DEFAULT', 1),
('RG021', 'MICHAEL', 'GABRIEL', 'NDEKWA', 'Male', 'michael@srms.test', 6, '$2y$10$qsHSbSbQGceU8VGKlxptu.1GfwPCH9yoX7c1lKh5qdPGruIua9fTC', 3, 'DEFAULT', 1),
('RG022', 'NYEMO', 'WILFRED', 'SENHYINA', 'Male', 'nyemo@srms.test', 6, '$2y$10$rOhIasvPU8RW0WMqtVXxHOiLW2J66wpbE5NUd0lJBA9GbFb7nAogy', 3, 'DEFAULT', 1),
('RG023', 'RAMADHANI', 'JUMA', 'KIFUNTA', 'Male', 'ramadhani@srms.test', 6, '$2y$10$r81yFW5othiG5iGkKTh6GOOhmrSbhYG.B/Lhv3.hjH6hZ5tYV8E2m', 3, 'DEFAULT', 1),
('RG024', 'SAIDA', 'ABDULQADIR', 'MOHAMED', 'Female', 'saida@srms.test', 6, '$2y$10$Z3U9EU.ywJbQAjgtN2A5FO2Asw3qezCYhA7W6qvue4ehi2ePLeQQG', 3, 'DEFAULT', 1),
('RG025', 'SALMA', 'FADHIL', 'MGANGA', 'Female', 'salma@srms.test', 6, '$2y$10$IQjuYGKYXPlzgNwxow0Zd.0bZrbAZZ5ArEAUTCavfvBKPymGOOh7W', 3, 'DEFAULT', 1),
('RG026', 'LUCAS', 'J', 'MAIVAJI', 'Male', 'lucas@srms.test', 7, '$2y$10$yYIzQ6RtyI4xLzpAIBSg.uiJuAg6.T5jmvDQVytB2JPIUlwMtq7bi', 3, 'DEFAULT', 1),
('RG027', 'Mbarouk', 'Abdi', 'MBAROUK', 'Male', 'mbarouk@srms.test', 7, '$2y$10$UIcz5q8wbyCYnOFMSODjO.18PCG75DzY8yZDvtx2N4qqWMDLHdyna', 3, 'DEFAULT', 1),
('RG029', 'NATHAN', 'JORAM', 'MAHUNDI', 'Male', 'nathan@srms.test', 7, '$2y$10$fmyCT5/rEONcZZ7VwSvsqexW6sTMtaNU0/.UuseUxvMlUYb5bum9a', 3, 'DEFAULT', 1),
('RG030', 'PATRICK', 'STEVEN', 'MAPUNDA', 'Male', 'patrick@srms.test', 7, '$2y$10$mH3xVRY89bog.AXxBkZ.KedfwplTlmZzctwlMs2EfXcYQvZrGKIaO', 3, 'DEFAULT', 1),
('RG031', 'PHILIPO', 'A', 'KANYOKI', 'Male', 'philipo@srms.test', 7, '$2y$10$4QwWL4bPU.UBW7AkXIv7FO73zZBGkjpTWwOzEDaOU/dpqvXHyUgOC', 3, 'DEFAULT', 1),
('RG033', 'SADICK', 'SHARABII', 'KIBASA', 'Male', 'sadick@srms.test', 7, '$2y$10$bo3kC2paitvInM.7bmkNVeOsNJq2vDNzgQBunzh3pKtx2r389s0xS', 3, 'DEFAULT', 1),
('RG034', 'STEVEN', 'DAUD', 'DAUDI', 'Male', 'steven@srms.test', 7, '$2y$10$KoiNZztpY89BKf9eceJBJ.D3UEQ4Snd3DC67aIlhEG4H9lQFvGLKK', 3, 'DEFAULT', 1),
('RG035', 'TITUS', 'S', 'SIZIMWE', 'Male', 'titus@srms.test', 8, '$2y$10$1XfiDBvDZfDm9VWU9GtNmeO6H4nhPnhMBetS6zlxdi4E6ugm66HC6', 3, 'DEFAULT', 1),
('RG036', 'TUMAINIEL', 'JONA', 'MKONY', 'Male', 'tumainiel@srms.test', 8, '$2y$10$/312a8OcyO14EDLWmIA1vODEAwEMHdgyZ7R/3dx0CWSnQ0ijz6bkm', 3, 'DEFAULT', 1),
('RG037', 'Willfat', 'Hassan', 'SHAMS', 'Female', 'willfat@srms.test', 8, '$2y$10$WKDBlVj/lsuLqezdINtqQuxpRwgr8kZxof/tJjC6hgv06yE/58l46', 3, 'DEFAULT', 1),
('RG038', 'WILLIAM', 'MUJUNI', 'BALAILE', 'Male', 'william@srms.test', 8, '$2y$10$h30odqjWbQSkI3IKEDoR/u1EoBvYzzeoYl7LIgoPHD.jbpj4pyr62', 3, 'DEFAULT', 1),
('RG039', 'YOHANA', 'JACKSON', 'SIIMA', 'Male', 'yohana@srms.test', 8, '$2y$10$FLqe1Tf/71wHH51KbHqb1e5uqou4uzxKYfe.yhORo30kgK.2QXRlO', 3, 'DEFAULT', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subjects`
--

DROP TABLE IF EXISTS `tbl_subjects`;
CREATE TABLE IF NOT EXISTS `tbl_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(3, 'Mathematics'),
(4, 'English'),
(5, 'Kiswahili'),
(6, 'Geography'),
(7, 'History'),
(8, 'Civics'),
(9, 'Biology'),
(10, 'Physics'),
(11, 'Chemistry'),
(12, 'Literature'),
(15, 'Computer Studies');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject_combinations`
--

DROP TABLE IF EXISTS `tbl_subject_combinations`;
CREATE TABLE IF NOT EXISTS `tbl_subject_combinations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `subject` int NOT NULL,
  `teacher` int NOT NULL,
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `class` (`class`),
  KEY `teacher` (`teacher`),
  KEY `subject` (`subject`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 9, 3, '2024-03-18 13:45:03'),
(2, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 11, 4, '2024-03-18 14:01:12'),
(3, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 8, 5, '2024-03-18 14:01:28'),
(4, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 4, 6, '2024-03-18 14:02:09'),
(5, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 6, 7, '2024-03-18 14:02:29'),
(6, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 5, 13, '2024-03-18 14:03:00'),
(7, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 3, 9, '2024-03-18 14:03:15'),
(8, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 10, 11, '2024-03-18 14:03:47'),
(9, 'a:2:{i:0;s:1:\"3\";i:1;s:1:\"4\";}', 7, 16, '2024-03-18 14:04:12'),
(10, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 9, 3, '2024-03-18 14:06:29'),
(11, 'a:2:{i:0;s:1:\"5\";i:1;s:1:\"7\";}', 11, 20, '2024-03-18 14:07:07'),
(12, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 8, 17, '2024-03-18 14:07:31'),
(13, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 4, 13, '2024-03-18 14:07:57'),
(14, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 6, 7, '2024-03-18 14:08:22'),
(15, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 7, 5, '2024-03-18 14:09:03'),
(16, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 5, 21, '2024-03-18 14:10:00'),
(17, 'a:2:{i:0;s:1:\"6\";i:1;s:1:\"8\";}', 12, 6, '2024-03-18 14:10:40'),
(18, 'a:4:{i:0;s:1:\"5\";i:1;s:1:\"6\";i:2;s:1:\"7\";i:3;s:1:\"8\";}', 3, 10, '2024-03-18 14:11:01');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_terms`
--

DROP TABLE IF EXISTS `tbl_terms`;
CREATE TABLE IF NOT EXISTS `tbl_terms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(90) COLLATE utf8mb4_general_ci NOT NULL,
  `status` int NOT NULL DEFAULT '1' COMMENT '	0 = Disabled , 1 = Enabled',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--

(1, 'Midterm March 2024', 1),
(2, 'Terminal June 2024', 1),
(3, 'Midterm September 2024', 1),
(4, 'Annual November 2024', 1);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_exam_results`
--
ALTER TABLE `tbl_exam_results`
  ADD CONSTRAINT `tbl_exam_results_ibfk_1` FOREIGN KEY (`student`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_2` FOREIGN KEY (`class`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_3` FOREIGN KEY (`subject_combination`) REFERENCES `tbl_subject_combinations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_exam_results_ibfk_4` FOREIGN KEY (`term`) REFERENCES `tbl_terms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_login_sessions`
--
ALTER TABLE `tbl_login_sessions`
  ADD CONSTRAINT `tbl_login_sessions_ibfk_1` FOREIGN KEY (`staff`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_login_sessions_ibfk_2` FOREIGN KEY (`student`) REFERENCES `tbl_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_students`
--
ALTER TABLE `tbl_students`
  ADD CONSTRAINT `tbl_students_ibfk_1` FOREIGN KEY (`class`) REFERENCES `tbl_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_subject_combinations`
--
ALTER TABLE `tbl_subject_combinations`
  ADD CONSTRAINT `tbl_subject_combinations_ibfk_2` FOREIGN KEY (`subject`) REFERENCES `tbl_subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_subject_combinations_ibfk_3` FOREIGN KEY (`teacher`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

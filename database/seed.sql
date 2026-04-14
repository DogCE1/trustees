-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 11:38 AM
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
-- Database: `trustees_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `evidence` varchar(255) DEFAULT NULL,
  `status` enum('open','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disputes`
--

INSERT INTO `disputes` (`id`, `order_id`, `user_id`, `reason`, `evidence`, `status`, `created_at`) VALUES
(1, 2, 3, 'Item received does not match listing description. Shoes are size 8 not size 9 as listed.', 'uploads/disputes/evidence1.jpg', 'open', '2026-04-14 09:33:57'),
(2, 5, 5, 'Item never arrived at the Trustees location after 2 weeks.', 'uploads/disputes/evidence2.jpg', 'resolved', '2026-04-14 09:33:57');

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

DROP TABLE IF EXISTS `listings`;
CREATE TABLE `listings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `item_condition` enum('new','used','refurbished') DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','sold') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `user_id`, `title`, `description`, `price`, `category`, `item_condition`, `image`, `status`, `created_at`) VALUES
(2, 2, 'Samsung Galaxy A14', 'Barely used Samsung Galaxy A14. Comes with original charger and box. No scratches.', 1200.00, 'Electronics', 'used', 'uploads/listings/phone.jpg', 'verified', '2026-04-14 09:33:13'),
(3, 2, 'Nike Air Max 90', 'Size 9 Nike Air Max in great condition. Only worn a few times.', 850.00, 'Clothing', 'used', 'uploads/listings/shoes.jpg', 'verified', '2026-04-14 09:33:13'),
(4, 4, 'Office Chair', 'Comfortable black office chair with lumbar support. Height adjustable.', 600.00, 'Furniture', 'used', 'uploads/listings/chair.jpg', 'verified', '2026-04-14 09:33:13'),
(5, 4, 'Textbooks x3', 'Three second year IT textbooks. Still in good condition with minimal highlighting.', 320.00, 'Books', 'used', 'uploads/listings/books.jpg', 'verified', '2026-04-14 09:33:13'),
(6, 6, 'PS4 Controller', 'Original Sony PS4 controller. Works perfectly. Slight wear on joysticks.', 450.00, 'Electronics', 'used', 'uploads/listings/controller.jpg', 'verified', '2026-04-14 09:33:13'),
(7, 6, 'Leather Handbag', 'Brown genuine leather handbag. Spacious with multiple compartments.', 380.00, 'Clothing', 'used', 'uploads/listings/bag.jpg', 'verified', '2026-04-14 09:33:13'),
(8, 2, 'Mountain Bike', 'Adult mountain bike. 21 speed gears. Needs new tires but otherwise great.', 2500.00, 'Sports', 'used', 'uploads/listings/bike.jpg', 'pending', '2026-04-14 09:33:13'),
(9, 4, 'Microwave Oven', 'LG microwave 20L. Works perfectly. Selling because upgrading to bigger model.', 750.00, 'Furniture', 'used', 'uploads/listings/microwave.jpg', 'pending', '2026-04-14 09:33:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `delivery_method` enum('collect','delivery','meetup') DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `status` enum('received','inspecting','ready','delivered','cancelled') DEFAULT 'received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) DEFAULT 1,
  `total_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `buyer_id`, `listing_id`, `delivery_method`, `delivery_address`, `status`, `created_at`, `quantity`, `total_price`) VALUES
(1, 3, 1, 'collect', NULL, 'ready', '2026-04-14 09:33:23', 1, 1200.00),
(2, 3, 2, 'delivery', '45 Main Road, Soweto, 1804', 'inspecting', '2026-04-14 09:33:23', 1, 850.00),
(3, 5, 3, 'meetup', NULL, 'received', '2026-04-14 09:33:23', 1, 600.00),
(4, 3, 5, 'collect', NULL, 'delivered', '2026-04-14 09:33:23', 1, 450.00),
(5, 5, 6, 'delivery', '12 Church Street, Pretoria, 0002', 'cancelled', '2026-04-14 09:33:23', 1, 380.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phonenr` varchar(20) DEFAULT NULL,
  `role` enum('buyer','seller','admin') DEFAULT 'buyer',
  `is_verified` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `phonenr`, `role`, `is_verified`, `created_at`) VALUES
(6, 'Admin', 'Trustees', 'admin@trustees.co.za', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0821234567', 'admin', 1, '2026-04-14 09:33:00'),
(7, 'Thabo', 'Nkosi', 'thabo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0731234567', 'seller', 1, '2026-04-14 09:33:00'),
(8, 'Lerato', 'Dlamini', 'lerato@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0641234567', 'buyer', 1, '2026-04-14 09:33:00'),
(9, 'Sipho', 'Mokoena', 'sipho@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0611234567', 'seller', 1, '2026-04-14 09:33:00'),
(10, 'Ayanda', 'Zulu', 'ayanda@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0791234567', 'buyer', 0, '2026-04-14 09:33:00'),
(11, 'Nomvula', 'Khumalo', 'nomvula@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0821112233', 'seller', 1, '2026-04-14 09:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `verifications`
--

DROP TABLE IF EXISTS `verifications`;
CREATE TABLE `verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verifications`
--

INSERT INTO `verifications` (`id`, `user_id`, `id_document`, `status`, `submitted_at`) VALUES
(1, 2, 'uploads/verification/thabo_id.jpg', 'approved', '2026-04-14 09:33:47'),
(2, 3, 'uploads/verification/lerato_id.jpg', 'approved', '2026-04-14 09:33:47'),
(3, 4, 'uploads/verification/sipho_id.jpg', 'approved', '2026-04-14 09:33:47'),
(4, 5, 'uploads/verification/ayanda_id.jpg', 'pending', '2026-04-14 09:33:47'),
(5, 6, 'uploads/verification/nomvula_id.jpg', 'approved', '2026-04-14 09:33:47');

-- --------------------------------------------------------

--
-- Table structure for table `wallet`
--

DROP TABLE IF EXISTS `wallet`;
CREATE TABLE `wallet` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet`
--

INSERT INTO `wallet` (`id`, `user_id`, `balance`, `updated_at`) VALUES
(1, 1, 0.00, '2026-04-14 09:33:32'),
(2, 2, 3200.00, '2026-04-14 09:33:32'),
(3, 3, 500.00, '2026-04-14 09:33:32'),
(4, 4, 1800.00, '2026-04-14 09:33:32'),
(5, 5, 150.00, '2026-04-14 09:33:32'),
(6, 6, 950.00, '2026-04-14 09:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` enum('deposit','hold','release') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `amount`, `type`, `created_at`) VALUES
(1, 3, 500.00, 'deposit', '2026-04-14 09:33:41'),
(2, 3, 1200.00, 'deposit', '2026-04-14 09:33:41'),
(3, 3, 1200.00, 'hold', '2026-04-14 09:33:41'),
(4, 2, 1200.00, 'release', '2026-04-14 09:33:41'),
(5, 5, 150.00, 'deposit', '2026-04-14 09:33:41'),
(6, 4, 800.00, 'deposit', '2026-04-14 09:33:41'),
(7, 4, 800.00, 'release', '2026-04-14 09:33:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verifications`
--
ALTER TABLE `verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `verifications`
--
ALTER TABLE `verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `wallet`
--
ALTER TABLE `wallet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

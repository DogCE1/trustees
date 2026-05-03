-- Seed data for trustees_db
-- Import this after database/trustees_db.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `messages`;
DELETE FROM `login_attempts`;
DELETE FROM `wallet_transactions`;
DELETE FROM `disputes`;
DELETE FROM `orders`;
DELETE FROM `listing_images`;
DELETE FROM `notifications`;
DELETE FROM `verifications`;
DELETE FROM `wallet`;
DELETE FROM `listings`;
DELETE FROM `stores`;
DELETE FROM `users`;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `phonenr`, `role`, `is_verified`, `created_at`) VALUES
(1, 'Admin', 'Trustees', 'admin@trustees.co.za', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0821234567', 'admin', 1, '2026-04-20 08:00:00'),
(2, 'Thabo', 'Nkosi', 'thabo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0731234567', 'seller', 1, '2026-04-20 08:05:00'),
(3, 'Lerato', 'Dlamini', 'lerato@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0641234567', 'buyer', 1, '2026-04-20 08:10:00'),
(4, 'Sipho', 'Mokoena', 'sipho@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0611234567', 'seller', 1, '2026-04-20 08:15:00'),
(5, 'Ayanda', 'Zulu', 'ayanda@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0791234567', 'buyer', 0, '2026-04-20 08:20:00'),
(6, 'Nomvula', 'Khumalo', 'nomvula@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0821112233', 'seller', 1, '2026-04-20 08:25:00'),
(7, 'Kabelo', 'Mabena', 'kabelo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0720001111', 'buyer', 1, '2026-04-20 08:30:00'),
(8, 'Zanele', 'Naidoo', 'zanele@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0834445555', 'seller', 1, '2026-04-20 08:35:00'),
(9, 'Musa', 'Cele', 'musa@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0742223333', 'buyer', 1, '2026-04-20 08:40:00'),
(10, 'Refilwe', 'Molefe', 'refilwe@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0767778888', 'buyer', 0, '2026-04-20 08:45:00');

INSERT INTO `stores` (`id`, `name`, `address`, `latitude`, `longitude`, `created_at`) VALUES
(1, 'Sandton City Mall', 'Sandton Drive, Sandton, Johannesburg', -26.1076145, 28.0567017, '2026-04-20 09:00:00'),
(2, 'Mall of Africa', 'Magwa Crescent, Waterfall, Midrand', -25.9988460, 28.1080560, '2026-04-20 09:00:00'),
(3, 'Rosebank Mall', 'Cradock Avenue, Rosebank, Johannesburg', -26.1454260, 28.0407340, '2026-04-20 09:00:00'),
(4, 'Eastgate Shopping Centre', 'Bradford Road, Bedfordview, Johannesburg', -26.1838500, 28.1196700, '2026-04-20 09:00:00'),
(5, 'Menlyn Park Shopping Centre', 'Atterbury Road, Menlyn, Pretoria', -25.7826800, 28.2761500, '2026-04-20 09:00:00'),
(6, 'V&A Waterfront', 'Dock Road, Cape Town', -33.9028200, 18.4196700, '2026-04-20 09:00:00');

INSERT INTO `listings` (`id`, `user_id`, `title`, `description`, `price`, `category`, `item_condition`, `image`, `status`, `rejection_reason`, `created_at`) VALUES
(1, 2, 'Samsung Galaxy A14', 'Lightly used phone with charger and box. Battery health is excellent.', 1200.00, 'Electronics', 'good', 'Uploads/listings/phone_a14.jpg', 'verified', NULL, '2026-04-20 09:10:00'),
(2, 2, 'Nike Air Max 90', 'Size 9 sneakers, clean and comfortable. Worn less than ten times.', 850.00, 'Clothing', 'like_new', 'Uploads/listings/nike_airmax90.jpg', 'sold', NULL, '2026-04-20 09:12:00'),
(3, 4, 'Office Chair', 'Black ergonomic office chair with lumbar support and tilt lock.', 600.00, 'Furniture', 'good', 'Uploads/listings/office_chair.jpg', 'verified', NULL, '2026-04-20 09:15:00'),
(4, 4, 'IT Textbooks Bundle', 'Three second-year IT textbooks. Minor highlighting only.', 320.00, 'Books', 'good', 'Uploads/listings/it_textbooks.jpg', 'pending', NULL, '2026-04-20 09:17:00'),
(5, 6, 'PS4 Controller', 'Original Sony controller, tested and fully working.', 450.00, 'Electronics', 'fair', 'Uploads/listings/ps4_controller.jpg', 'verified', NULL, '2026-04-20 09:20:00'),
(6, 6, 'Leather Handbag', 'Brown handbag with visible stitching wear and a loose zip.', 380.00, 'Clothing', 'fair', 'Uploads/listings/leather_bag.jpg', 'rejected', 'Listing photos did not clearly show wear on zip area.', '2026-04-20 09:22:00'),
(7, 8, 'Mountain Bike 21 Speed', 'Adult bike with strong frame. Back tire needs replacement soon.', 2500.00, 'Sports', 'fair', 'Uploads/listings/mountain_bike.jpg', 'verified', NULL, '2026-04-20 09:25:00'),
(8, 8, 'Dell 24 inch Monitor', '1080p monitor with HDMI cable included.', 1400.00, 'Electronics', 'good', 'Uploads/listings/dell_monitor.jpg', 'pending', NULL, '2026-04-20 09:28:00'),
(9, 2, 'Microwave Oven 20L', 'Compact microwave, fully functional, clean interior.', 750.00, 'Other', 'good', 'Uploads/listings/microwave_20l.jpg', 'verified', NULL, '2026-04-20 09:30:00'),
(10, 4, 'Logitech Mechanical Keyboard', 'Blue switches, RGB lighting, includes detachable cable.', 950.00, 'Electronics', 'like_new', 'Uploads/listings/mech_keyboard.jpg', 'sold', NULL, '2026-04-20 09:35:00');

INSERT INTO `listing_images` (`id`, `listing_id`, `image`, `sort_order`, `created_at`) VALUES
(1, 1, 'Uploads/listings/phone_a14_1.jpg', 0, '2026-04-20 09:11:00'),
(2, 1, 'Uploads/listings/phone_a14_2.jpg', 1, '2026-04-20 09:11:00'),
(3, 2, 'Uploads/listings/nike_airmax90_1.jpg', 0, '2026-04-20 09:13:00'),
(4, 3, 'Uploads/listings/office_chair_1.jpg', 0, '2026-04-20 09:16:00'),
(5, 5, 'Uploads/listings/ps4_controller_1.jpg', 0, '2026-04-20 09:21:00'),
(6, 7, 'Uploads/listings/mountain_bike_1.jpg', 0, '2026-04-20 09:26:00'),
(7, 7, 'Uploads/listings/mountain_bike_2.jpg', 1, '2026-04-20 09:26:00'),
(8, 10, 'Uploads/listings/mech_keyboard_1.jpg', 0, '2026-04-20 09:36:00');

INSERT INTO `wallet` (`id`, `user_id`, `balance`, `updated_at`) VALUES
(1, 1, 0.00, '2026-04-20 10:00:00'),
(2, 2, 4200.00, '2026-04-24 12:00:00'),
(3, 3, 650.00, '2026-04-24 12:00:00'),
(4, 4, 2100.00, '2026-04-24 12:00:00'),
(5, 5, 150.00, '2026-04-24 12:00:00'),
(6, 6, 980.00, '2026-04-24 12:00:00'),
(7, 7, 1900.00, '2026-04-24 12:00:00'),
(8, 8, 1200.00, '2026-04-24 12:00:00'),
(9, 9, 500.00, '2026-04-24 12:00:00'),
(10, 10, 50.00, '2026-04-24 12:00:00');

INSERT INTO `orders` (`id`, `buyer_id`, `listing_id`, `delivery_method`, `delivery_address`, `meetup_store_id`, `delivery_proof_image`, `buyer_confirmed_meetup`, `seller_confirmed_meetup`, `status`, `created_at`, `quantity`, `unit_price_at_purchase`, `total_price`) VALUES
(1, 3, 2, 'meetup', NULL, 1, NULL, 1, 1, 'delivered', '2026-04-21 10:00:00', 1, 850.00, 850.00),
(2, 5, 3, 'delivery', '45 Main Road, Soweto, 1804', NULL, NULL, 0, 0, 'awaiting_proof', '2026-04-21 10:10:00', 1, 600.00, 600.00),
(3, 7, 5, 'collect', NULL, NULL, NULL, 0, 0, 'inspecting', '2026-04-21 10:20:00', 1, 450.00, 450.00),
(4, 9, 10, 'meetup', NULL, 3, NULL, 1, 1, 'disputed', '2026-04-21 10:30:00', 1, 950.00, 950.00),
(5, 3, 7, 'delivery', '12 Church Street, Pretoria, 0002', NULL, 'Uploads/orders/proof_order5.jpg', 0, 0, 'delivered', '2026-04-21 10:40:00', 1, 2500.00, 2500.00),
(6, 7, 9, 'meetup', NULL, 2, NULL, 0, 0, 'pending_admin_approval', '2026-04-21 10:50:00', 1, 750.00, 750.00);

INSERT INTO `wallet_transactions` (`id`, `user_id`, `order_id`, `amount`, `type`, `balance_after`, `created_at`) VALUES
(1, 3, NULL, 2000.00, 'deposit', 2000.00, '2026-04-21 08:30:00'),
(2, 3, 1, 850.00, 'hold', 1150.00, '2026-04-21 10:01:00'),
(3, 2, 1, 850.00, 'release', 4200.00, '2026-04-22 09:00:00'),
(4, 5, NULL, 600.00, 'deposit', 600.00, '2026-04-21 09:00:00'),
(5, 7, NULL, 2000.00, 'deposit', 2000.00, '2026-04-21 09:30:00'),
(6, 7, 3, 450.00, 'hold', 1550.00, '2026-04-21 10:21:00'),
(7, 9, NULL, 1200.00, 'deposit', 1200.00, '2026-04-21 09:45:00'),
(8, 9, 4, 950.00, 'hold', 250.00, '2026-04-21 10:31:00'),
(9, 9, 4, 950.00, 'refund', 1200.00, '2026-04-23 16:00:00'),
(10, 3, 5, 2500.00, 'hold', 650.00, '2026-04-21 10:41:00'),
(11, 8, 5, 2500.00, 'release', 1200.00, '2026-04-22 14:00:00');

INSERT INTO `verifications` (`id`, `user_id`, `id_document`, `selfie_photo`, `verification_video`, `full_name`, `id_number`, `address`, `status`, `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 2, 'Uploads/verification/thabo_id.jpg', 'Uploads/verification/thabo_selfie.jpg', 'Uploads/verification/thabo_video.mp4', 'Thabo Nkosi', '9201015800082', '32 Vilakazi Street, Soweto', 'approved', NULL, '2026-04-20 11:00:00', '2026-04-20 12:00:00', 1),
(2, 4, 'Uploads/verification/sipho_id.jpg', 'Uploads/verification/sipho_selfie.jpg', 'Uploads/verification/sipho_video.mp4', 'Sipho Mokoena', '9403155109084', '14 Church Road, Pretoria', 'approved', NULL, '2026-04-20 11:05:00', '2026-04-20 12:10:00', 1),
(3, 6, 'Uploads/verification/nomvula_id.jpg', 'Uploads/verification/nomvula_selfie.jpg', 'Uploads/verification/nomvula_video.mp4', 'Nomvula Khumalo', '9102210400083', '7 Ridge Lane, Johannesburg', 'approved', NULL, '2026-04-20 11:10:00', '2026-04-20 12:20:00', 1),
(4, 8, 'Uploads/verification/zanele_id.jpg', 'Uploads/verification/zanele_selfie.jpg', 'Uploads/verification/zanele_video.mp4', 'Zanele Naidoo', '9506020184089', '55 Palm Avenue, Durban', 'approved', NULL, '2026-04-20 11:15:00', '2026-04-20 12:30:00', 1),
(5, 5, 'Uploads/verification/ayanda_id.jpg', 'Uploads/verification/ayanda_selfie.jpg', 'Uploads/verification/ayanda_video.mp4', 'Ayanda Zulu', '0007075891088', '2 Sunrise Court, Midrand', 'pending', NULL, '2026-04-24 08:00:00', NULL, NULL),
(6, 10, 'Uploads/verification/refilwe_id.jpg', 'Uploads/verification/refilwe_selfie.jpg', 'Uploads/verification/refilwe_video.mp4', 'Refilwe Molefe', '0101015732081', '88 Oak Street, Alberton', 'rejected', 'ID photo is blurry. Please retake in good lighting.', '2026-04-24 08:30:00', '2026-04-24 09:15:00', 1);

INSERT INTO `notifications` (`id`, `user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'Your listing "Samsung Galaxy A14" has been verified.', '/ITECA-Website/Listings/my_listings.php', 1, '2026-04-20 12:05:00'),
(2, 3, 'Your order #1 is ready for meetup collection.', '/ITECA-Website/Orders/my_orders.php', 0, '2026-04-21 11:00:00'),
(3, 5, 'Please upload delivery proof for order #2.', '/ITECA-Website/Orders/upload_proof.php?order_id=2', 0, '2026-04-22 10:00:00'),
(4, 8, 'Payment for order #5 has been released to your wallet.', '/ITECA-Website/Profile/wallet.php', 0, '2026-04-22 14:05:00'),
(5, 9, 'Dispute opened for order #4. Admin review has started.', '/ITECA-Website/Orders/dispute.php?order_id=4', 0, '2026-04-23 15:00:00');

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `listing_id`, `body`, `is_read`, `created_at`) VALUES
(1, 3, 2, 2, 'Hi, is the Nike Air Max still available for meetup this afternoon?', 1, '2026-04-20 13:00:00'),
(2, 2, 3, 2, 'Yes, available. I can meet at Sandton City around 16:00.', 1, '2026-04-20 13:05:00'),
(3, 7, 6, 5, 'Can you confirm the PS4 controller has no drift?', 0, '2026-04-21 09:30:00'),
(4, 9, 4, 10, 'Keyboard arrived but one key feels stuck. Please advise.', 0, '2026-04-22 17:10:00');

INSERT INTO `disputes` (`id`, `order_id`, `user_id`, `reason`, `evidence`, `status`, `created_at`) VALUES
(1, 4, 9, 'Keyboard keycaps looked fine in listing photos, but key R is sticking and not responding consistently.', 'Uploads/disputes/dispute_order4.jpg', 'open', '2026-04-23 14:55:00');

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `success`, `attempted_at`) VALUES
(1, 'thabo@gmail.com', '127.0.0.1', 0, '2026-04-24 07:59:01'),
(2, 'thabo@gmail.com', '127.0.0.1', 0, '2026-04-24 07:59:20'),
(3, 'thabo@gmail.com', '127.0.0.1', 1, '2026-04-24 08:00:02'),
(4, 'ayanda@gmail.com', '127.0.0.1', 0, '2026-04-24 08:20:10'),
(5, 'ayanda@gmail.com', '127.0.0.1', 0, '2026-04-24 08:20:33'),
(6, 'admin@trustees.co.za', '127.0.0.1', 1, '2026-04-24 08:22:14');

ALTER TABLE `users` AUTO_INCREMENT = 11;
ALTER TABLE `stores` AUTO_INCREMENT = 7;
ALTER TABLE `listings` AUTO_INCREMENT = 11;
ALTER TABLE `listing_images` AUTO_INCREMENT = 9;
ALTER TABLE `wallet` AUTO_INCREMENT = 11;
ALTER TABLE `orders` AUTO_INCREMENT = 7;
ALTER TABLE `wallet_transactions` AUTO_INCREMENT = 12;
ALTER TABLE `verifications` AUTO_INCREMENT = 7;
ALTER TABLE `notifications` AUTO_INCREMENT = 6;
ALTER TABLE `messages` AUTO_INCREMENT = 5;
ALTER TABLE `disputes` AUTO_INCREMENT = 2;
ALTER TABLE `login_attempts` AUTO_INCREMENT = 7;

COMMIT;

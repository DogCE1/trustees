-- Migration: ensure AUTO_INCREMENT is set on every primary key, and add the
-- 'withdraw' transaction type. Run once on existing databases.
--
-- Root cause: some installs of trustees_db are missing AUTO_INCREMENT on
-- `orders.id`, which makes every INSERT default to id=0 and triggers
-- "Duplicate entry '0' for key 'PRIMARY'" on the second checkout.

ALTER TABLE `users`               MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `stores`              MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `listings`            MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `listing_images`      MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `orders`              MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `disputes`            MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `notifications`       MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `verifications`       MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `wallet`              MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `wallet_transactions` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `messages`            MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `login_attempts`      MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Bump counters above any seed/legacy ids so existing rows don't collide.
ALTER TABLE `orders`              AUTO_INCREMENT = 100;
ALTER TABLE `wallet_transactions` AUTO_INCREMENT = 100;
ALTER TABLE `notifications`       AUTO_INCREMENT = 100;
ALTER TABLE `disputes`            AUTO_INCREMENT = 100;
ALTER TABLE `messages`            AUTO_INCREMENT = 100;

-- Add 'withdraw' to wallet_transactions.type so users can withdraw from wallets.
ALTER TABLE `wallet_transactions`
  MODIFY `type` ENUM('deposit','hold','release','refund','withdraw') DEFAULT NULL;

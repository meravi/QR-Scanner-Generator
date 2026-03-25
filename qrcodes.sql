CREATE DATABASE IF NOT EXISTS `qrcode`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `qrcode`;

CREATE TABLE IF NOT EXISTS `qrcodes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `qrUsername` varchar(120) NOT NULL,
  `qrContent` varchar(2048) NOT NULL,
  `qrImg` varchar(255) NOT NULL,
  `qrlink` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qrImg` (`qrImg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

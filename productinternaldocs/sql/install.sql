CREATE TABLE IF NOT EXISTS `PREFIX_product_internal_document` (
    `id_document` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `storage_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` INT(11) UNSIGNED NOT NULL,
    `uploaded_at` DATETIME NOT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_document`),
    KEY `idx_product` (`id_product`),
    KEY `idx_active` (`is_active`),
    KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
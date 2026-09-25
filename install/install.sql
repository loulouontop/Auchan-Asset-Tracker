CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_equipmenttypes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `category` VARCHAR(20) NOT NULL DEFAULT 'A',
    `comment` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    `date_mod` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `name` (`name`),
    KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_manufacturers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `comment` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    `date_mod` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_containers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL DEFAULT '',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `locations_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    `date_mod` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `locations_id` (`locations_id`),
    KEY `is_active` (`is_active`),
    KEY `is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_equipments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `serial` VARCHAR(255) DEFAULT NULL,
    `model` VARCHAR(255) NOT NULL DEFAULT '',
    `itemtype` VARCHAR(100) NOT NULL DEFAULT 'Computer',
    `items_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `plugin_auchanassettracker_equipmenttypes_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `plugin_auchanassettracker_manufacturers_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `manufacturers_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(40) NOT NULL DEFAULT 'available',
    `locations_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `plugin_auchanassettracker_containers_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    `date_mod` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `serial_uniq` (`serial`),
    KEY `status` (`status`),
    KEY `locations_id` (`locations_id`),
    KEY `plugin_auchanassettracker_containers_id` (`plugin_auchanassettracker_containers_id`),
    KEY `is_deleted` (`is_deleted`),
    KEY `plugin_auchanassettracker_equipmenttypes_id` (`plugin_auchanassettracker_equipmenttypes_id`),
    KEY `itemtype_items` (`itemtype`, `items_id`),
    KEY `manufacturers_id` (`manufacturers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_auditlogs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `users_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `action` VARCHAR(100) NOT NULL DEFAULT '',
    `itemtype` VARCHAR(100) NOT NULL DEFAULT '',
    `items_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `details` TEXT DEFAULT NULL,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `users_id` (`users_id`),
    KEY `itemtype_items` (`itemtype`, `items_id`),
    KEY `date_creation` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `glpi_plugin_auchanassettracker_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profiles_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `role` VARCHAR(40) NOT NULL DEFAULT 'user',
    `locations_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `date_creation` TIMESTAMP NULL DEFAULT NULL,
    `date_mod` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `profiles_id` (`profiles_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

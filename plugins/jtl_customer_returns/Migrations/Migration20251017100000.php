<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Initial Migration - Erstellt alle RMA-Tabellen
 */
class Migration20251017100000 extends Migration implements IMigration
{
    /**
     * Migration ausführen
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Tabelle: rma (Haupttabelle)
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `rma` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `rmaNr` VARCHAR(50) NOT NULL COMMENT 'RMA-Nummer (z.B. RMA-2025-00123)',
                `orderID` BIGINT UNSIGNED NOT NULL COMMENT 'FK zu tbestellung.kBestellung',
                `customerID` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK zu tkunde.kKunde (NULL für Gäste)',
                `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=OPEN, 1=IN_PROGRESS, 2=ACCEPTED, 3=COMPLETED, 4=REJECTED',
                `totalGross` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Retourenwert brutto',
                `returnAddressID` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK zu return_address.id',
                `wawiID` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Wawi-RMA-ID nach Sync',
                `synced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=unsynchronisiert, 1=synchronisiert',
                `labelPath` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Pfad zum Retourenlabel-PDF',
                `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updateDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `lastSyncDate` DATETIME NULL DEFAULT NULL COMMENT 'Letzter Wawi-Sync',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_rmaNr` (`rmaNr`),
                INDEX `idx_orderID` (`orderID`),
                INDEX `idx_customerID` (`customerID`),
                INDEX `idx_status` (`status`),
                INDEX `idx_synced` (`synced`),
                INDEX `idx_createDate` (`createDate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='RMA (Return Merchandise Authorization) - Haupttabelle'"
        );
        
        // 2. Tabelle: rma_items (Retourenpositionen)
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `rma_items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `rmaID` BIGINT UNSIGNED NOT NULL COMMENT 'FK zu rma.id',
                `productID` INT UNSIGNED NOT NULL COMMENT 'FK zu tartikel.kArtikel',
                `variationID` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK zu teigenschaftwertwert (falls Variante)',
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Retournierte Menge',
                `reasonID` INT UNSIGNED NOT NULL COMMENT 'FK zu rma_reasons.id',
                `itemStatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=PENDING, 1=ACCEPTED, 2=REJECTED, 3=REFUNDED',
                `refundAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Erstattungsbetrag',
                `comment` TEXT NULL DEFAULT NULL COMMENT 'Kundenkommentar',
                `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_rmaID` (`rmaID`),
                INDEX `idx_productID` (`productID`),
                INDEX `idx_reasonID` (`reasonID`),
                CONSTRAINT `fk_rma_items_rma` 
                    FOREIGN KEY (`rmaID`) 
                    REFERENCES `rma` (`id`) 
                    ON DELETE CASCADE 
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='RMA-Positionen (Line Items)'"
        );
        
        // 3. Tabelle: rma_reasons (Retourengründe - mehrsprachig)
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `rma_reasons` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ISO` VARCHAR(3) NOT NULL DEFAULT 'GER' COMMENT 'Sprach-ISO (GER, ENG, etc.)',
                `reason` VARCHAR(255) NOT NULL COMMENT 'Retouren-Grund',
                `sortOrder` INT NOT NULL DEFAULT 0 COMMENT 'Sortierreihenfolge',
                `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktiv, 0=inaktiv',
                PRIMARY KEY (`id`),
                INDEX `idx_ISO` (`ISO`),
                INDEX `idx_active` (`active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Retourengründe (mehrsprachig)'"
        );
        
        // 4. Tabelle: return_address (Rücksendeadressen)
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `return_address` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customerID` INT UNSIGNED NOT NULL COMMENT 'FK zu tkunde.kKunde',
                `salutation` VARCHAR(10) NULL DEFAULT NULL COMMENT 'm/w/d',
                `firstName` VARCHAR(100) NOT NULL,
                `lastName` VARCHAR(100) NOT NULL,
                `street` VARCHAR(255) NOT NULL,
                `houseNumber` VARCHAR(20) NULL DEFAULT NULL,
                `zip` VARCHAR(10) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `country` VARCHAR(3) NOT NULL DEFAULT 'DE' COMMENT 'ISO-3166-1 Alpha-2',
                `phone` VARCHAR(50) NULL DEFAULT NULL,
                `createDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_customerID` (`customerID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Rücksendeadressen (Kundenkonten/Lieferadressen)'"
        );
        
        // 5. Tabelle: rma_history (Event-Sourcing Log)
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `rma_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `rmaID` BIGINT UNSIGNED NOT NULL COMMENT 'FK zu rma.id',
                `event` VARCHAR(50) NOT NULL COMMENT 'Event-Name (z.B. STATUS_CHANGED, WAWI_SYNC)',
                `eventData` JSON NULL DEFAULT NULL COMMENT 'Event-Daten (JSON)',
                `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `createdBy` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Benutzer-ID (NULL für System)',
                PRIMARY KEY (`id`),
                INDEX `idx_rmaID` (`rmaID`),
                INDEX `idx_event` (`event`),
                INDEX `idx_createdAt` (`createdAt`),
                CONSTRAINT `fk_rma_history_rma` 
                    FOREIGN KEY (`rmaID`) 
                    REFERENCES `rma` (`id`) 
                    ON DELETE CASCADE 
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='RMA-Historie (Event-Sourcing)'"
        );
        
        // HINWEIS: Retourengründe werden aus JTL-Wawi synchronisiert
        // Keine Default-Gründe nötig - Wawi ist Master-System
    }
    
    /**
     * Migration rückgängig machen (Rollback)
     *
     * @return void
     */
    public function down(): void
    {
        // Tabellen in umgekehrter Reihenfolge löschen (wegen Foreign Keys)
        $this->execute("DROP TABLE IF EXISTS `rma_history`");
        $this->execute("DROP TABLE IF EXISTS `return_address`");
        $this->execute("DROP TABLE IF EXISTS `rma_items`");
        $this->execute("DROP TABLE IF EXISTS `rma_reasons`");
        $this->execute("DROP TABLE IF EXISTS `rma`");
    }
}

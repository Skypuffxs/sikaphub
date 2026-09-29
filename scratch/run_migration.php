<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers/CSRF.php';
require_once __DIR__ . '/../config/Database.php';

$lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $_ENV[trim($parts[0])] = trim($parts[1]);
    }
}

try {
    $db = Database::getInstance()->getConnection();
    $cols = $db->query('SHOW COLUMNS FROM employers')->fetchAll(PDO::FETCH_COLUMN);
    
    $toAdd = [];
    if (!in_array('permit_file_path', $cols)) {
        $toAdd[] = "ADD COLUMN permit_file_path VARCHAR(255) NULL AFTER business_permit_file";
    }
    if (!in_array('verification_status', $cols)) {
        $toAdd[] = "ADD COLUMN verification_status ENUM('pending', 'green_flag', 'red_flag') NOT NULL DEFAULT 'pending' AFTER verified_status";
    }
    if (!in_array('ai_feedback', $cols)) {
        $toAdd[] = "ADD COLUMN ai_feedback TEXT NULL AFTER verification_status";
    }
    if (!in_array('extracted_permit_data', $cols)) {
        $toAdd[] = "ADD COLUMN extracted_permit_data LONGTEXT NULL AFTER ai_feedback";
    }
    if (!in_array('admin_decision', $cols)) {
        $toAdd[] = "ADD COLUMN admin_decision ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER extracted_permit_data";
    }

    if (!empty($toAdd)) {
        $sql = 'ALTER TABLE employers ' . implode(', ', $toAdd);
        $db->exec($sql);
        echo "SUCCESS: Migration complete. Added " . count($toAdd) . " columns to employers table.\n";
    } else {
        echo "NOTICE: Migration already applied. All columns exist on employers table.\n";
    }
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
}

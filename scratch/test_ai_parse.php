<?php
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'app/config/config.php';
require_once BASE_PATH . 'app/core/Database.php';
require_once BASE_PATH . 'app/services/AIEngineService.php';

try {
    $ai = new AIEngineService();
    $res = $ai->parseResumeFile(BASE_PATH . 'storage/uploads/resumes/39ad2b7f55c71dffba36e694301b142f.pdf');
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

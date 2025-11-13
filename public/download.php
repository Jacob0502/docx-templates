<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\Utils;
use App\TemplateManager;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Utils::jsonError(405, 'method not allowed', 'only_get');
    }

    $id = $_GET['id'] ?? '';
    if ($id === '') {
        Utils::jsonError(400, 'bad request', 'missing_id');
    }

    $tm = new TemplateManager($config);
    $gen = $tm->getGeneration($id);
    if (!$gen) {
        Utils::jsonError(404, 'not found', 'generation_not_found');
    }

    $stored = $gen['storedName'] ?? null;
    if (!$stored) {
        Utils::jsonError(404, 'not found', 'file_missing');
    }

    $filePath = Utils::joinPaths($config['output_dir'], $stored);
    if (!is_file($filePath)) {
        Utils::jsonError(404, 'not found', 'file_missing');
    }

    $fsize = filesize($filePath);
    $downloadName = $gen['downloadName'] ?? basename($filePath);

    Utils::sendStrictDownloadHeaders($downloadName, $fsize);

    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        Utils::jsonError(500, 'internal error', 'open_failed');
    }
    while (!feof($fh)) {
        echo fread($fh, 8192);
    }
    fclose($fh);
    exit;
} catch (\Throwable $e) {
    App\Utils::handleException($e);
}
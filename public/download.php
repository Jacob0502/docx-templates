<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\Auth;
use App\Utils;
use App\TemplateManager;

try {
    $auth = new Auth($config);
    if (!$auth->check()) Utils::jsonError(401,'unauthorized');

    // 仅允许通过“生成记录ID”访问
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || $id === '') {
        Utils::jsonError(400, 'bad request', 'missing_id');
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $id)) {
        Utils::jsonError(400, 'bad request', 'invalid_id');
    }

    $tm = new TemplateManager($config);
    $gen = $tm->getGeneration($id);
    if (!$gen) {
        Utils::jsonError(404, 'not found');
    }
    $storedName = $gen['storedName'] ?? null;
    if (!$storedName) {
        Utils::jsonError(404, 'not found');
    }

    $path = Utils::joinPaths($config['output_dir'], $storedName);
    if (!is_file($path)) {
        Utils::jsonError(404, 'not found');
    }

    $downloadName = $gen['downloadName'] ?? ('output_' . $id . '.docx');
    $size = filesize($path);
    if ($size === false) {
        Utils::logError("filesize failed");
        Utils::jsonError(500, 'internal error');
    }

    Utils::sendStrictDownloadHeaders($downloadName, (int)$size);

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        Utils::logError("fopen failed");
        Utils::jsonError(500, 'internal error');
    }
    while (!feof($fp)) {
        echo fread($fp, 8192);
    }
    fclose($fp);
    exit;
} catch (\Throwable $e) {
    App\Utils::handleException($e);
}
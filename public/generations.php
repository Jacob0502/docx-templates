<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\TemplateManager;
use App\Utils;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Utils::jsonError(405, 'method not allowed', 'only_post');
    }

    $tm = new TemplateManager($config);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') Utils::jsonError(400, 'bad request', 'missing_id');
        $ok = $tm->deleteGeneration($id, true);
        if (!$ok) Utils::jsonError(404, 'not found', 'delete_failed');
        Utils::jsonResponse(200, 'ok', ['id' => $id]);
    } elseif ($action === 'clear') {
        $removeFiles = (bool)($config['clear_generations_remove_files'] ?? true);
        $count = $tm->clearGenerations($removeFiles);
        Utils::jsonResponse(200, 'ok', ['removed' => $count]);
    } else {
        Utils::jsonError(400, 'bad request', 'unknown_action');
    }
} catch (\Throwable $e) {
    App\Utils::handleException($e);
}
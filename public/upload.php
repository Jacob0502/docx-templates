<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\Utils;
use App\TemplateManager;

// UI 模式：当 ui=1 时，用简单 HTML 文本返回“上传成功/失败”
$ui = isset($_REQUEST['ui']) && (string)$_REQUEST['ui'] === '1';

$respondHtml = function (int $code, string $text) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code($code);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' .
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8') .
        '</title></head><body><p>' .
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8') .
        '</p></body></html>';
    exit;
};

$fail = function (int $code, string $message, ?string $details = null) use ($ui, $respondHtml) {
    if ($ui) {
        // 页面表单场景：仅显示“上传失败”，不暴露内部细节
        $respondHtml($code, '上传失败');
    } else {
        // API 场景：输出统一 JSON 错误
        Utils::jsonError($code, $message, $details);
    }
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail(405, 'method not allowed', 'only_post');

    if (empty($_FILES['template'])) $fail(400, 'bad request', 'no_file');

    $file = $_FILES['template'];
    if ($file['error'] !== UPLOAD_ERR_OK) $fail(400, 'bad request', 'upload_error');

    $maxBytes = isset($config['max_upload_bytes']) ? (int)$config['max_upload_bytes'] : (10 * 1024 * 1024);
    if ($file['size'] > $maxBytes) $fail(400, 'bad request', 'file_too_large');

    // 原始文件名校验（仅允许 .docx）
    $origName = $file['name'];
    if ($err = Utils::validateDocxOriginalName($origName)) {
        $fail(400, 'bad request', 'invalid_filename');
    }

    // MIME 校验（finfo_file）
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMime = $config['allowed_mime'] ?? [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];
    if (!in_array($mime, $allowedMime, true)) {
        $fail(400, 'bad request', 'invalid_mime');
    }

    // 扩展名二次确认（仅 .docx）
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext !== 'docx') {
        $fail(400, 'bad request', 'invalid_extension');
    }

    // 统一重命名：使用 UUID 文件名，避免覆盖与路径穿越；仅保留 .docx 扩展名
    $stored = Utils::uuidV4() . '.docx';
    $dest = Utils::joinPaths($config['templates_dir'], $stored);
    $attempts = 0;
    while (file_exists($dest) && $attempts < 3) {
        $stored = Utils::uuidV4() . '.docx';
        $dest = Utils::joinPaths($config['templates_dir'], $stored);
        $attempts++;
    }

    if (!Utils::moveUploadedFileSafely($file['tmp_name'], $dest)) {
        Utils::logError('move_uploaded_file failed');
        $fail(500, 'internal error');
    }

    $tm = new TemplateManager($config);
    $record = $tm->addTemplate($origName, $stored, $file['size']);

    if ($ui) {
        // 页面表单模式：只显示“上传成功”
        $respondHtml(200, '上传成功');
    } else {
        // API：返回统一 JSON
        Utils::jsonResponse(200, 'ok', $record);
    }
} catch (\Throwable $e) {
    if ($ui) {
        App\Utils::logError('Upload exception: ' . $e->getMessage());
        $respondHtml(500, '上传失败');
    } else {
        App\Utils::handleException($e);
    }
}
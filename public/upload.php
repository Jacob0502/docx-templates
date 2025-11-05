<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use App\Utils;
use App\TemplateManager;
use App\Auth;

$auth = new Auth($config);
if (!$auth->check()) Utils::jsonResponse(401, 'unauthorized');

$tm = new TemplateManager($config);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Utils::jsonResponse(405,'only POST');

if (empty($_FILES['template'])) Utils::jsonResponse(400,'no file');

$file = $_FILES['template'];
if ($file['error'] !== UPLOAD_ERR_OK) Utils::jsonResponse(400,'upload error');

if ($file['size'] > $config['max_upload_bytes']) Utils::jsonResponse(400,'file too large');

// MIME check
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $config['allowed_mime'])) Utils::jsonResponse(400,'invalid mime');

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!in_array(strtolower($ext), $config['allowed_ext'])) Utils::jsonResponse(400,'invalid extension');

$safe = Utils::safeFilename(basename($file['name']));
$stored = uniqid('tpl_') . '_' . $safe;
$dest = $config['templates_dir'] . DIRECTORY_SEPARATOR . $stored;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Utils::logError('move_uploaded_file failed');
    Utils::jsonResponse(500,'save failed');
}

$record = $tm->addTemplate($file['name'], $stored, $file['size']);
Utils::jsonResponse(200,'ok', $record);
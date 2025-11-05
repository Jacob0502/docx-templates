<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use App\Auth;
use App\Utils;

$auth = new Auth($config);
if (!$auth->check()) Utils::jsonResponse(401,'unauthorized');

$file = $_GET['file'] ?? null;
if (!$file) Utils::jsonResponse(400,'no file');
$file = basename($file); // 防止目录穿越
$path = $config['output_dir'] . DIRECTORY_SEPARATOR . $file;
if (!file_exists($path)) Utils::jsonResponse(404,'not found');

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($file).'"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
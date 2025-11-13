<?php
// 避免 Warning/Deprecated 影响 JSON 响应
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\TemplateManager;
use App\Generator;
use App\Utils;

try {
    // 浏览器直开时重定向到单页 UI
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $ui = isset($_GET['ui']) && (string)$_GET['ui'] === '1';
    if ($ui || stripos($accept, 'text/html') !== false) {
        $tid = isset($_GET['template_id']) ? (string)$_GET['template_id'] : '';
        $to = '/public/templates.php' . ($tid !== '' ? ('?template_id=' . urlencode($tid)) : '');
        header('Location: ' . $to, true, 302);
        exit;
    }

    $tm = new TemplateManager($config);
    $gen = new Generator($config, $tm);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = $_GET['template_id'] ?? '';
        if ($id === '') {
            Utils::jsonError(400, 'bad request', 'missing_template_id');
        }
        try {
            $vars = $gen->getTemplateVariables($id);
            Utils::jsonResponse(200, 'ok', ['template_id' => $id, 'variables' => $vars]);
        } catch (\Throwable $e) {
            Utils::logError('Get variables error: ' . $e->getMessage());
            Utils::jsonError(400, 'failed', 'get_variables_failed');
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $templateId = $_POST['template_id'] ?? null;
        $data = $_POST['data'] ?? [];
        if (!$templateId) {
            Utils::jsonError(400, 'bad request', 'missing_template_id');
        }

        try {
            $options = [];
            if (isset($_POST['version']) && is_string($_POST['version']) && $_POST['version'] !== '') $options['version'] = $_POST['version'];
            if (isset($_POST['requestId']) && is_string($_POST['requestId']) && $_POST['requestId'] !== '') $options['requestId'] = $_POST['requestId'];
            if (isset($_POST['output']) && is_string($_POST['output']) && $_POST['output'] !== '') $options['output'] = $_POST['output'];
            if (isset($_POST['downloadName']) && is_string($_POST['downloadName']) && $_POST['downloadName'] !== '') $options['downloadName'] = $_POST['downloadName'];

            $res = $gen->generate($templateId, is_array($data) ? $data : [], $options);
            $downloadUrl = '/public/download.php?id=' . urlencode($res['generation']['id']);
            Utils::jsonResponse(200, 'ok', [
                'download' => $downloadUrl,
                'generation' => $res['generation'],
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $details = 'generate_exception';
            if (strpos($msg, 'Extra variables:') === 0) $details = 'extra_variables';
            if (strpos($msg, 'Template not found') !== false) $details = 'template_not_found';
            if (strpos($msg, 'Template file missing') !== false) $details = 'template_file_missing';
            if (strpos($msg, 'save failed') !== false) $details = 'save_failed';
            Utils::logError('Generate error: '.$msg);
            Utils::jsonError(400, 'generate failed', $details);
        }
        exit;
    }

    Utils::jsonError(405, 'method not allowed', 'only_get_post');
} catch (\Throwable $e) {
    App\Utils::handleException($e);
}
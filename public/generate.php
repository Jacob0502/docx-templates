<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use App\TemplateManager;
use App\Generator;
use App\Auth;
use App\Utils;

$auth = new Auth($config);
if (!$auth->check()) Utils::jsonResponse(401,'unauthorized');

$tm = new TemplateManager($config);
$gen = new \App\Generator($config, $tm);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
$id = $_GET['template_id'] ?? '';
$tpl = $tm->getTemplate($id);
if (!$tpl) die('模板不存在');
$vars = Generator::extractVariablesFromTemplateFile($config['templates_dir'].'/'. $tpl['stored_name']);
// 渲染简单表单
?><!doctype html><html><head><meta charset="utf-8"><title>生成文档</title></head><body>
<h2>生成：<?php echo htmlspecialchars($tpl['original_name']); ?></h2>
<form method="post">
    <input type="hidden" name="template_id" value="<?php echo $tpl['id']; ?>">
    <?php foreach($vars as $v): ?>
        <label><?php echo $v; ?>: <input name="data[<?php echo $v; ?>]"></label><br>
    <?php endforeach; ?>
    <button type="submit">生成并下载</button>
</form>
</body></html><?php
exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = $_POST['template_id'] ?? null;
    $data = $_POST['data'] ?? [];
    try {
        $res = $gen->generateFromTemplate($templateId, $data);
        // 返回下载链接
        $downloadUrl = '/public/download.php?file=' . urlencode(basename($res['path']));
        Utils::jsonResponse(200, 'ok', ['download'=>$downloadUrl, 'history'=>$res['history']]);
    } catch (Exception $e) {
        Utils::logError('Generate error: '.$e->getMessage());
        Utils::jsonResponse(400, 'generate failed: '.$e->getMessage());
    }
}
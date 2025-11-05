<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use App\TemplateManager;
use App\Auth;

$auth = new Auth($config);
if (!$auth->check()) {
    header('Location: /public/index.php'); exit;
}
$tm = new TemplateManager($config);
$templates = $tm->listTemplates();
$history = $tm->listHistory();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $id = $_POST['delete_template'];
    $tm->deleteTemplate($id);
    header('Location: /public/templates.php'); exit;
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>模板管理</title></head>
<body>
<h2>模板管理</h2>
<form action="/public/upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="ui" value="1">
    <input type="file" name="template" accept=".docx">
    <button type="submit">上传</button>
</form>
<hr>
<h3>已上传模板</h3>
<ul>
    <?php foreach($templates as $t): ?>
        <li><?php echo htmlspecialchars($t['original_name']); ?>
            - <form style="display:inline" method="post"><button name="delete_template" value="<?php echo $t['id'];?>">删除</button></form>
            - <a href="/public/generate.php?template_id=<?php echo $t['id']; ?>">生成</a>
        </li>
    <?php endforeach; ?>
</ul>
<hr>
<h3>生成历史</h3>
<ul>
    <?php foreach($history as $h): ?>
        <li><?php echo htmlspecialchars($h['template_name']).' -> '.htmlspecialchars($h['output_name']).' @ '.date('Y-m-d H:i:s',$h['created_at']); ?>
            - <a href="/public/download.php?file=<?php echo urlencode($h['output_name']); ?>">下载</a>
        </li>
    <?php endforeach; ?>
</ul>
</body></html>
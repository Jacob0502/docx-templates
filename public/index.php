<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use App\Auth;
$auth = new Auth($config);


// 简单登录页面
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    $u = $_POST['login_user'] ?? '';
    $p = $_POST['login_pass'] ?? '';
    if ($auth->login($u, $p)) {
        header('Location: /public/templates.php');
        exit;
    } else {
        $err = '登录失败';
    }
}


?><!doctype html>
<html>
<head><meta charset="utf-8"><title>模板系统 - 登录</title></head>
<body>
<h2>管理员登录</h2>
<?php if(!empty($err)) echo '<p style="color:red">'.htmlspecialchars($err).'</p>'; ?>
<form method="post" action="/">
    <input name="login_user" placeholder="用户名"><br>
    <input name="login_pass" type="password" placeholder="密码"><br>
    <button type="submit">登录</button>
</form>
</body>
</html>
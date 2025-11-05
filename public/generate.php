<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\TemplateManager;
use App\Generator;
use App\Auth;
use App\Utils;

$ui = isset($_REQUEST['ui']) && (string)$_REQUEST['ui'] === '1';

// 简易 HTML 响应
$renderPage = function (string $title, string $bodyHtml, int $code = 200) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,Apple Color Emoji,Segoe UI Emoji;color:#222;margin:20px;line-height:1.6}
    .container{max-width:800px;margin:0 auto}
    .card{border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:12px 0;background:#fff}
    label{display:block;margin:8px 0 4px}
    input[type=text],textarea,select{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px}
    button{padding:8px 14px;border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer}
    button.secondary{border-color:#64748b;background:#64748b}
    .row{display:flex;gap:8px;flex-wrap:wrap}
    .row > *{flex:1}
    .muted{color:#64748b}
    .ok{color:#16a34a}
    .err{color:#dc2626}
    .actions{display:flex;gap:8px;margin-top:12px}
    .msg{padding:8px 12px;border-radius:6px;background:#f1f5f9;margin:8px 0}
    </style>';
    echo '</head><body><div class="container">';
    echo $bodyHtml;
    echo '</div></body></html>';
    exit;
};

try {
    $auth = new Auth($config);
    if (!$auth->check()) {
        if ($ui) $renderPage('未授权', '<div class="card">未授权</div>', 401);
        Utils::jsonError(401,'unauthorized');
    }

    $tm = new TemplateManager($config);
    $gen = new Generator($config, $tm);

    // UI 模式：返回单页应用
    if ($ui) {
        $templates = $tm->listTemplates();
        $templateId = isset($_GET['template_id']) ? (string)$_GET['template_id'] : '';

        // 处理生成（POST）
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $templateId = $_POST['template_id'] ?? '';
            $data = $_POST['data'] ?? [];
            $version = isset($_POST['version']) ? (string)$_POST['version'] : 'v1';
            $downloadName = isset($_POST['downloadName']) ? (string)$_POST['downloadName'] : '';
            if (!$templateId) {
                $renderPage('生成失败', '<div class="card err">缺少 template_id</div>', 400);
            }
            try {
                $res = $gen->generate($templateId, is_array($data) ? $data : [], [
                    'version' => $version,
                    'downloadName' => $downloadName,
                ]);
                $downloadUrl = '/public/download.php?id=' . urlencode($res['generation']['id']);
                $body = '<h2>生成文档</h2>';
                $body .= '<div class="card ok">生成成功！</div>';
                $body .= '<div class="card"><div>下载链接：</div><p><a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($res["generation"]["downloadName"] ?: "下载文件", ENT_QUOTES, 'UTF-8') . '</a></p>';
                $body .= '<div class="muted">生成记录ID：' . htmlspecialchars($res["generation"]["id"], ENT_QUOTES, 'UTF-8') . '</div></div>';
                $body .= '<div class="actions"><a href="/public/generate.php?ui=1&template_id=' . urlencode($templateId) . '"><button class="secondary">继续生成本模板</button></a>';
                $body .= '<a href="/public/generate.php?ui=1"><button>返回模板选择</button></a></div>';
                $renderPage('生成成功', $body);
            } catch (\Throwable $e) {
                App\Utils::logError('Generate error (UI): '.$e->getMessage());
                $renderPage('生成失败', '<div class="card err">生成失败，请检查变量或稍后重试。</div>', 400);
            }
        }

        // GET：渲染单页（选择模板 + 变量表单）
        $vars = [];
        $tplInfo = null;
        if ($templateId !== '') {
            $tplInfo = $tm->getTemplate($templateId);
            if ($tplInfo) {
                try {
                    $vars = $gen->getTemplateVariables($templateId);
                } catch (\Throwable $e) {
                    App\Utils::logError('Get variables error (UI): '.$e->getMessage());
                    $vars = [];
                }
            }
        }

        ob_start();
        echo '<h2>生成文档</h2>';

        // 模板选择
        echo '<div class="card">';
        echo '<form method="get" action="/public/generate.php">';
        echo '<input type="hidden" name="ui" value="1">';
        echo '<label>选择模板</label>';
        echo '<div class="row">';
        echo '<select name="template_id">';
        echo '<option value="">-- 请选择模板 --</option>';
        foreach ($templates as $t) {
            $sel = ($t['id'] === $templateId) ? ' selected' : '';
            $text = $t['original_name'] . ' (' . $t['id'] . ')';
            echo '<option value="' . htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select>';
        echo '<button type="submit">加载变量</button>';
        echo '</div>';
        echo '<div class="muted">或从模板列表页跳转到本页（带 ?ui=1&template_id=...）。</div>';
        echo '</form>';
        echo '</div>';

        // 若已选定模板，展示变量表单
        if ($templateId && $tplInfo) {
            echo '<div class="card">';
            echo '<h3>填写变量</h3>';
            if (empty($vars)) {
                echo '<div class="msg muted">该模板未检测到变量，可直接生成。</div>';
            }
            echo '<form method="post" action="/public/generate.php?ui=1">';
            echo '<input type="hidden" name="template_id" value="' . htmlspecialchars($templateId, ENT_QUOTES, 'UTF-8') . '">';

            // 可选：版本号与建议下载名
            echo '<div class="row">';
            echo '<div><label>版本号（version）</label><input type="text" name="version" placeholder="例如 v1" value="v1"></div>';
            $suggest = pathinfo($tplInfo['original_name'] ?? 'output.docx', PATHINFO_FILENAME) . '.docx';
            echo '<div><label>下载文件名（可选）</label><input type="text" name="downloadName" placeholder="留空则使用系统命名" value="' . htmlspecialchars($suggest, ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '</div>';

            foreach ($vars as $v) {
                echo '<label>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</label>';
                echo '<input type="text" name="data[' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . ']" value="">';
            }

            echo '<div class="actions">';
            echo '<button type="button" class="secondary" id="btnDryRun">预检（不生成文件）</button>';
            echo '<button type="submit">生成并下载</button>';
            echo '</div>';

            echo '<div id="dryRunBox" class="msg" style="display:none"></div>';

            echo '</form>';
            echo '</div>';
            // JS：调用干跑接口显示校验结果
            echo '<script>
            (function(){
                var btn = document.getElementById("btnDryRun");
                if(!btn) return;
                btn.addEventListener("click", function(){
                    var form = btn.closest("form");
                    var fd = new FormData(form);
                    fd.append("dry_run","1");
                    fetch("/public/generate.php", { method:"POST", body:fd, credentials:"same-origin" })
                      .then(function(r){ return r.json(); })
                      .then(function(resp){
                          var box = document.getElementById("dryRunBox");
                          if(!box) return;
                          box.style.display = "block";
                          if(!resp || resp.code !== 200){
                              box.innerHTML = "<span class=\"err\">预检失败</span>";
                              return;
                          }
                          var rpt = resp.data && resp.data.report ? resp.data.report : null;
                          if(!rpt){ box.innerHTML = "<span class=\"err\">预检返回为空</span>"; return; }
                          var html = [];
                          if(rpt.missing && rpt.missing.length){
                              html.push("<div><b class=\"err\">缺失变量：</b> " + rpt.missing.join(", ") + "</div>");
                          } else {
                              html.push("<div><b class=\"ok\">缺失变量：</b> 无</div>");
                          }
                          if(rpt.extra && rpt.extra.length){
                              html.push("<div><b class=\"err\">多余变量：</b> " + rpt.extra.join(", ") + (rpt.strict_extra ? "（严格模式将报错）" : "") + "</div>");
                          } else {
                              html.push("<div><b class=\"ok\">多余变量：</b> 无</div>");
                          }
                          box.innerHTML = html.join("");
                      })
                      .catch(function(){ 
                          var box = document.getElementById("dryRunBox");
                          if(!box) return;
                          box.style.display = "block";
                          box.innerHTML = "<span class=\"err\">预检请求失败</span>";
                      });
                });
            })();
            </script>';
        }

        $renderPage('生成文档', ob_get_clean());
    }

    // API 模式：保持 JSON 行为（GET 返回变量列表，POST 干跑或生成）
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
        $dryRun = isset($_POST['dry_run']) && (string)$_POST['dry_run'] === '1';

        if (!$templateId) {
            Utils::jsonError(400, 'bad request', 'missing_template_id');
        }

        try {
            if ($dryRun) {
                $report = $gen->dryRun($templateId, is_array($data) ? $data : []);
                Utils::jsonResponse(200, 'ok', ['report' => $report]);
            } else {
                $options = [];
                if (isset($_POST['version']) && is_string($_POST['version'])) $options['version'] = $_POST['version'];
                if (isset($_POST['requestId']) && is_string($_POST['requestId'])) $options['requestId'] = $_POST['requestId'];
                if (isset($_POST['downloadName']) && is_string($_POST['downloadName'])) $options['downloadName'] = $_POST['downloadName'];

                $res = $gen->generate($templateId, is_array($data) ? $data : [], $options);
                $downloadUrl = '/public/download.php?id=' . urlencode($res['generation']['id']);
                Utils::jsonResponse(200, 'ok', [
                    'download' => $downloadUrl,
                    'generation' => $res['generation'],
                ]);
            }
        } catch (\Throwable $e) {
            Utils::logError('Generate error: '.$e->getMessage());
            Utils::jsonError(400, 'generate failed', 'generate_exception');
        }
        exit;
    }

    Utils::jsonError(405, 'method not allowed', 'only_get_post');
} catch (\Throwable $e) {
    if ($ui) {
        App\Utils::logError('Generate exception (UI root): '.$e->getMessage());
        // UI 模式兜底：用户可见错误不泄漏内部细节
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>出错了</title><p>服务开小差，请稍后重试。</p>';
        exit;
    }
    App\Utils::handleException($e);
}
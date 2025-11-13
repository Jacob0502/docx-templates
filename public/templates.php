<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

use App\TemplateManager;
// Auth 已移除：此页面不再做登录/鉴权检查

$tm = new TemplateManager($config);
$templates = $tm->listTemplates();
$generations = method_exists($tm, 'listGenerations') ? $tm->listGenerations() : $tm->listHistory();

$tplNameMap = [];
foreach ($templates as $t) $tplNameMap[$t['id']] = $t['original_name'] ?? $t['id'];

// AJAX 删除模板（不跳转）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_template']) || (isset($_POST['action']) && $_POST['action']==='delete_template'))) {
    $tplId = $_POST['delete_template'] ?? ($_POST['template_id'] ?? '');
    $ok = false;
    if ($tplId !== '') $ok = $tm->deleteTemplate($tplId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code'=>$ok?200:400,'message'=>$ok?'ok':'delete failed','data'=>['id'=>$tplId]], JSON_UNESCAPED_UNICODE);
    exit;
}
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>模板管理（单页）</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,Apple Color Emoji,Segoe UI Emoji;color:#222;margin:20px;line-height:1.6;background:#f8fafc}
        .container{max-width:1100px;margin:0 auto}
        h2,h3{margin:8px 0 16px}
        .card{border:1px solid #e5e7eb;border-radius:8px;padding:16px;background:#fff;margin-bottom:16px}
        label{display:block;margin:8px 0 4px}
        input[type=text],input[type=file],textarea,select{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;background:#fff}
        button{padding:8px 14px;border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer}
        button.secondary{border-color:#64748b;background:#64748b}
        button.danger{border-color:#dc2626;background:#dc2626}
        .muted{color:#64748b}
        .ok{color:#16a34a}
        .err{color:#dc2626}
        ul{padding-left:18px}
        li{margin:6px 0}
        .actions{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
        .msg{padding:8px 12px;border-radius:6px;background:#f1f5f9;margin:8px 0}
        .pill{display:inline-block;padding:2px 8px;border-radius:9999px;background:#f1f5f9;color:#475569;font-size:12px;margin-left:6px}
        .small{font-size:12px}
        .list-actions{display:inline-flex;gap:8px;margin-left:8px}
        .nowrap{white-space:nowrap}
        a.button-link{display:inline-block;padding:6px 10px;border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <h2>模板管理（单页）</h2>

    <!-- 1. 上传模板 -->
    <div class="card">
        <h3>上传模板</h3>
        <form id="uploadForm" method="post" action="/public/upload.php" enctype="multipart/form-data">
            <input type="file" name="template" accept=".docx" required>
            <div class="actions">
                <button type="submit">上传</button>
                <span id="uploadMsg" class="muted"></span>
            </div>
        </form>

        <hr>
        <h3>已上传模板</h3>
        <ul id="tplList">
            <?php foreach($templates as $t): ?>
                <li data-tpl-item="<?php echo htmlspecialchars($t['id']); ?>">
                    <b><?php echo htmlspecialchars($t['original_name']); ?></b>
                    <span class="pill small"><?php echo htmlspecialchars($t['id']); ?></span>
                    <span class="small muted">(<?php echo number_format((int)($t['size'] ?? 0)); ?> bytes)</span>
                    <div class="list-actions">
                        <button class="danger small" data-action="delete-template" data-tpl="<?php echo htmlspecialchars($t['id']); ?>">删除</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- 2. 生成文档 -->
    <div class="card">
        <h3>生成文档</h3>

        <div>
            <label>选择模板</label>
            <select id="genTemplate">
                <option value="">-- 请选择模板 --</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?php echo htmlspecialchars($t['id']); ?>">
                        <?php echo htmlspecialchars(($t['original_name'] ?? $t['id']) . ' (' . $t['id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-top:8px">
            <label>下载文件名（可选）</label>
            <input type="text" id="genDownloadName" placeholder="留空则使用系统命名">
        </div>

        <div id="varsBox" class="msg muted" style="margin-top:12px">请选择模板以加载占位符变量。</div>

        <div class="actions">
            <button id="btnGenerate" disabled>生成并获取下载链接（默认 PDF）</button>
            <span id="genMsg" class="muted" style="align-self:center;"></span>
        </div>
    </div>

    <!-- 3. 生成历史 -->
    <div class="card">
        <h3>生成历史</h3>
        <div class="actions">
            <button class="danger small" id="btnClearHistory">清空历史记录</button>
            <span class="muted small">（将删除数据库记录与已生成文件）</span>
        </div>
        <ul id="histList">
            <?php
            usort($generations, function($a,$b){
                $ta = isset($a['createdAt']) ? (int)$a['createdAt'] : (isset($a['created_at']) ? (int)$a['created_at'] : 0);
                $tb = isset($b['createdAt']) ? (int)$b['createdAt'] : (isset($b['created_at']) ? (int)$b['created_at'] : 0);
                return $tb <=> $ta;
            });
            foreach($generations as $g): ?>
                <li data-gen-item="<?php echo htmlspecialchars($g['id'] ?? ''); ?>">
                    <?php
                    $tplId = $g['templateId'] ?? ($g['template_id'] ?? '');
                    $tplLabel = $tplNameMap[$tplId] ?? $tplId;
                    $created = isset($g['createdAt']) ? (int)$g['createdAt'] : (isset($g['created_at']) ? (int)$g['created_at'] : time());
                    $downloadId = $g['id'] ?? '';
                    $downloadName = $g['downloadName'] ?? ($g['output_name'] ?? '下载文件');
                    ?>
                    <span><?php echo htmlspecialchars($tplLabel); ?></span>
                    <span class="muted small">→</span>
                    <span class="nowrap"><?php echo htmlspecialchars($downloadName); ?></span>
                    <span class="muted small"> @ <?php echo date('Y-m-d H:i:s', $created); ?></span>
                    <?php if ($downloadId): ?>
                        <a class="button-link small" href="/public/download.php?id=<?php echo urlencode($downloadId); ?>">下载</a>
                        <button class="danger small" data-action="delete-generation" data-id="<?php echo htmlspecialchars($downloadId); ?>">删除</button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
    (function(){
        function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]);});}
        function escapeAttr(s){ return escapeHtml(s).replace(/"/g, '&quot;'); }
        function pad(n){ return n<10 ? '0'+n : n; }

        // 上传
        var uploadForm = document.getElementById('uploadForm');
        var uploadMsg = document.getElementById('uploadMsg');
        var tplList = document.getElementById('tplList');
        var genTemplate = document.getElementById('genTemplate');

        if (uploadForm) {
            uploadForm.addEventListener('submit', function(ev){
                ev.preventDefault();
                uploadMsg.textContent = '上传中...';
                var fd = new FormData(uploadForm);
                fetch('/public/upload.php', { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                        if (resp && resp.code === 200 && resp.data) {
                            uploadMsg.textContent = '上传成功';
                            var t = resp.data;
                            // 列表新增
                            var li = document.createElement('li');
                            li.setAttribute('data-tpl-item', t.id);
                            li.innerHTML = '<b>' + escapeHtml(t.original_name) + '</b>' +
                                ' <span class="pill small">' + escapeHtml(t.id) + '</span>' +
                                ' <span class="small muted">(' + (t.size ? Number(t.size).toLocaleString() : '0') + ' bytes)</span>' +
                                ' <div class="list-actions">' +
                                '   <button class="danger small" data-action="delete-template" data-tpl="' + escapeAttr(t.id) + '">删除</button>' +
                                ' </div>';
                            tplList.insertBefore(li, tplList.firstChild);
                            // 下拉新增
                            var opt = document.createElement('option');
                            opt.value = t.id;
                            opt.textContent = (t.original_name || t.id) + ' (' + t.id + ')';
                            genTemplate.insertBefore(opt, genTemplate.options[1] || null);
                        } else {
                            uploadMsg.textContent = '上传失败';
                        }
                    })
                    .catch(function(){
                        uploadMsg.textContent = '上传失败';
                    });
            });
        }

        // 列表删除模板
        document.addEventListener('click', function(e){
            var btn = e.target.closest('[data-action="delete-template"]');
            if (!btn) return;
            var tpl = btn.getAttribute('data-tpl');
            if (!tpl) return;
            if (!confirm('确认删除该模板？')) return;
            var fd = new FormData();
            fd.append('delete_template', tpl);
            fetch('/public/templates.php', { method:'POST', body: fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp && resp.code === 200) {
                        var li = document.querySelector('li[data-tpl-item="'+ CSS.escape(tpl) +'"]');
                        if (li) li.remove();
                        var opt = Array.from(genTemplate.options).find(function(o){ return o.value === tpl; });
                        if (opt) opt.remove();
                        if (genTemplate.value === tpl) {
                            genTemplate.value = '';
                            varsBox.className = 'msg muted';
                            varsBox.innerHTML = '请选择模板以加载占位符变量。';
                            setActionsEnabled(false);
                            document.getElementById('genMsg').textContent='';
                        }
                    } else {
                        alert('删除失败');
                    }
                })
                .catch(function(){ alert('删除失败'); });
        });

        // 生成流程
        var varsBox = document.getElementById('varsBox');
        var btnGenerate = document.getElementById('btnGenerate');
        var genMsg = document.getElementById('genMsg');
        var genDownloadName = document.getElementById('genDownloadName');
        var histList = document.getElementById('histList');

        function setActionsEnabled(enabled) {
            btnGenerate.disabled = !enabled;
        }

        // 加载变量
        if (genTemplate) {
            genTemplate.addEventListener('change', function(){
                var id = genTemplate.value;
                genMsg.textContent = '';
                if (!id) {
                    varsBox.className = 'msg muted';
                    varsBox.innerHTML = '请选择模板以加载占位符变量。';
                    setActionsEnabled(false);
                    return;
                }
                varsBox.className = 'msg';
                varsBox.innerHTML = '加载变量中...';
                fetch('/public/generate.php?template_id=' + encodeURIComponent(id), { credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                        if (!resp || resp.code !== 200 || !resp.data || !Array.isArray(resp.data.variables)) {
                            varsBox.className = 'msg err';
                            varsBox.innerHTML = '加载变量失败';
                            setActionsEnabled(false);
                            return;
                        }
                        var vars = resp.data.variables;
                        if (vars.length === 0) {
                            varsBox.className = 'msg muted';
                            varsBox.innerHTML = '该模板未检测到变量，可直接生成。';
                        } else {
                            var html = [];
                            html.push('<div class="muted">填写以下占位符变量：</div>');
                            vars.forEach(function(v){
                                html.push('<label>' + escapeHtml(v) + '</label><input type="text" data-var name="data['+ escapeAttr(v) +']" value="">');
                            });
                            varsBox.className = '';
                            varsBox.innerHTML = html.join('');
                        }
                        setActionsEnabled(true);
                    })
                    .catch(function(){
                        varsBox.className = 'msg err';
                        varsBox.innerHTML = '加载变量失败';
                        setActionsEnabled(false);
                    });
            });
        }

        // 生成（默认 PDF）
        if (btnGenerate) {
            btnGenerate.addEventListener('click', function(){
                var id = genTemplate.value;
                if (!id) return;
                genMsg.textContent = '生成中...';
                var fd = new FormData();
                fd.append('template_id', id);
                if (genDownloadName && genDownloadName.value) fd.append('downloadName', genDownloadName.value);
                document.querySelectorAll('input[data-var]').forEach(function(inp){
                    fd.append(inp.name, inp.value);
                });
                fetch('/public/generate.php', { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                        if (!resp || resp.code !== 200) {
                            genMsg.textContent = '生成失败';
                            return;
                        }
                        var g = resp.data && resp.data.generation ? resp.data.generation : null;
                        var link = resp.data && resp.data.download ? resp.data.download : '';
                        if (!g || !link) {
                            genMsg.textContent = '生成结果不完整';
                            return;
                        }
                        var name = g.downloadName || '下载文件';
                        genMsg.innerHTML = '生成成功：<a href="' + escapeAttr(link) + '">' + escapeHtml(name) + '</a>';

                        // 历史列表追加
                        var li = document.createElement('li');
                        var created = g.createdAt ? new Date(g.createdAt * 1000) : new Date();
                        var timeStr = created.getFullYear() + '-' + pad(created.getMonth()+1) + '-' + pad(created.getDate()) + ' ' + pad(created.getHours()) + ':' + pad(created.getMinutes()) + ':' + pad(created.getSeconds());
                        var tplLabel = (document.querySelector('#genTemplate option:checked') || {}).textContent || (g.templateId || '');
                        li.setAttribute('data-gen-item', g.id);
                        li.innerHTML = '<span>' + escapeHtml(tplLabel) + '</span> <span class="muted small">→</span> ' +
                            '<span class="nowrap">' + escapeHtml(name) + '</span>' +
                            ' <span class="muted small"> @ ' + timeStr + '</span> ' +
                            '<a class="button-link small" href="' + escapeAttr(link) + '">下载</a> ' +
                            '<button class="danger small" data-action="delete-generation" data-id="' + escapeAttr(g.id) + '">删除</button>';
                        histList.insertBefore(li, histList.firstChild);
                    })
                    .catch(function(){
                        genMsg.textContent = '生成请求失败';
                    });
            });
        }

        // 清空历史（调用 /public/generations.php）
        var btnClearHistory = document.getElementById('btnClearHistory');
        var histList = document.getElementById('histList');
        if (btnClearHistory) {
            btnClearHistory.addEventListener('click', function(){
                if (!confirm('确认清空历史记录？')) return;
                var fd = new FormData();
                fd.append('action','clear');
                fetch('/public/generations.php', { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                        if (resp && resp.code === 200) {
                            histList.innerHTML = '';
                        } else {
                            alert('清空失败');
                        }
                    })
                    .catch(function(){ alert('清空失败'); });
            });
        }

        // 删除单条历史
        document.addEventListener('click', function(e){
            var btn = e.target.closest('[data-action="delete-generation"]');
            if (!btn) return;
            var id = btn.getAttribute('data-id');
            if (!id) return;
            if (!confirm('确认删除该记录？')) return;
            var fd = new FormData();
            fd.append('action','delete');
            fd.append('id', id);
            fetch('/public/generations.php', { method:'POST', body: fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp && resp.code === 200) {
                        var li = document.querySelector('li[data-gen-item="'+ CSS.escape(id) +'"]');
                        if (li) li.remove();
                    } else {
                        alert('删除失败');
                    }
                })
                .catch(function(){ alert('删除失败'); });
        });

        // 支持 URL 预选模板
        (function presetTemplateFromQuery(){
            var m = location.search.match(/[?&]template_id=([^&]+)/);
            if (!m) return;
            var t = decodeURIComponent(m[1]);
            var opt = Array.from(genTemplate.options).find(function(o){ return o.value === t; });
            if (!opt) return;
            genTemplate.value = t;
            var event = new Event('change');
            genTemplate.dispatchEvent(event);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })();
    })();
</script>
</body>
</html>
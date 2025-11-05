<?php
// config.php
return [
    'storage_dir' => __DIR__ . '/storage',
    'templates_dir' => __DIR__ . '/storage/templates',
    'output_dir' => __DIR__ . '/storage/output',
    'db_file' => __DIR__ . '/storage/db.json',


// 上传限制
    'max_upload_bytes' => 5 * 1024 * 1024, // 5MB
    'allowed_mime' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    'allowed_ext' => ['docx'],


// 会话
    'session_name' => 'DOCX_TMPL_SESS',
    'admin_user' => 'admin',
// 用于演示，请部署时改为强密码并存放到安全位置
    'admin_pass' => password_hash('123456', PASSWORD_DEFAULT),


// 日志文件
    'log_file' => __DIR__ . '/storage/error.log'
];
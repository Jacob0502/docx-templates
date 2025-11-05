<?php
// config.php
$root = __DIR__; // 项目根目录的绝对路径

return [
    // 目录与文件（全部使用绝对路径）
    'storage_dir'   => $root . '/storage',
    // 按你的需求：使用根目录下已存在的 templates 文件夹
    'templates_dir' => $root . '/storage/templates',
    'output_dir'    => $root . '/storage/output',
    'db_file'       => $root . '/storage/db.json',

    // 上传限制
    'max_upload_bytes' => 5 * 1024 * 1024, // 5MB（如需更大可调为 10*1024*1024）
    'allowed_mime' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // 某些环境下 docx 会被识别为 zip，这里做兼容
        'application/zip',
    ],
    'allowed_ext' => ['docx'],

    // 会话
    'session_name' => 'DOCX_TMPL_SESS',
    'admin_user' => 'admin',
    // 用于演示，请部署时改为强密码并存放到安全位置
    'admin_pass' => password_hash('123456', PASSWORD_DEFAULT),

    // 变量默认值与格式化器（供生成前干跑/真实生成使用）
    'variables' => [
        // 缺省值：当 payload 中缺少该变量时使用
        'defaults' => [
            // 'company' => 'ACME Inc.',
        ],
        // 格式化器：可传入 callable（示例见下）
        'formatters' => [
            // 'username' => function ($val) { return mb_strtoupper((string)$val); },
        ],
        // 是否严格禁止“多余变量”
        'strict_extra' => true,
    ],

    // 日志文件
    'log_file' => $root . '/storage/error.log',
];
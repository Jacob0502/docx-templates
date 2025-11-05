<?php
namespace App;


class Utils {
    public static function ensureDirs(array $dirs) {
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0750, true);
            }
        }
    }


    public static function safeFilename($name) {
// 保留字母数字、下划线、短横、点
        $name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
// 防止以点开头
        $name = ltrim($name, '.');
        return $name;
    }


    public static function logError($msg) {
        $cfg = include __DIR__ . '/../config.php';
        $t = date('Y-m-d H:i:s');
        file_put_contents($cfg['log_file'], "[$t] $msg\n", FILE_APPEND);
    }


    public static function jsonResponse($code, $message, $data = null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['code'=>$code,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
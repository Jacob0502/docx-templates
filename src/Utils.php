<?php
namespace App;

class Utils {
    private static $requestId = null;

    public static function ensureDirs(array $dirs) {
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0750, true);
            }
        }
    }

    // 兼容保留，建议不再将用户文件名直接用于存储
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
        $rid = self::getRequestId();
        file_put_contents($cfg['log_file'], "[$t] [req:$rid] $msg\n", FILE_APPEND);
    }

    public static function jsonResponse($code, $message, $data = null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['code'=>$code,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 统一错误响应：{code, message, details, requestId}
    public static function jsonError(int $httpCode, string $message, ?string $details = null): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
        $payload = [
            'code' => $httpCode,
            'message' => $message,
            'details' => $details,
            'requestId' => self::getRequestId(),
        ];
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 统一异常处理（隐藏内部细节，仅记录日志）
    public static function handleException(\Throwable $e, int $httpCode = 500, string $message = 'internal error', string $details = 'unexpected_error'): void {
        $summary = sprintf('%s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        self::logError($summary);
        self::jsonError($httpCode, $message, $details);
    }

    // 判断是否为绝对路径（支持 *nix、Windows、UNC）
    public static function isAbsolutePath(string $path): bool {
        if ($path === '') return false;
        // *nix: 以 / 开头
        if ($path[0] === '/') return true;
        // UNC: \\server\share
        if (strlen($path) >= 2 && $path[0] === '\\' && $path[1] === '\\') return true;
        // Windows 盘符: C:\ 或 C:/
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) return true;
        return false;
    }

    // 统一路径拼接（保留绝对路径，不再去掉前导分隔符）
    public static function joinPaths(string ...$segments): string {
        $result = '';
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === null) continue;
            $seg = (string)$seg;

            // 如果该段本身是绝对路径，则重置为该绝对路径开始
            if (self::isAbsolutePath($seg)) {
                $result = rtrim($seg, "\\/ \t\n\r\0\x0B");
                continue;
            }

            if ($result === '') {
                // 首段为相对路径：仅去掉尾部分隔符，保留可能的相对符号
                $result = rtrim($seg, "\\/ \t\n\r\0\x0B");
            } else {
                // 追加段：去掉两端分隔符后拼接
                $clean = trim($seg, "\\/ \t\n\r\0\x0B");
                $result .= DIRECTORY_SEPARATOR . $clean;
            }
        }
        return $result;
    }

    // 生成 UUID v4
    public static function uuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // 请求级 requestId（优先透传 X-Request-Id）
    public static function getRequestId(): string {
        if (self::$requestId !== null) return self::$requestId;

        $rid = null;
        if (isset($_SERVER['HTTP_X_REQUEST_ID']) && $_SERVER['HTTP_X_REQUEST_ID'] !== '') {
            $rid = $_SERVER['HTTP_X_REQUEST_ID'];
        } elseif (function_exists('getallheaders')) {
            $h = getallheaders();
            if ($h && isset($h['X-Request-Id']) && $h['X-Request-Id'] !== '') {
                $rid = $h['X-Request-Id'];
            }
        }
        if (is_string($rid)) {
            $rid = preg_replace('/[^A-Za-z0-9_.\-]/', '', $rid);
            $rid = substr($rid, 0, 64);
        }
        if (!$rid) {
            $rid = self::uuidV4();
        }
        self::$requestId = $rid;
        return self::$requestId;
    }

    // 校验原始名安全性与仅允许 .docx（拒绝隐藏扩展名/控制字符/以点开头/以点结尾等）
    public static function validateDocxOriginalName(string $name): ?string {
        if ($name === '') return 'empty filename';
        if (preg_match('/[\x00-\x1F]/', $name)) return 'invalid characters';
        if ($name !== trim($name)) return 'filename has leading or trailing spaces';
        $base = basename($name);
        if (strpos($base, '/') !== false || strpos($base, '\\') !== false) return 'invalid path separators';
        if ($base[0] === '.') return 'hidden filename not allowed';
        if (substr($base, -1) === '.') return 'filename cannot end with dot';
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if ($ext !== 'docx') return 'only .docx allowed';
        return null;
    }

    // 统一的安全移动上传文件（自动建目录）
    public static function moveUploadedFileSafely(string $tmpPath, string $destPath): bool {
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
                self::logError("mkdir failed: $dir");
                return false;
            }
        }
        return move_uploaded_file($tmpPath, $destPath);
    }

    // 严格下载 Header，防止浏览器尝试内联执行
    public static function sendStrictDownloadHeaders(string $downloadName, int $length): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
        if ($asciiName === '') $asciiName = 'download.docx';
        $utf8Name = rawurlencode($downloadName);

        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Referrer-Policy: no-referrer');
        header('X-XSS-Protection: 0');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . $utf8Name);
        header('Content-Length: ' . $length);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    // 读取 JSON（共享锁）
    public static function readJsonWithSharedLock(string $file, array $defaults = []): array {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            self::ensureDirs([$dir]);
        }
        $fh = fopen($file, 'c+');
        if ($fh === false) {
            return $defaults;
        }
        $data = $defaults;
        if (flock($fh, LOCK_SH)) {
            rewind($fh);
            $content = stream_get_contents($fh);
            if ($content !== false && $content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $data;
    }

    // 在独占锁下更新 JSON，并用“临时文件+rename”原子写回
    public static function updateJsonWithExclusiveLock(string $file, callable $updater, array $defaults = []): array {
        $dir = dirname($file);
        self::ensureDirs([$dir]);

        $fh = fopen($file, 'c+');
        if ($fh === false) {
            throw new \RuntimeException("cannot open db file");
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            throw new \RuntimeException("cannot acquire lock on db file");
        }

        rewind($fh);
        $content = stream_get_contents($fh);
        $db = $defaults;
        if ($content !== false && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $db = $decoded;
            }
        }

        $updater($db);

        $json = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new \RuntimeException('json encode failed');
        }

        $tmp = tempnam($dir, basename($file) . '.tmp.');
        if ($tmp === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new \RuntimeException('tempnam failed');
        }
        $tfh = fopen($tmp, 'wb');
        if ($tfh === false) {
            @unlink($tmp);
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new \RuntimeException('open temp file failed');
        }
        $written = fwrite($tfh, $json);
        if ($written === false || $written < strlen($json)) {
            fclose($tfh);
            @unlink($tmp);
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new \RuntimeException('write temp file failed');
        }
        fflush($tfh);
        fclose($tfh);

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new \RuntimeException('rename temp file failed');
        }

        flock($fh, LOCK_UN);
        fclose($fh);

        return $db;
    }
}
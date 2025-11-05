<?php

namespace App;

use PhpOffice\PhpWord\TemplateProcessor;

class Generator {
    protected $cfg;
    protected $tm;

    public function __construct(array $cfg, TemplateManager $tm) {
        $this->cfg = $cfg;
        $this->tm = $tm;
    }

    // 用 PHPWord 的 TemplateProcessor 提取占位符变量
    public function getTemplateVariables(string $templateId): array {
        $tpl = $this->tm->getTemplate($templateId);
        if (!$tpl) {
            throw new \Exception('Template not found');
        }
        $tplFile = Utils::joinPaths($this->cfg['templates_dir'], $tpl['stored_name']);
        if (!is_file($tplFile)) {
            throw new \Exception('Template file missing');
        }
        $processor = new TemplateProcessor($tplFile);
        $vars = $processor->getVariables(); // 返回占位符变量名（不含 ${}）
        // 去重、标准化
        $vars = array_values(array_unique(array_map('strval', (array)$vars)));
        return $vars;
    }

    // 构建规范化的存储文件名：{templateId}-{version}-{yyyyMMddHHmm}-{shortUuid}.docx
    protected function buildStoredName(string $templateId, string $version): string {
        // 约束 templateId/version 内容，避免路径或非法字符
        $safeTemplateId = preg_replace('/[^A-Za-z0-9_.-]/', '-', $templateId);
        $safeVersion = preg_replace('/[^A-Za-z0-9_.-]/', '-', $version === '' ? 'v1' : $version);

        $ts = date('YmdHi'); // 分钟精度
        // 8位短UUID（16进制）
        $shortUuid = substr(bin2hex(random_bytes(8)), 0, 8);

        return sprintf('%s-%s-%s-%s.docx', $safeTemplateId, $safeVersion, $ts, $shortUuid);
    }

    // 从配置加载缺省开关（保留以兼容第4步的严格多余变量控制）
    protected function loadStrictExtra(): bool {
        return (bool)($this->cfg['variables']['strict_extra'] ?? true);
    }

    // 应用默认值与格式化器（与第4步保持一致）
    protected function resolvePayload(array $vars, array $payload): array {
        $defaults = $this->cfg['variables']['defaults'] ?? [];
        $formatters = $this->cfg['variables']['formatters'] ?? [];

        $resolved = $payload;

        // 默认值（仅填充缺失变量）
        foreach ($vars as $v) {
            if (!array_key_exists($v, $resolved) && array_key_exists($v, $defaults)) {
                $resolved[$v] = $defaults[$v];
            }
        }

        // 格式化器
        foreach ($resolved as $k => $val) {
            if (isset($formatters[$k]) && is_callable($formatters[$k])) {
                try {
                    $resolved[$k] = call_user_func($formatters[$k], $val, $k, $resolved, $this->cfg);
                } catch (\Throwable $e) {
                    Utils::logError("formatter failed for {$k}: " . $e->getMessage());
                }
            }
        }

        // 将 null 统一为 ''
        foreach ($resolved as $k => $v) {
            if ($v === null) $resolved[$k] = '';
        }

        return $resolved;
    }

    // 干跑校验：仅返回报告
    public function dryRun(string $templateId, array $payload): array {
        $vars = $this->getTemplateVariables($templateId);
        $resolved = $this->resolvePayload($vars, $payload);
        $strictExtra = $this->loadStrictExtra();

        // 缺失（应用默认值后仍缺）
        $missing = [];
        foreach ($vars as $v) {
            if (!array_key_exists($v, $resolved)) {
                $missing[] = $v;
            }
        }

        // 多余
        $extra = [];
        foreach ($resolved as $k => $_) {
            if (!in_array($k, $vars, true)) {
                $extra[] = $k;
            }
        }

        return [
            'template_id' => $templateId,
            'variables' => $vars,
            'missing' => $missing,
            'extra' => $extra,
            'resolved' => $resolved,
            'strict_extra' => $strictExtra,
        ];
    }

    /**
     * 真实生成：通过校验后落地文件并写入“生成记录”
     *
     * @param string $templateId 模板ID
     * @param array  $payload    变量数据
     * @param array  $options    可选项:
     *   - version?: string 版本号（默认 'v1'）
     *   - requestId?: string 请求ID（默认生成 UUID）
     *   - downloadName?: string 下载建议名（默认与 storedName 相同）
     *
     * @return array{generation: array, path: string}
     */
    public function generate(string $templateId, array $payload, array $options = []): array {
        // 基础校验与变量解析
        $tpl = $this->tm->getTemplate($templateId);
        if (!$tpl) {
            throw new \Exception('Template not found');
        }
        $tplFile = Utils::joinPaths($this->cfg['templates_dir'], $tpl['stored_name']);
        if (!is_file($tplFile)) {
            throw new \Exception('Template file missing');
        }

        // 干跑校验
        $report = $this->dryRun($templateId, $payload);
        if (!empty($report['missing'])) {
            throw new \Exception('Missing variables: ' . implode(',', $report['missing']));
        }
        if ($report['strict_extra'] && !empty($report['extra'])) {
            throw new \Exception('Extra variables: ' . implode(',', $report['extra']));
        }

        // 统一 ID 与命名
        $version = isset($options['version']) && is_string($options['version']) && $options['version'] !== '' ? $options['version'] : 'v1';
        $storedName = $this->buildStoredName($templateId, $version);
        $outPath = Utils::joinPaths($this->cfg['output_dir'], $storedName);

        $requestId = isset($options['requestId']) && is_string($options['requestId']) && $options['requestId'] !== ''
            ? $options['requestId']
            : Utils::uuidV4();

        $downloadName = isset($options['downloadName']) && is_string($options['downloadName']) && $options['downloadName'] !== ''
            ? $options['downloadName']
            : $storedName;

        // 实际填充并保存
        $processor = new TemplateProcessor($tplFile);
        foreach ($report['resolved'] as $k => $v) {
            if (in_array($k, $report['variables'], true)) {
                $processor->setValue($k, $v);
            }
        }
        $processor->saveAs($outPath);

        // 生成记录（统一字段）
        $generationId = Utils::uuidV4();
        $generation = $this->tm->addGeneration([
            'id' => $generationId,
            'templateId' => $templateId,
            'version' => $version,
            'requestId' => $requestId,
            'status' => 'success',
            'error' => null,
            'storedName' => $storedName,
            'downloadName' => $downloadName,
            'createdAt' => time(),
        ]);

        return [
            'generation' => $generation,
            'path' => $outPath,
        ];
    }
}
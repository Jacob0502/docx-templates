<?php

namespace App;

use ZipArchive;
use PhpOffice\PhpWord\TemplateProcessor;

class Generator {
    protected $cfg;
    protected $tm;


    public function __construct(array $cfg, TemplateManager $tm) {
        $this->cfg = $cfg;
        $this->tm = $tm;
    }


// 从 docx 原始二进制读取文本变量 (简单解析占位符)
    public static function extractVariablesFromTemplateFile($filePath) {
// docx 本质为 zip，变量通常出现在 word/document.xml
        $vars = [];
        if (!file_exists($filePath)) return $vars;
        $zip = new \ZipArchive();
        if ($zip->open($filePath) === true) {
            $idx = $zip->locateName('word/document.xml');
            if ($idx !== false) {
                $xml = $zip->getFromIndex($idx);
// 支持三种格式：{{var}} , ${var} , [var]
                preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $xml, $m1);
                preg_match_all('/\$\{\s*([a-zA-Z0-9_]+)\s*\}/', $xml, $m2);
                preg_match_all('/\[\s*([a-zA-Z0-9_]+)\s*\]/', $xml, $m3);
                $all = array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []);
                $all = array_unique($all);
                $vars = array_values($all);
            }
            $zip->close();
        }
        return $vars;
    }


    public function generateFromTemplate($templateId, array $data, $outputFilename = null) {
        $tpl = $this->tm->getTemplate($templateId);
        if (!$tpl) throw new \Exception('Template not found');
        $tplFile = $this->cfg['templates_dir'] . DIRECTORY_SEPARATOR . $tpl['stored_name'];
        if (!file_exists($tplFile)) throw new \Exception('Template file missing');


// 验证数据完整性
        $vars = self::extractVariablesFromTemplateFile($tplFile);
        $missing = [];
        foreach ($vars as $v) {
            if (!array_key_exists($v, $data)) $missing[] = $v;
        }
        if (!empty($missing)) {
            throw new \Exception('Missing variables: ' . implode(',', $missing));
        }


// 使用 PHPWord 模板填值
        $processor = new TemplateProcessor($tplFile);
        foreach ($data as $k => $v) {
// 强制把 null 转成空字符串
            $processor->setValue($k, $v === null ? '' : $v);
        }


        if (!$outputFilename) {
            $outputFilename = sprintf('%s_%s.docx', pathinfo($tpl['original_name'], PATHINFO_FILENAME), date('Ymd_His'));
        }
        $safe = Utils::safeFilename($outputFilename);
        $outPath = $this->cfg['output_dir'] . DIRECTORY_SEPARATOR . $safe;
        $processor->saveAs($outPath);


// 记录历史
        $history = $this->tm->addHistory([
            'template_id' => $templateId,
            'template_name' => $tpl['original_name'],
            'output_name' => $safe,
            'data' => $data
        ]);


        return ['path'=>$outPath, 'history'=>$history];
    }
}
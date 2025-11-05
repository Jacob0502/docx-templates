<?php
namespace App;

class TemplateManager {
    protected $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        Utils::ensureDirs([$cfg['storage_dir'], $cfg['templates_dir'], $cfg['output_dir']]);

        // 初始化/迁移到新结构：templates + generations
        $defaults = ['templates' => [], 'generations' => []];
        Utils::updateJsonWithExclusiveLock($cfg['db_file'], function (&$db) use ($defaults) {
            if (!is_array($db) || empty($db)) {
                $db = $defaults;
                return;
            }
            if (!isset($db['templates']) || !is_array($db['templates'])) {
                $db['templates'] = [];
            }
            // 兼容旧字段 history -> generations
            if (isset($db['history']) && !isset($db['generations'])) {
                $db['generations'] = is_array($db['history']) ? $db['history'] : [];
                unset($db['history']);
            }
            if (!isset($db['generations']) || !is_array($db['generations'])) {
                $db['generations'] = [];
            }
        }, $defaults);
    }

    // ---- Templates ----
    public function addTemplate($origName, $storedName, $size) {
        $id = uniqid('tpl_', true);
        $record = [
            'id' => $id,
            'original_name' => $origName,
            'stored_name' => $storedName,
            'size' => $size,
            'uploaded_at' => time(),
        ];

        $db = Utils::updateJsonWithExclusiveLock($this->cfg['db_file'], function (&$db) use ($id, $record) {
            if (!isset($db['templates']) || !is_array($db['templates'])) $db['templates'] = [];
            $db['templates'][$id] = $record;
        }, ['templates' => [], 'generations' => []]);

        return $db['templates'][$id];
    }

    public function listTemplates() {
        $db = Utils::readJsonWithSharedLock($this->cfg['db_file'], ['templates' => [], 'generations' => []]);
        return array_values($db['templates']);
    }

    public function getTemplate($id) {
        $db = Utils::readJsonWithSharedLock($this->cfg['db_file'], ['templates' => [], 'generations' => []]);
        return $db['templates'][$id] ?? null;
    }

    public function deleteTemplate($id) {
        $fileToDelete = null;
        $removed = false;

        Utils::updateJsonWithExclusiveLock($this->cfg['db_file'], function (&$db) use ($id, &$fileToDelete, &$removed) {
            if (!isset($db['templates'][$id])) return;
            $fileToDelete = $db['templates'][$id]['stored_name'] ?? null;
            unset($db['templates'][$id]);
            $removed = true;
        }, ['templates' => [], 'generations' => []]);

        if ($removed && $fileToDelete) {
            $file = Utils::joinPaths($this->cfg['templates_dir'], $fileToDelete);
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return $removed;
    }

    // ---- Generations ----
    public function addGeneration(array $record) {
        // 允许外部预先提供 id（用于将文件名与记录ID对齐）
        if (empty($record['id'])) {
            $record['id'] = uniqid('gen_', true);
        }
        if (empty($record['created_at'])) {
            $record['created_at'] = time();
        }

        $id = $record['id'];
        $db = Utils::updateJsonWithExclusiveLock($this->cfg['db_file'], function (&$db) use ($id, $record) {
            if (!isset($db['generations']) || !is_array($db['generations'])) $db['generations'] = [];
            $db['generations'][$id] = $record;
        }, ['templates' => [], 'generations' => []]);

        return $db['generations'][$id];
    }

    public function listGenerations() {
        $db = Utils::readJsonWithSharedLock($this->cfg['db_file'], ['templates' => [], 'generations' => []]);
        return array_values($db['generations']);
    }

    public function getGeneration($id) {
        $db = Utils::readJsonWithSharedLock($this->cfg['db_file'], ['templates' => [], 'generations' => []]);
        return $db['generations'][$id] ?? null;
    }

    // ---- 兼容旧接口 ----
    public function addHistory($record) {
        return $this->addGeneration($record);
    }

    public function listHistory() {
        return $this->listGenerations();
    }
}
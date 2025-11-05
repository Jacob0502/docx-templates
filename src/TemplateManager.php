<?php
namespace App;


class TemplateManager {
    protected $cfg;
    protected $db;


    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        Utils::ensureDirs([$cfg['storage_dir'], $cfg['templates_dir'], $cfg['output_dir']]);
        if (!file_exists($cfg['db_file'])) file_put_contents($cfg['db_file'], json_encode(['templates'=>[], 'history'=>[]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->db = json_decode(file_get_contents($cfg['db_file']), true);
    }


    protected function persist() {
        file_put_contents($this->cfg['db_file'], json_encode($this->db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }


    public function addTemplate($origName, $storedName, $size) {
        $id = uniqid('tpl_', true);
        $this->db['templates'][$id] = [
            'id'=>$id,
            'original_name'=>$origName,
            'stored_name'=>$storedName,
            'size'=>$size,
            'uploaded_at'=>time()
        ];
        $this->persist();
        return $this->db['templates'][$id];
    }


    public function listTemplates() {
        return array_values($this->db['templates']);
    }


    public function getTemplate($id) {
        return $this->db['templates'][$id] ?? null;
    }


    public function deleteTemplate($id) {
        if (empty($this->db['templates'][$id])) return false;
        $file = $this->cfg['templates_dir'] . DIRECTORY_SEPARATOR . $this->db['templates'][$id]['stored_name'];
        if (file_exists($file)) unlink($file);
        unset($this->db['templates'][$id]);
        $this->persist();
        return true;
    }


    public function addHistory($record) {
        $id = uniqid('hist_', true);
        $record['id'] = $id;
        $record['created_at'] = time();
        $this->db['history'][$id] = $record;
        $this->persist();
        return $this->db['history'][$id];
    }


    public function listHistory() {
        return array_values($this->db['history']);
    }
}
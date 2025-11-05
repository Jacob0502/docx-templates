# PHP DOCX Template System

## Structure
- docx-templates/ 
  - composer.json 
  - config.php 
  - storage/
    - templates/              # 存放上传的 .docx 模板 
    - output/                 # 存放生成的文档 
    - db.json                 # 模板元数据与生成记录（JSON） 
  - public/ 
    - index.php               # 简单路由 + 页面 
    - upload.php              # 处理上传（POST） 
    - templates.php           # 列表、删除（GET/POST） 
    - generate.php            # 填充并生成文档（POST） 
    - download.php            # 下载生成文件（GET） 
    - assets/                 # 前端静态文件（简单 CSS/JS） 
  - src/ 
    - TemplateManager.php     # 模板管理核心类 
    - Generator.php           # 文档生成核心类（基于 PHPWord） 
    - Auth.php                # 简单会话管理员认证 
    - Utils.php               # 工具函数（安全、校验等） 
  - .htaccess 
  - README.md

## Quick run
php -S localhost:8000 -t ./

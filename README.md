# vivics

這是一個具備多模板、多版本管理的履歷編輯器。前端使用純 HTML/CSS/JS，後端使用 PHP + MySQL 儲存履歷版本、樣式與頭像。

## 功能概要

- 多模板切換（教師風格／極簡風格）
- 多履歷、多版本保存與切換
- 樣式（主色、輔色、背景、字體）預設保存與套用
- 離線模式仍可使用 localStorage 保存

## 後端啟動前準備

1. 建立資料庫與資料表：

```bash
mysql -u root -p -e "CREATE DATABASE resume_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p resume_app < db/schema.sql
```

2. 設定資料庫連線參數（直接編輯 `api/config.php`）：

3. 啟動 PHP 內建伺服器：

```bash
php -S 0.0.0.0:8000
```

然後在瀏覽器開啟 `http://localhost:8000/index.html`。

## API 檔案

- `api.php`：後端 API 入口
- `api/config.php`：資料庫連線設定
- `api/bootstrap.php`：PDO 連線與共用工具
- `db/schema.sql`：資料庫 schema 定義

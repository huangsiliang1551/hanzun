param(
    [string]$BaseDir = "C:\Program Files\MySQL\MySQL Server 8.4",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$Database = "hanzun_cms",
    [switch]$UseDockerCompose
)

$ErrorActionPreference = "Stop"

$workspaceRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$sqlDir = [System.IO.Path]::GetTempPath()
$sqlFile = Join-Path $sqlDir "hanzun-smoke-fixtures.sql"

$sql = @"
CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE $Database;

CREATE TABLE IF NOT EXISTS visitor_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_code VARCHAR(64) NOT NULL,
  page VARCHAR(255) NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  referrer VARCHAR(500) DEFAULT NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  language_code VARCHAR(16) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_visitor_events_session_code (session_code),
  KEY idx_visitor_events_visited_at (visited_at)
);

INSERT INTO media_assets
(id, folder_name, storage_disk, file_path, file_name, file_ext, mime_type, file_size, sha1, width, height, duration_seconds, alt_text_zh, description_zh, uploaded_by, status, created_at, updated_at)
VALUES
  (900001, 'certificates', 'local', '/assets/images/certificates/cert-1.png', 'cert-1.png', 'png', 'image/png', 416650, NULL, 1280, 920, NULL, 'Smoke certificate asset', 'Smoke fixture media asset', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  folder_name = VALUES(folder_name),
  storage_disk = VALUES(storage_disk),
  file_path = VALUES(file_path),
  file_name = VALUES(file_name),
  file_ext = VALUES(file_ext),
  mime_type = VALUES(mime_type),
  file_size = VALUES(file_size),
  width = VALUES(width),
  height = VALUES(height),
  alt_text_zh = VALUES(alt_text_zh),
  description_zh = VALUES(description_zh),
  status = VALUES(status),
  updated_at = NOW();

INSERT INTO chat_sessions
(id, session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, last_message_at, created_at, updated_at)
VALUES
  (900001, 'smoke-session-900001', 'ai', '/en/products/cake-depositor', 'en', 'en', 'AE', 'desktop', 'smoke', 1, 900001, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
  session_code = VALUES(session_code),
  source = VALUES(source),
  source_page = VALUES(source_page),
  entry_language = VALUES(entry_language),
  resolved_language = VALUES(resolved_language),
  country_code = VALUES(country_code),
  device_type = VALUES(device_type),
  utm_source = VALUES(utm_source),
  is_valid_conversation = VALUES(is_valid_conversation),
  inquiry_id = VALUES(inquiry_id),
  last_message_at = VALUES(last_message_at),
  updated_at = NOW();

INSERT INTO chat_messages
(id, session_id, message_role, message_language, content, translated_text, intent_code, contains_contact_info, extracted_entities_json, created_at)
VALUES
  (900001, 900001, 'user', 'en', 'I need a cake production line for UAE market.', NULL, 'product_consulting', 0, JSON_OBJECT(), NOW()),
  (900002, 900001, 'assistant', 'en', 'Please share your company and contact email.', NULL, 'lead_capture', 0, JSON_OBJECT(), NOW()),
  (900003, 900001, 'user', 'en', 'Daniel Foods LLC, daniel.foods@example.com', NULL, 'lead_capture', 1, JSON_OBJECT('company_name', 'Daniel Foods LLC', 'email', 'daniel.foods@example.com'), NOW())
ON DUPLICATE KEY UPDATE
  message_role = VALUES(message_role),
  message_language = VALUES(message_language),
  content = VALUES(content),
  translated_text = VALUES(translated_text),
  intent_code = VALUES(intent_code),
  contains_contact_info = VALUES(contains_contact_info),
  extracted_entities_json = VALUES(extracted_entities_json);

INSERT INTO lead_snapshots
(id, session_id, snapshot_version, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary, confidence_score, created_at)
VALUES
  (900001, 900001, 1, 'Daniel Foods', 'Daniel Foods LLC', 'daniel.foods@example.com', NULL, NULL, 'AE', 'Cake depositor', 'Cake automatic production line', 'Mid-size line with export installation support.', 88.50, NOW())
ON DUPLICATE KEY UPDATE
  snapshot_version = VALUES(snapshot_version),
  contact_name = VALUES(contact_name),
  company_name = VALUES(company_name),
  email = VALUES(email),
  phone = VALUES(phone),
  whatsapp = VALUES(whatsapp),
  country_code = VALUES(country_code),
  product_interest = VALUES(product_interest),
  solution_interest = VALUES(solution_interest),
  requirement_summary = VALUES(requirement_summary),
  confidence_score = VALUES(confidence_score);

INSERT INTO inquiries
(id, source, session_id, primary_contact_type, primary_contact_value, customer_name, company_name, country_code, language_code, product_interest, solution_interest, requirement_summary, inquiry_score, status, assigned_to, first_response_at, browse_traces, change_logs, follow_ups, created_at, updated_at)
VALUES
  (900001, 'ai', 900001, 'email', 'daniel.foods@example.com', 'Daniel Foods', 'Daniel Foods LLC', 'AE', 'en', 'Cake depositor', 'Cake automatic production line', 'Mid-size line with export installation support.', 88.50, 'new', NULL, NULL,
   JSON_ARRAY(
     JSON_OBJECT('page', '/en', 'visited_at', DATE_FORMAT(NOW() - INTERVAL 3 HOUR, '%Y-%m-%d %H:%i:%s')),
     JSON_OBJECT('page', '/en/solutions/cake-line', 'visited_at', DATE_FORMAT(NOW() - INTERVAL 170 MINUTE, '%Y-%m-%d %H:%i:%s')),
     JSON_OBJECT('page', '/en/products/cake-depositor', 'visited_at', DATE_FORMAT(NOW() - INTERVAL 160 MINUTE, '%Y-%m-%d %H:%i:%s'))
   ),
   JSON_ARRAY(
     JSON_OBJECT('field', 'status', 'from', 'new', 'to', 'new', 'changed_at', DATE_FORMAT(NOW() - INTERVAL 30 MINUTE, '%Y-%m-%d %H:%i:%s'))
   ),
   JSON_ARRAY(
     JSON_OBJECT('content', 'AI captured email and production-line interest.', 'created_at', DATE_FORMAT(NOW() - INTERVAL 25 MINUTE, '%Y-%m-%d %H:%i:%s'))
   ),
   NOW(), NOW())
ON DUPLICATE KEY UPDATE
  source = VALUES(source),
  session_id = VALUES(session_id),
  primary_contact_type = VALUES(primary_contact_type),
  primary_contact_value = VALUES(primary_contact_value),
  customer_name = VALUES(customer_name),
  company_name = VALUES(company_name),
  country_code = VALUES(country_code),
  language_code = VALUES(language_code),
  product_interest = VALUES(product_interest),
  solution_interest = VALUES(solution_interest),
  requirement_summary = VALUES(requirement_summary),
  inquiry_score = VALUES(inquiry_score),
  status = VALUES(status),
  assigned_to = VALUES(assigned_to),
  first_response_at = VALUES(first_response_at),
  browse_traces = VALUES(browse_traces),
  change_logs = VALUES(change_logs),
  follow_ups = VALUES(follow_ups),
  updated_at = NOW();
"@

Set-Content -LiteralPath $sqlFile -Value $sql -Encoding utf8

try {
    if ($UseDockerCompose) {
        $dockerArgs = @("compose", "exec", "-T", "mysql", "mysql", "--default-character-set=utf8mb4", "-u$User")
        if ($Password -ne "") {
            $dockerArgs += "-p$Password"
        }

        Push-Location $workspaceRoot
        try {
            Get-Content -LiteralPath $sqlFile -Raw -Encoding UTF8 | & docker @dockerArgs
        } finally {
            Pop-Location
        }
    } else {
        $mysql = Join-Path $BaseDir "bin\mysql.exe"
        if (!(Test-Path $mysql)) {
            throw "mysql.exe not found: $mysql"
        }

        $mysqlArgs = @("--protocol=tcp", "--host=$DbHost", "--port=$Port", "--user=$User", "--default-character-set=utf8mb4")
        if ($Password -ne "") {
            $mysqlArgs += "--password=$Password"
        }

        Get-Content -LiteralPath $sqlFile -Raw -Encoding UTF8 | & $mysql @mysqlArgs
    }

    if ($LASTEXITCODE -ne 0) {
        throw "Ensure smoke fixtures failed with exit code $LASTEXITCODE"
    }
} finally {
    Remove-Item -LiteralPath $sqlFile -ErrorAction SilentlyContinue
}

Write-Output "Smoke fixtures ensured."

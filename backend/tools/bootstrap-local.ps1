param(
    [switch]$ReimportSchema,
    [string]$PhpExe = "",
    [string]$MysqlExe = "",
    [string]$MysqldExe = "",
    [string]$MysqlBaseDir = ""
)

$toolDir = $PSScriptRoot
$mysqlScript = Join-Path $toolDir "start-local-mysql.ps1"
$importScript = Join-Path $toolDir "import-schema.ps1"
$backendScript = Join-Path $toolDir "start-local-backend.ps1"
$smokeScript = Join-Path $toolDir "smoke-test.ps1"
$schemaMarker = "C:\hanzun-cms-runtime\mysql\.schema-imported"

$mysqlArgs = @("-ExecutionPolicy", "Bypass", "-File", $mysqlScript)
if (-not [string]::IsNullOrWhiteSpace($MysqlBaseDir)) {
    $mysqlArgs += @("-BaseDir", $MysqlBaseDir)
}
if (-not [string]::IsNullOrWhiteSpace($MysqlExe)) {
    $mysqlArgs += @("-MysqlExe", $MysqlExe)
}
if (-not [string]::IsNullOrWhiteSpace($MysqldExe)) {
    $mysqlArgs += @("-MysqldExe", $MysqldExe)
}
& powershell @mysqlArgs

if ($ReimportSchema -or !(Test-Path $schemaMarker)) {
    $importArgs = @("-ExecutionPolicy", "Bypass", "-File", $importScript)
    if (-not [string]::IsNullOrWhiteSpace($MysqlBaseDir)) {
        $importArgs += @("-BaseDir", $MysqlBaseDir)
    }
    if (-not [string]::IsNullOrWhiteSpace($MysqlExe)) {
        $importArgs += @("-MysqlExe", $MysqlExe)
    }
    & powershell @importArgs
    New-Item -ItemType File -Path $schemaMarker -Force | Out-Null
}

$backendArgs = @("-ExecutionPolicy", "Bypass", "-File", $backendScript)
if (-not [string]::IsNullOrWhiteSpace($PhpExe)) {
    $backendArgs += @("-PhpExe", $PhpExe)
}
& powershell @backendArgs
& powershell -ExecutionPolicy Bypass -File $smokeScript

param(
    [string]$BaseDir = "",
    [string]$MysqlExe = "",
    [string]$SchemaFile = "",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root"
)

function Resolve-ExecutablePath {
    param(
        [string]$ExplicitPath,
        [string]$EnvKey,
        [string]$CommandName,
        [string[]]$FallbackPaths = @()
    )

    $candidates = @()
    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        $candidates += $ExplicitPath
    }
    $envPath = [Environment]::GetEnvironmentVariable($EnvKey, "Process")
    if ([string]::IsNullOrWhiteSpace($envPath)) {
        $envPath = [Environment]::GetEnvironmentVariable($EnvKey, "User")
    }
    if ([string]::IsNullOrWhiteSpace($envPath)) {
        $envPath = [Environment]::GetEnvironmentVariable($EnvKey, "Machine")
    }
    if (-not [string]::IsNullOrWhiteSpace($envPath)) {
        $candidates += $envPath
    }
    try {
        $command = Get-Command $CommandName -ErrorAction Stop
        if ($command -and $command.Source) {
            $candidates += $command.Source
        }
    } catch {
    }
    $candidates += $FallbackPaths

    foreach ($candidate in $candidates) {
        if (-not [string]::IsNullOrWhiteSpace($candidate) -and (Test-Path $candidate)) {
            return $candidate
        }
    }

    return ""
}

if (-not [string]::IsNullOrWhiteSpace($BaseDir) -and [string]::IsNullOrWhiteSpace($MysqlExe)) {
    $MysqlExe = Join-Path $BaseDir "bin\mysql.exe"
}

$MysqlExe = Resolve-ExecutablePath -ExplicitPath $MysqlExe -EnvKey "HANZUN_MYSQL_EXE" -CommandName "mysql" -FallbackPaths @(
    "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
)

if (!(Test-Path $MysqlExe)) {
    throw "mysql.exe not found. Add MySQL bin to PATH, set HANZUN_MYSQL_EXE, or pass -MysqlExe."
}

$backendDir = Split-Path $PSScriptRoot -Parent
$workspaceDir = Split-Path $backendDir -Parent

if ([string]::IsNullOrWhiteSpace($SchemaFile)) {
    $fullDumpFile = Join-Path $workspaceDir "hanzun_cms.sql"
    $fallbackSchemaFile = Join-Path $backendDir "database\sql\001_init_schema.sql"
    if (Test-Path $fullDumpFile) {
        $SchemaFile = $fullDumpFile
    } else {
        $SchemaFile = $fallbackSchemaFile
    }
}

if (!(Test-Path $SchemaFile)) {
    throw "Schema file not found: $SchemaFile"
}

$runtimeDir = "C:\hanzun-cms-runtime\mysql"
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
$asciiSchemaFile = Join-Path $runtimeDir "schema-import.sql"
Copy-Item -LiteralPath $SchemaFile -Destination $asciiSchemaFile -Force

$command = "`"$MysqlExe`" --protocol=tcp --host=$DbHost --port=$Port --user=$User --default-character-set=utf8mb4 < `"$asciiSchemaFile`""
cmd.exe /d /c $command

if ($LASTEXITCODE -ne 0) {
    throw "Schema import failed with exit code $LASTEXITCODE"
}

Write-Output "SQL imported: $SchemaFile"

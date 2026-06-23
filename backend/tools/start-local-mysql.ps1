param(
    [string]$BaseDir = "",
    [string]$MysqldExe = "",
    [string]$MysqlExe = "",
    [string]$RuntimeDir = "C:\hanzun-cms-runtime\mysql",
    [int]$Port = 3306
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

if (-not [string]::IsNullOrWhiteSpace($BaseDir)) {
    if ([string]::IsNullOrWhiteSpace($MysqldExe)) {
        $MysqldExe = Join-Path $BaseDir "bin\mysqld.exe"
    }
    if ([string]::IsNullOrWhiteSpace($MysqlExe)) {
        $MysqlExe = Join-Path $BaseDir "bin\mysql.exe"
    }
}

$MysqldExe = Resolve-ExecutablePath -ExplicitPath $MysqldExe -EnvKey "HANZUN_MYSQLD_EXE" -CommandName "mysqld" -FallbackPaths @(
    "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe"
)
$MysqlExe = Resolve-ExecutablePath -ExplicitPath $MysqlExe -EnvKey "HANZUN_MYSQL_EXE" -CommandName "mysql" -FallbackPaths @(
    "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
)

if (!(Test-Path $MysqldExe)) {
    throw "mysqld.exe not found. Add MySQL bin to PATH, set HANZUN_MYSQLD_EXE, or pass -MysqldExe."
}
if (!(Test-Path $MysqlExe)) {
    throw "mysql.exe not found. Add MySQL bin to PATH, set HANZUN_MYSQL_EXE, or pass -MysqlExe."
}

$dataDir = Join-Path $RuntimeDir "data"
$tmpDir = Join-Path $RuntimeDir "tmp"
$logFile = Join-Path $RuntimeDir "mysql-error.log"
$configFile = Join-Path $RuntimeDir "mysql-dev.ini"
$bootstrapSqlFile = Join-Path $RuntimeDir "bootstrap.sql"
$bootstrapMarker = Join-Path $RuntimeDir ".bootstrap-complete"

New-Item -ItemType Directory -Path $RuntimeDir -Force | Out-Null
New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
New-Item -ItemType Directory -Path $tmpDir -Force | Out-Null

$configContent = @"
[mysqld]
basedir=$((Split-Path (Split-Path $MysqldExe -Parent) -Parent).Replace('\', '/'))/
datadir=$($dataDir.Replace('\', '/'))
port=$Port
bind-address=127.0.0.1
server_id=1
mysqlx=0
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone=+08:00
log-error=$($logFile.Replace('\', '/'))
pid-file=$($(Join-Path $RuntimeDir 'mysql.pid').Replace('\', '/'))
tmpdir=$($tmpDir.Replace('\', '/'))

[client]
host=127.0.0.1
port=$Port
default-character-set=utf8mb4
"@
Set-Content -LiteralPath $configFile -Value $configContent -Encoding ascii

$bootstrapSql = @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
"@
Set-Content -LiteralPath $bootstrapSqlFile -Value $bootstrapSql -Encoding ascii

$initialized = Test-Path (Join-Path $dataDir "mysql")
if (-not $initialized) {
    & $MysqldExe --defaults-file=$configFile --initialize-insecure --console
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL initialize failed with exit code $LASTEXITCODE"
    }
}

$running = Get-Process | Where-Object { $_.Path -eq $MysqldExe } | Select-Object -First 1
if ($running -and -not (Test-Path $bootstrapMarker)) {
    Stop-Process -Id $running.Id -Force
    Start-Sleep -Seconds 2
    $running = $null
}

if (-not $running) {
    $args = @("--defaults-file=$configFile", "--console")
    if (-not (Test-Path $bootstrapMarker)) {
        $args += "--init-file=$bootstrapSqlFile"
    }
    Start-Process -FilePath $MysqldExe -ArgumentList $args -WindowStyle Hidden | Out-Null
}

$deadline = (Get-Date).AddSeconds(30)
do {
    Start-Sleep -Milliseconds 500
    $portReady = Test-NetConnection 127.0.0.1 -Port $Port -WarningAction SilentlyContinue
    if ($portReady.TcpTestSucceeded) {
        & $MysqlExe --protocol=tcp --host=127.0.0.1 --port=$Port --user=root --execute="SELECT 1;" | Out-Null
        if ($LASTEXITCODE -eq 0) {
            if (-not (Test-Path $bootstrapMarker)) {
                New-Item -ItemType File -Path $bootstrapMarker -Force | Out-Null
            }
            Write-Output "MySQL ready on 127.0.0.1:$Port"
            exit 0
        }
    }
} while ((Get-Date) -lt $deadline)

if (Test-Path $logFile) {
    Get-Content $logFile -Tail 120
}

throw "MySQL did not become ready on 127.0.0.1:$Port within 30 seconds"

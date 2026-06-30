param(
    [string]$PhpExe = "",
    [string]$ProjectDir = "",
    [string]$BindHost = "127.0.0.1",
    [int]$Port = 8080,
    [string]$UploadMaxFilesize = "64M",
    [string]$PostMaxSize = "64M",
    [switch]$Router
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

function Test-BackendReady {
    param(
        [string]$TargetHost,
        [int]$Port
    )

    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri "http://$TargetHost`:$Port/health" -TimeoutSec 3
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

$PhpExe = Resolve-ExecutablePath -ExplicitPath $PhpExe -EnvKey "HANZUN_PHP_EXE" -CommandName "php" -FallbackPaths @(
    "C:\Users\Administrator\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
)

if (!(Test-Path $PhpExe)) {
    throw "php.exe not found. Add php to PATH, set HANZUN_PHP_EXE, or pass -PhpExe."
}

if ([string]::IsNullOrWhiteSpace($ProjectDir)) {
    $ProjectDir = Split-Path $PSScriptRoot -Parent
}

if (-not $Router -and $Port -eq 8080) {
    $Router = $true
}

$iniArgs = @(
    "-d", "upload_max_filesize=$UploadMaxFilesize",
    "-d", "post_max_size=$PostMaxSize"
)

if ($Router) {
    # Router mode: serve whole project (frontend + backend) from project root using backend/public/router.php
    if ($Port -eq 8080) {
        $Port = 8080
    }
    $siteRoot = Split-Path $ProjectDir -Parent
    $routerScript = Join-Path $siteRoot "backend/public/router.php"
    $args = @($iniArgs + @("-S","$BindHost`:$Port","-t",$siteRoot,$routerScript))
} else {
    $siteRoot = $ProjectDir
    $publicDir = Join-Path $ProjectDir "public"
    $args = @($iniArgs + @("-S","$BindHost`:$Port","-t",$publicDir))
}

$runtimeDir = Join-Path $ProjectDir "runtime"
$stdout = Join-Path $runtimeDir "php-server.out.log"
$stderr = Join-Path $runtimeDir "php-server.err.log"

New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null

if (-not (Test-BackendReady -TargetHost $BindHost -Port $Port)) {
    Start-Process -FilePath $PhpExe -ArgumentList $args -WorkingDirectory $siteRoot -RedirectStandardOutput $stdout -RedirectStandardError $stderr -WindowStyle Hidden | Out-Null
}

$deadline = (Get-Date).AddSeconds(20)
do {
    Start-Sleep -Milliseconds 500
    if (Test-BackendReady -TargetHost $BindHost -Port $Port) {
        Write-Output "Backend ready on http://$BindHost`:$Port"
        exit 0
    }
} while ((Get-Date) -lt $deadline)

if (Test-Path $stderr) {
    Get-Content $stderr -Tail 80
}

throw "Backend did not become ready on http://$BindHost`:$Port"

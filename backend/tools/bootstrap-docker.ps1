param(
    [string]$BaseUrl = "http://127.0.0.1:18080",
    [string]$ComposeEnvPath = "",
    [switch]$Build,
    [switch]$NoSmoke
)

$ErrorActionPreference = "Stop"

$workspaceRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$smokeScript = Join-Path $PSScriptRoot "smoke-test-docker.ps1"

function Invoke-Compose {
    param(
        [string[]]$Arguments
    )

    $composeArgs = @()
    if ($ComposeEnvPath -ne "") {
        $composeArgs += "--env-file"
        $composeArgs += $ComposeEnvPath
    }

    $composeArgs += $Arguments

    Write-Host ("docker compose " + ($composeArgs -join " "))
    & docker compose @composeArgs
}

function Wait-ForHealth {
    param(
        [string]$Url,
        [int]$TimeoutSeconds = 120
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -Uri ($Url.TrimEnd("/") + "/health") -UseBasicParsing -TimeoutSec 5
            $payload = $response.Content | ConvertFrom-Json
            if ($payload.code -eq 0 -and $payload.data.status -eq "ok") {
                return
            }
        } catch {
            Start-Sleep -Seconds 3
            continue
        }

        Start-Sleep -Seconds 3
    }

    throw "Timed out waiting for Docker stack health at $Url/health"
}

Push-Location $workspaceRoot
try {
    $upArgs = @("up", "-d")
    if ($Build) {
        $upArgs += "--build"
    }

    Invoke-Compose -Arguments $upArgs
    Wait-ForHealth -Url $BaseUrl

    if (-not $NoSmoke) {
        & powershell -ExecutionPolicy Bypass -File $smokeScript -BaseUrl $BaseUrl
    }
} finally {
    Pop-Location
}

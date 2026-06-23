param(
    [switch]$SkipBackendTests,
    [switch]$SkipFrontendTests,
    [switch]$SkipAdminBuild,
    [switch]$SkipSmokeTest
)

$ErrorActionPreference = "Stop"
$workspaceRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$backendEnvPath = Join-Path $workspaceRoot "backend\.env"
$appStorageMode = ""
if (Test-Path $backendEnvPath) {
    $appStorageLine = Get-Content $backendEnvPath | Where-Object { $_ -match '^APP_STORAGE_MODE=' } | Select-Object -First 1
    if ($appStorageLine) {
        $appStorageMode = (($appStorageLine -split '=', 2)[1]).Trim().Trim('"').Trim("'")
    }
}

function Run-Step {
    param(
        [string]$Label,
        [scriptblock]$Action
    )

    Write-Host ""
    Write-Host ("== {0} ==" -f $Label)
    & $Action
}

Push-Location $workspaceRoot
try {
    if (-not $SkipBackendTests) {
        Run-Step -Label "Backend tests" -Action {
            $files = Get-ChildItem backend\tests -Filter *.js | Sort-Object Name
            foreach ($file in $files) {
                $content = Get-Content $file.FullName -Raw
                if ($appStorageMode -eq "database" -and $content -match 'PREFER_RUNTIME_STORAGE') {
                    Write-Host ("[skip] {0} (legacy runtime-storage test skipped under APP_STORAGE_MODE=database)" -f $file.FullName)
                    continue
                }
                Write-Host ("[node] {0}" -f $file.FullName)
                & node $file.FullName
                if ($LASTEXITCODE -ne 0) {
                    throw ("Node validation failed: " + $file.FullName)
                }
            }
        }
    }

    if (-not $SkipFrontendTests) {
        Run-Step -Label "Frontend tests" -Action {
            $testsDir = Join-Path $workspaceRoot "tests"
            if (-not (Test-Path $testsDir)) {
                Write-Host "[skip] Frontend tests directory not found."
                return
            }

            $files = Get-ChildItem $testsDir -Filter *.js | Sort-Object Name
            foreach ($file in $files) {
                Write-Host ("[node] {0}" -f $file.FullName)
                & node $file.FullName
                if ($LASTEXITCODE -ne 0) {
                    throw ("Node validation failed: " + $file.FullName)
                }
            }
        }
    }

    if (-not $SkipAdminBuild) {
        Run-Step -Label "React admin build" -Action {
            Push-Location (Join-Path $workspaceRoot "admin-v2")
            try {
                & npm run build
                if ($LASTEXITCODE -ne 0) {
                    throw "React admin build failed."
                }
            } finally {
                Pop-Location
            }
        }
    }

    if (-not $SkipSmokeTest) {
        Run-Step -Label "Local backend smoke test" -Action {
            & powershell -ExecutionPolicy Bypass -File (Join-Path $workspaceRoot "backend\tools\start-local-backend.ps1") -ProjectDir (Join-Path $workspaceRoot "backend") -Router
            if ($LASTEXITCODE -ne 0) {
                throw "Failed to start local backend router."
            }

            & powershell -ExecutionPolicy Bypass -File (Join-Path $workspaceRoot "backend\tools\smoke-test.ps1") -BaseUrl "http://127.0.0.1:8080"
            if ($LASTEXITCODE -ne 0) {
                throw "Local backend smoke test failed."
            }
        }
    }

    Write-Host ""
    Write-Host "Project verification completed."
} finally {
    Pop-Location
}

param(
    [string]$BaseUrl = "http://127.0.0.1:18080",
    [string]$Username = "admin",
    [string]$Password = "admin123456",
    [string]$FixtureDbUser = "root",
    [string]$FixtureDbPassword = "root",
    [string]$FixtureDbName = "hanzun_cms",
    [switch]$EnableMutationChecks
)

$smokeScript = Join-Path $PSScriptRoot "smoke-test.ps1"

$args = @(
    "-ExecutionPolicy", "Bypass",
    "-File", $smokeScript,
    "-BaseUrl", $BaseUrl,
    "-Username", $Username,
    "-Password", $Password,
    "-FixtureDbUser", $FixtureDbUser,
    "-FixtureDbPassword", $FixtureDbPassword,
    "-FixtureDbName", $FixtureDbName,
    "-UseDockerComposeFixtures"
)

if ($EnableMutationChecks) {
    $args += "-EnableMutationChecks"
}

& powershell @args

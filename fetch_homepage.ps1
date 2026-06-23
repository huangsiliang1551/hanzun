param(
    [string]$BaseUrl = 'http://124.221.43.124:666/index.html'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$rootDir = (Get-Location).Path
$downloaded = New-Object 'System.Collections.Generic.HashSet[string]'
$cssQueue = New-Object System.Collections.Queue
$rewrites = @{}

function Ensure-ParentDirectory([string]$path) {
    $parent = Split-Path -Parent $path
    if ($parent -and -not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
}

function Get-LocalPath([Uri]$uri, [Uri]$pageBase) {
    if (-not $uri.IsAbsoluteUri) {
        $uri = [Uri]::new($pageBase, $uri)
    }

    $uriHost = $uri.Host.ToLowerInvariant()
    $baseHost = $pageBase.Host.ToLowerInvariant()
    $segments = $uri.AbsolutePath.TrimStart('/')

    if ([string]::IsNullOrWhiteSpace($segments)) {
        $segments = 'index.html'
    }

    if ($uri.AbsolutePath.EndsWith('/')) {
        $segments = Join-Path $segments 'index.html'
    }

    $safePath = $segments -replace '[\\:*?"<>|]', '_'

    if ($uriHost -eq $baseHost) {
        return $safePath -replace '\\', '/'
    }

    $querySuffix = ''
    if ($uri.Query) {
        $queryHex = ([System.BitConverter]::ToString([System.Text.Encoding]::UTF8.GetBytes($uri.Query))).Replace('-', '').ToLowerInvariant()
        $querySuffix = '_' + $queryHex.Substring(0, [Math]::Min(16, $queryHex.Length))
    }

    $ext = [System.IO.Path]::GetExtension($safePath)
    $nameWithoutExt = [System.IO.Path]::GetFileNameWithoutExtension($safePath)
    $dir = Split-Path -Parent $safePath
    $fileName = if ($ext) { "$nameWithoutExt$querySuffix$ext" } else { "$nameWithoutExt$querySuffix" }
    $combined = if ($dir) { Join-Path $dir $fileName } else { $fileName }

    return (Join-Path 'external' (Join-Path $uriHost $combined)) -replace '\\', '/'
}

function Download-File([string]$sourceUrl, [string]$localRelativePath, [bool]$Required = $true) {
    if ($downloaded.Contains($sourceUrl)) {
        return Test-Path -LiteralPath (Join-Path $rootDir $localRelativePath)
    }

    $null = $downloaded.Add($sourceUrl)
    $targetPath = Join-Path $rootDir $localRelativePath
    Ensure-ParentDirectory $targetPath

    & curl.exe -L -sS --fail $sourceUrl --output $targetPath
    if ($LASTEXITCODE -ne 0) {
        if (Test-Path -LiteralPath $targetPath) {
            Remove-Item -LiteralPath $targetPath -Force -ErrorAction SilentlyContinue
        }

        if ($Required) {
            throw "Download failed: $sourceUrl"
        }

        return $false
    }

    if ([System.IO.Path]::GetExtension($targetPath).ToLowerInvariant() -eq '.css') {
        $cssQueue.Enqueue(@{
            SourceUrl = $sourceUrl
            LocalRelativePath = $localRelativePath
        })
    }

    return $true
}

$pageUri = [Uri]$BaseUrl
$pageLocalPath = 'index.html'
$null = Download-File $BaseUrl $pageLocalPath

$htmlPath = Join-Path $rootDir $pageLocalPath
$html = Get-Content -LiteralPath $htmlPath -Raw -Encoding UTF8
$assetPattern = '(?i)(?:src|href)=["'']([^"''#]+)["'']'
$matches = [regex]::Matches($html, $assetPattern)

foreach ($match in $matches) {
    $original = $match.Groups[1].Value
    if ($original -match '^(mailto:|tel:|javascript:|data:)' -or $original.StartsWith('#')) {
        continue
    }

    try {
        $assetUri = [Uri]::new($pageUri, $original)
    } catch {
        continue
    }

    $isLikelyAsset = $original -match '(?i)\.(css|js|png|jpe?g|gif|webp|svg|ico|bmp|avif|mp4|webm|ogg|mp3|wav|woff2?|ttf|otf)(\?.*)?$' -or
                     $original -match '(?i)^https://fonts\.googleapis\.com/'
    if (-not $isLikelyAsset) {
        continue
    }

    $localRelative = Get-LocalPath $assetUri $pageUri
    $required = $assetUri.Host.ToLowerInvariant() -eq $pageUri.Host.ToLowerInvariant()
    if (Download-File $assetUri.AbsoluteUri $localRelative $required) {
        $rewrites[$original] = $localRelative
    }
}

$urlPattern = '(?i)url\(([^)]+)\)'
while ($cssQueue.Count -gt 0) {
    $cssItem = $cssQueue.Dequeue()
    $cssPath = Join-Path $rootDir $cssItem.LocalRelativePath
    $cssDir = Split-Path -Parent $cssPath
    $cssContent = Get-Content -LiteralPath $cssPath -Raw -Encoding UTF8
    $changed = $false
    $cssMatches = [regex]::Matches($cssContent, $urlPattern)

    foreach ($cssMatch in $cssMatches) {
        $raw = $cssMatch.Groups[1].Value.Trim()
        $trimmed = $raw.Trim([char[]]@(34, 39))
        if (-not $trimmed -or $trimmed -match '^(data:|about:|#)') {
            continue
        }

        try {
            $resolved = [Uri]::new([Uri]$cssItem.SourceUrl, $trimmed)
        } catch {
            continue
        }

        $localRelative = Get-LocalPath $resolved $pageUri
        $required = $resolved.Host.ToLowerInvariant() -eq $pageUri.Host.ToLowerInvariant()
        if (-not (Download-File $resolved.AbsoluteUri $localRelative $required)) {
            continue
        }

        $absoluteTarget = Join-Path $rootDir $localRelative
        $relativeForCss = [System.IO.Path]::GetRelativePath($cssDir, $absoluteTarget).Replace('\', '/')
        $replacement = "url('$relativeForCss')"
        $fullMatch = $cssMatch.Value

        if ($cssContent.Contains($fullMatch)) {
            $cssContent = $cssContent.Replace($fullMatch, $replacement)
            $changed = $true
        }
    }

    if ($changed) {
        Set-Content -LiteralPath $cssPath -Value $cssContent -Encoding UTF8
    }
}

foreach ($entry in $rewrites.GetEnumerator()) {
    $escaped = [regex]::Escape($entry.Key)
    $html = [regex]::Replace(
        $html,
        $escaped,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($m)
            $entry.Value
        }
    )
}

Set-Content -LiteralPath $htmlPath -Value $html -Encoding UTF8

Get-ChildItem -Recurse -File |
    Select-Object FullName, Length |
    Sort-Object FullName

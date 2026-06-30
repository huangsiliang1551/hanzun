param(
    [string]$BaseUrl = "http://127.0.0.1:8080",
    [string]$Username = "admin",
    [string]$Password = "admin123456",
    [string]$FixtureDbBaseDir = "C:\Program Files\MySQL\MySQL Server 8.4",
    [string]$FixtureDbHost = "127.0.0.1",
    [int]$FixtureDbPort = 3306,
    [string]$FixtureDbUser = "root",
    [string]$FixtureDbPassword = "",
    [string]$FixtureDbName = "hanzun_cms",
    [switch]$UseDockerComposeFixtures,
    [switch]$EnableMutationChecks
)

$fixtureMediaId = 900001
$fixtureInquiryId = 900001
$fixtureScript = Join-Path $PSScriptRoot "ensure-smoke-fixtures.ps1"

function Invoke-JsonRequest {
    param(
        [string]$Uri,
        [string]$Method = "GET",
        [hashtable]$Headers,
        $Body = $null
    )

    $requestParams = @{
        UseBasicParsing = $true
        Uri             = "$BaseUrl$Uri"
        Method          = $Method
        TimeoutSec      = 10
    }

    if ($Headers) {
        $requestParams.Headers = $Headers
    }

    if ($null -ne $Body) {
        $requestParams.ContentType = "application/json"
        $requestParams.Body = ($Body | ConvertTo-Json -Compress -Depth 10)
    }

    $response = Invoke-WebRequest @requestParams
    return $response.Content | ConvertFrom-Json
}

function Get-ResponseItems {
    param($Response)

    if ($null -eq $Response -or $null -eq $Response.data -or $null -eq $Response.data.items) {
        return @()
    }

    return @($Response.data.items)
}

function Get-FirstAvailableFeaturedCandidate {
    param([hashtable]$Headers)

    $sources = @(
        @{ endpoint = "products";  source_type = "product"  },
        @{ endpoint = "solutions"; source_type = "solution" },
        @{ endpoint = "articles";  source_type = "article"  }
    )

    foreach ($source in $sources) {
        $response = Invoke-JsonRequest -Uri "/admin/$($source.endpoint)" -Headers $Headers
        $items = Get-ResponseItems -Response $response
        if ($response.code -ne 0 -or $items.Count -eq 0) {
            continue
        }

        $preferred = $items | Where-Object { $_.publish_status -eq "published" } | Select-Object -First 1
        if ($null -eq $preferred) {
            $preferred = $items[0]
        }

        return [pscustomobject]@{
            source_type = $source.source_type
            endpoint    = $source.endpoint
            item        = $preferred
        }
    }

    throw "No available record found in /admin/products, /admin/solutions, or /admin/articles"
}

function Get-FirstAvailableInquiryId {
    param([hashtable]$Headers)

    $response = Invoke-JsonRequest -Uri "/admin/inquiries" -Headers $Headers
    $items = Get-ResponseItems -Response $response
    if ($items.Count -eq 0) {
        return $null
    }

    return [int]$items[0].id
}

function Convert-ToHomepageSectionPayload {
    param($Section)

    return @{
        title_zh    = $Section.title_zh
        subtitle_zh = $Section.subtitle_zh
        fetch_mode  = $Section.fetch_mode
        extra_config = if ($Section.extra_config -is [string]) {
            $Section.extra_config
        } else {
            $Section.extra_config | ConvertTo-Json -Compress -Depth 10
        }
        sort        = [int]$Section.sort
        is_enabled  = [int]$Section.is_enabled
    }
}

$health = Invoke-JsonRequest -Uri "/health"
$publicBootstrapEn = Invoke-JsonRequest -Uri "/api/site/bootstrap?lang=en"
$publicProductEn = Invoke-JsonRequest -Uri "/api/site/products/cake-depositor?lang=en"
$fixtureArgs = @(
    "-ExecutionPolicy", "Bypass",
    "-File", $fixtureScript,
    "-BaseDir", $FixtureDbBaseDir,
    "-DbHost", $FixtureDbHost,
    "-Port", $FixtureDbPort,
    "-User", $FixtureDbUser,
    "-Password", $FixtureDbPassword,
    "-Database", $FixtureDbName
)
if ($UseDockerComposeFixtures) {
    $fixtureArgs += "-UseDockerCompose"
}
$fixtureOutput = & powershell @fixtureArgs 2>&1
if ($LASTEXITCODE -ne 0) {
    throw ("ensure-smoke-fixtures failed: " + ($fixtureOutput | Out-String).Trim())
}

$publicAiClientId = "smoke-public-" + [guid]::NewGuid().ToString("N")
$publicAiTrack = Invoke-JsonRequest -Uri "/api/visitor-events" -Method "POST" -Body @{
    client_id = $publicAiClientId
    session_code = ""
    path = "/products.html"
    title = "Smoke Public Visit"
    referrer = $BaseUrl
    language = "en"
}
$publicAiChat = Invoke-JsonRequest -Uri "/api/ai/chat" -Method "POST" -Body @{
    client_id = $publicAiClientId
    session_code = $publicAiTrack.data.session_code
    message = "Name: Smoke QA`nEmail: smoke.qa@example.com`nRequirement: Need a cake production line quotation for testing."
    path = "/products.html"
    title = "Smoke Public Visit"
    referrer = $BaseUrl
    language = "en"
    utm_source = "smoke"
}

$loginBody = @{
    username = $Username
    password = $Password
}
$login = Invoke-JsonRequest -Uri "/admin/auth/login" -Method "POST" -Body $loginBody
$token = $login.data.access_token

if ([string]::IsNullOrWhiteSpace($token)) {
    throw "Login did not return access_token"
}

$headers = @{
    Authorization = "Bearer $token"
}

$profile = Invoke-JsonRequest -Uri "/admin/auth/profile" -Headers $headers
$products = Invoke-JsonRequest -Uri "/admin/products" -Headers $headers
$solutions = Invoke-JsonRequest -Uri "/admin/solutions" -Headers $headers
$articles = Invoke-JsonRequest -Uri "/admin/articles" -Headers $headers
$inquiries = Invoke-JsonRequest -Uri "/admin/inquiries" -Headers $headers
$jobs = Invoke-JsonRequest -Uri "/admin/dashboard/jobs" -Headers $headers
$homepageSections = Invoke-JsonRequest -Uri "/admin/homepage/sections" -Headers $headers
$homepageWorkflow = Invoke-JsonRequest -Uri "/admin/homepage/workflow" -Headers $headers
$aboutPages = Invoke-JsonRequest -Uri "/admin/about/pages" -Headers $headers
$pages = Invoke-JsonRequest -Uri "/admin/pages" -Headers $headers
$languages = Invoke-JsonRequest -Uri "/admin/settings/languages" -Headers $headers
$deepseek = Invoke-JsonRequest -Uri "/admin/settings/deepseek" -Headers $headers
$siteSettings = Invoke-JsonRequest -Uri "/admin/settings/site" -Headers $headers
$contacts = Invoke-JsonRequest -Uri "/admin/contact-center/items" -Headers $headers
$mediaAssets = Invoke-JsonRequest -Uri "/admin/media/assets" -Headers $headers

$inquiryItems = Get-ResponseItems -Response $inquiries
$inquiryDetail = Invoke-JsonRequest -Uri "/admin/inquiries/$fixtureInquiryId" -Headers $headers
$inquiryDetailId = $fixtureInquiryId
$inquiryDetailSkipped = $false
$inquiryDetailReason = $null

$mediaItems = Get-ResponseItems -Response $mediaAssets
$mediaDetail = Invoke-JsonRequest -Uri "/admin/media/assets/$fixtureMediaId" -Headers $headers
$mediaDetailId = $fixtureMediaId
$mediaDetailSkipped = $false
$mediaDetailReason = $null

$featuredPatch = $null
$featuredRestore = $null
$featuredPatchSkipped = $true
$featuredPatchReason = "Mutation checks disabled."
$featuredPatchSourceType = $null
$featuredPatchEndpoint = $null
$featuredPatchId = $null
$featuredOriginalFeatured = $null
$featuredOriginalManualSort = $null
$featuredPatchManualSort = $null
$featuredPatchedFeatured = $null
$featuredPatchedManualSort = $null
$featuredRestoredFeatured = $null
$featuredRestoredManualSort = $null

$publishResponse = $null
$restoreLiveResponse = $null
$publishRestoreSkipped = $true
$publishRestoreReason = "Mutation checks disabled."
$publishDraftSort = $null
$publishLiveSort = $null
$restoreLiveSort = $null

if ($EnableMutationChecks) {
    $featuredCandidate = Get-FirstAvailableFeaturedCandidate -Headers $headers
    $featuredItem = $featuredCandidate.item
    $featuredPatchSourceType = $featuredCandidate.source_type
    $featuredPatchEndpoint = $featuredCandidate.endpoint
    $featuredPatchId = [int]$featuredItem.id
    $featuredOriginalFeatured = [int]$featuredItem.is_home_featured
    $featuredOriginalManualSort = [int]$featuredItem.manual_sort
    $featuredPatchManualSort = $featuredOriginalManualSort + 1

    $patchBody = @{
        is_home_featured = if ($featuredOriginalFeatured -eq 1 -or [string]$featuredItem.publish_status -eq "published") { 1 } else { 0 }
        manual_sort      = $featuredPatchManualSort
    }
    $restoreBody = @{
        is_home_featured = $featuredOriginalFeatured
        manual_sort      = $featuredOriginalManualSort
    }

    try {
        $featuredPatch = Invoke-JsonRequest -Uri "/admin/homepage/featured-items/$featuredPatchSourceType/$featuredPatchId" -Method "PATCH" -Headers $headers -Body $patchBody
        $featuredPatchedDetail = Invoke-JsonRequest -Uri "/admin/$featuredPatchEndpoint/$featuredPatchId" -Headers $headers
        $featuredPatchedFeatured = [int]$featuredPatchedDetail.data.is_home_featured
        $featuredPatchedManualSort = [int]$featuredPatchedDetail.data.manual_sort
    }
    finally {
        $featuredRestore = Invoke-JsonRequest -Uri "/admin/homepage/featured-items/$featuredPatchSourceType/$featuredPatchId" -Method "PATCH" -Headers $headers -Body $restoreBody
        $featuredRestoredDetail = Invoke-JsonRequest -Uri "/admin/$featuredPatchEndpoint/$featuredPatchId" -Headers $headers
        $featuredRestoredFeatured = [int]$featuredRestoredDetail.data.is_home_featured
        $featuredRestoredManualSort = [int]$featuredRestoredDetail.data.manual_sort
    }

    $featuredPatchSkipped = $false
    $featuredPatchReason = $null

    $sectionItems = @($homepageSections.data)
    if ($sectionItems.Count -eq 0) {
        throw "No homepage sections available for publish/restore smoke."
    }

    $sectionId = [int]$sectionItems[0].id
    $originalSection = Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Headers $headers
    $baselinePayload = Convert-ToHomepageSectionPayload -Section $originalSection.data

    $publishPayload = $baselinePayload.Clone()
    $publishPayload.sort = [int]$baselinePayload.sort + 1

    $draftPayload = $baselinePayload.Clone()
    $draftPayload.sort = [int]$baselinePayload.sort + 2

    try {
        Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Method "PUT" -Headers $headers -Body $publishPayload | Out-Null
        $publishResponse = Invoke-JsonRequest -Uri "/admin/homepage/publish" -Method "POST" -Headers $headers -Body @{}
        $publishedSection = Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Headers $headers
        $publishLiveSort = [int]$publishedSection.data.sort

        Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Method "PUT" -Headers $headers -Body $draftPayload | Out-Null
        $draftSection = Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Headers $headers
        $publishDraftSort = [int]$draftSection.data.sort

        $restoreLiveResponse = Invoke-JsonRequest -Uri "/admin/homepage/restore-live" -Method "POST" -Headers $headers -Body @{}
        $restoredSection = Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Headers $headers
        $restoreLiveSort = [int]$restoredSection.data.sort
    }
    finally {
        Invoke-JsonRequest -Uri "/admin/homepage/sections/$sectionId" -Method "PUT" -Headers $headers -Body $baselinePayload | Out-Null
        Invoke-JsonRequest -Uri "/admin/homepage/publish" -Method "POST" -Headers $headers -Body @{} | Out-Null
    }

    $publishRestoreSkipped = $false
    $publishRestoreReason = $null
}

[pscustomobject]@{
    health_code = $health.code
    health_status = $health.data.status
    database_connected = $health.data.dependencies.database_connected
    profile_ok = ($profile.code -eq 0)
    products_ok = ($products.code -eq 0)
    solutions_ok = ($solutions.code -eq 0)
    articles_ok = ($articles.code -eq 0)
    inquiries_ok = ($inquiries.code -eq 0)
    inquiry_detail_ok = if ($inquiryDetailSkipped) { $true } else { ($inquiryDetail.code -eq 0) }
    inquiry_detail_id = $inquiryDetailId
    inquiry_detail_skipped = $inquiryDetailSkipped
    inquiry_detail_reason = $inquiryDetailReason
    jobs_ok = ($jobs.code -eq 0)
    homepage_ok = ($homepageSections.code -eq 0)
    homepage_workflow_ok = ($homepageWorkflow.code -eq 0)
    about_ok = ($aboutPages.code -eq 0)
    pages_ok = ($pages.code -eq 0)
    languages_ok = ($languages.code -eq 0)
    deepseek_ok = ($deepseek.code -eq 0)
    site_settings_ok = ($siteSettings.code -eq 0)
    contacts_ok = ($contacts.code -eq 0)
    public_bootstrap_en_ok = ($publicBootstrapEn.code -eq 0 -and $publicBootstrapEn.meta.language.resolved_code -eq "en" -and $publicBootstrapEn.data.homepage.sections[0].language_code -eq "en" -and -not [string]::IsNullOrWhiteSpace([string]$publicBootstrapEn.data.site.site_name) -and -not [string]::IsNullOrWhiteSpace([string]$publicBootstrapEn.data.site.language_strategy))
    public_product_en_ok = ($publicProductEn.code -eq 0 -and $publicProductEn.meta.language.resolved_code -eq "en" -and $publicProductEn.data.name -eq "Cake Depositor")
    public_ai_track_ok = ($publicAiTrack.code -eq 0 -and -not [string]::IsNullOrWhiteSpace([string]$publicAiTrack.data.session_code) -and [int]$publicAiTrack.data.visit_count -ge 1)
    public_ai_chat_ok = ($publicAiChat.code -eq 0 -and -not [string]::IsNullOrWhiteSpace([string]$publicAiChat.data.session_code) -and -not [string]::IsNullOrWhiteSpace([string]$publicAiChat.data.assistant_reply))
    public_ai_session_code = $publicAiTrack.data.session_code
    public_ai_inquiry_id = [int]$publicAiChat.data.inquiry_id
    media_assets_ok = ($mediaAssets.code -eq 0)
    media_asset_detail_ok = if ($mediaDetailSkipped) { $true } else { ($mediaDetail.code -eq 0) }
    media_asset_detail_id = $mediaDetailId
    media_asset_detail_skipped = $mediaDetailSkipped
    media_asset_detail_reason = $mediaDetailReason
    mutation_checks_enabled = [bool]$EnableMutationChecks
    featured_patch_ok = if ($featuredPatchSkipped) { $null } else { ($featuredPatch.code -eq 0 -and $featuredRestore.code -eq 0) }
    featured_restore_ok = if ($featuredPatchSkipped) { $null } else { ($featuredRestore.code -eq 0) }
    featured_patch_skipped = $featuredPatchSkipped
    featured_patch_reason = $featuredPatchReason
    featured_patch_source_type = $featuredPatchSourceType
    featured_patch_id = $featuredPatchId
    featured_original_is_home_featured = $featuredOriginalFeatured
    featured_original_manual_sort = $featuredOriginalManualSort
    featured_patch_manual_sort = $featuredPatchManualSort
    featured_patched_is_home_featured = $featuredPatchedFeatured
    featured_patched_manual_sort = $featuredPatchedManualSort
    featured_restored_is_home_featured = $featuredRestoredFeatured
    featured_restored_manual_sort = $featuredRestoredManualSort
    featured_patch_state_verified = if ($featuredPatchSkipped) { $null } else { ($featuredPatchedManualSort -eq $featuredPatchManualSort -and $featuredPatchedFeatured -eq $patchBody.is_home_featured) }
    featured_restore_state_verified = if ($featuredPatchSkipped) { $null } else { ($featuredRestoredManualSort -eq $featuredOriginalManualSort -and $featuredRestoredFeatured -eq $featuredOriginalFeatured) }
    publish_ok = if ($publishRestoreSkipped) { $null } else { ($publishResponse.code -eq 0) }
    restore_live_ok = if ($publishRestoreSkipped) { $null } else { ($restoreLiveResponse.code -eq 0) }
    publish_restore_skipped = $publishRestoreSkipped
    publish_restore_reason = $publishRestoreReason
    publish_live_sort = $publishLiveSort
    publish_draft_sort = $publishDraftSort
    restore_live_sort = $restoreLiveSort
    restore_live_matches_published = if ($publishRestoreSkipped) { $null } else { ($publishLiveSort -eq $restoreLiveSort) }
} | ConvertTo-Json -Depth 6

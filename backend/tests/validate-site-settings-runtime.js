const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const backendRoot = path.resolve(__dirname, "..");
const storageDir = path.join(backendRoot, "runtime", "storage");
const settingsPath = path.join(storageDir, "system_settings.json");

function backup(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, "utf8") : null;
}

function restore(filePath, content) {
  if (content === null) {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
    return;
  }

  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, content, "utf8");
}

function main() {
  const settingsBackup = backup(settingsPath);

  try {
    const phpCode = `
      $basePath = getcwd();
      require_once $basePath . '/app/common/bootstrap/Autoloader.php';
      require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
      require_once $basePath . '/app/common/bootstrap/helpers.php';
      app\\common\\bootstrap\\Autoloader::register($basePath);
      app\\common\\bootstrap\\EnvLoader::load($basePath . '/.env');

      app\\common\\config\\ConfigRepository::instance()->load($basePath . '/config');

      app\\common\\database\\DatabaseManager::instance()->configure(

          app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', [])

      );

      $service = new app\\service\\system\\SettingService();
      $languageRepository = new app\\repository\\LanguageRepository();
      $before = $service->siteConfig();
      $after = $service->updateSiteConfig([
          'site_name' => 'HANZUN CMS',
          'site_title' => 'HANZUN CMS | Global Bakery Lines',
          'logo_url' => '/assets/images/common/logo-110.png',
          'logo_alt' => 'HANZUN',
          'company_name' => 'Shanghai Hanzun Industrial Co., Ltd.',
          'meta_description' => 'Bakery line equipment manufacturer.',
          'footer_text' => 'Global bakery processing solutions.',
          'language_strategy' => 'ua-first',
          'default_language' => 'en',
          'social_linkedin' => 'https://www.linkedin.com/company/hanzun',
          'social_youtube' => 'https://www.youtube.com/@hanzun'
      ]);

      echo json_encode([
          'before' => $before,
          'after' => $after,
          'languages_after_site_update' => $languageRepository->list(),
          'languages_after_language_update' => $service->updateLanguages([
              ['id' => 1, 'code' => 'zh', 'name' => 'Chinese', 'is_default' => 0, 'is_enabled' => 1, 'sort' => 100],
              ['id' => 2, 'code' => 'en', 'name' => 'English', 'is_default' => 1, 'is_enabled' => 1, 'sort' => 90]
          ]),
          'site_after_language_update' => $service->siteConfig()
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync("php", ["-r", phpCode], {
      cwd: backendRoot,
      encoding: "utf8",
    }));

    const issues = [];

    if (String(payload.before?.config?.site_name || "") === "") {
      issues.push("siteConfig must return default site settings");
    }
    if (String(payload.after?.config?.site_name || "") !== "HANZUN CMS") {
      issues.push("updateSiteConfig must persist site_name");
    }
    if (String(payload.after?.config?.default_language || "") !== "en") {
      issues.push("updateSiteConfig must persist default_language");
    }
    if (String(payload.after?.config?.social_linkedin || "") !== "https://www.linkedin.com/company/hanzun") {
      issues.push("updateSiteConfig must persist social links");
    }
    const defaultLanguageRow = Array.isArray(payload.languages_after_site_update) ? payload.languages_after_site_update.find((item) => String(item.code) === 'en') : null;
    if (Number(defaultLanguageRow?.is_default || 0) !== 1) {
      issues.push("updateSiteConfig must sync default language to language repository");
    }
    if (String(payload.site_after_language_update?.config?.default_language || "") !== "en") {
      issues.push("updateLanguages must sync default language back to site config");
    }

    if (issues.length > 0) {
      console.error("Site settings runtime validation failed:");
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log("Site settings runtime validation passed.");
  } finally {
    restore(settingsPath, settingsBackup);
  }
}

main();

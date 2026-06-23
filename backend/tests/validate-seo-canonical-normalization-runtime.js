const { execFileSync } = require('child_process');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');

function buildPhpPayload() {
  return `
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

    $publicService = new app\\service\\content\\PublicSiteService();
    $seoService = new app\\service\\seo\\SeoService();
    $routePath = '/en/products/cake-depositor';
    $expected = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/') . $routePath;
    $custom = 'https://www.hanzun.example/custom-canonical';

    $invoke = static function (object $service, string $method, string $canonicalUrl, string $routePath): string {
        $reflection = new ReflectionClass($service);
        $resolver = $reflection->getMethod($method);
        $resolver->setAccessible(true);

        return (string) $resolver->invoke($service, $canonicalUrl, $routePath);
    };

    echo json_encode([
      'expected' => $expected,
      'public_example' => $invoke($publicService, 'resolveCanonicalUrl', 'https://example.com' . $routePath, $routePath),
      'public_relative' => $invoke($publicService, 'resolveCanonicalUrl', $routePath, $routePath),
      'public_empty' => $invoke($publicService, 'resolveCanonicalUrl', '', $routePath),
      'public_custom' => $invoke($publicService, 'resolveCanonicalUrl', $custom, $routePath),
      'seo_example' => $invoke($seoService, 'resolveCanonicalUrl', 'https://example.com' . $routePath, $routePath),
      'seo_relative' => $invoke($seoService, 'resolveCanonicalUrl', $routePath, $routePath),
      'seo_empty' => $invoke($seoService, 'resolveCanonicalUrl', '', $routePath),
      'seo_custom' => $invoke($seoService, 'resolveCanonicalUrl', $custom, $routePath),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const output = execFileSync('php', ['-r', buildPhpPayload()], {
    cwd: backendRoot,
    encoding: 'utf8',
  });

  const payload = JSON.parse(output);
  const issues = [];

  ['public_example', 'public_relative', 'public_empty', 'seo_example', 'seo_relative', 'seo_empty'].forEach((key) => {
    if (payload[key] !== payload.expected) {
      issues.push(`${key} must normalize to current APP_URL canonical`);
    }
  });

  ['public_custom', 'seo_custom'].forEach((key) => {
    if (payload[key] !== 'https://www.hanzun.example/custom-canonical') {
      issues.push(`${key} must preserve explicit custom canonical urls`);
    }
  });

  if (issues.length > 0) {
    console.error('SEO canonical normalization runtime validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('SEO canonical normalization runtime validation passed.');
}

main();

<?php

declare(strict_types=1);

use app\common\bootstrap\Autoloader;
use app\common\database\DatabaseManager;
use app\repository\ArticleRepository;
use app\repository\HomepageRepository;
use app\repository\ProductRepository;
use app\repository\SolutionRepository;
use app\repository\SystemSettingRepository;
use app\service\homepage\HomepageService;
use app\service\log\OperationLogService;

require_once dirname(__DIR__) . '/app/common/bootstrap/Autoloader.php';
require_once dirname(__DIR__) . '/app/common/bootstrap/helpers.php';

Autoloader::register(dirname(__DIR__));

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function setPrivateProperty(object $object, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty($object, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($object, $value);
}

function insertHomepageTestData(): void
{
    $pdo = DatabaseManager::instance()->connection();
    if (!($pdo instanceof PDO)) {
        return;
    }

    // Insert homepage sections
    $pdo->exec('DELETE FROM homepage_sections');
    $stmt = $pdo->prepare(
        'INSERT INTO homepage_sections (section_key, section_type, title_zh, subtitle_zh, fetch_mode, extra_config, sort, is_enabled)
         VALUES (:section_key, :section_type, :title_zh, :subtitle_zh, :fetch_mode, :extra_config, :sort, :is_enabled)'
    );
    $stmt->execute([
        'section_key' => 'featured_products',
        'section_type' => 'product_list',
        'title_zh' => '推荐设备',
        'subtitle_zh' => '按首页推荐位自动聚合',
        'fetch_mode' => 'auto_latest',
        'extra_config' => json_encode(['limit' => 6], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'sort' => 99,
        'is_enabled' => 1,
    ]);

    // Insert test products
    $pdo->exec('DELETE FROM product_categories');
    $pdo->exec('DELETE FROM products');
    $pdo->prepare('INSERT INTO product_categories (id, parent_id, name_zh, slug, sort, is_enabled) VALUES (1, 0, :name, :slug, 100, 1)')
        ->execute(['name' => '测试分类', 'slug' => 'test-cat']);

    $productStmt = $pdo->prepare(
        'INSERT INTO products (id, category_id, sku, name_zh, summary_zh, content_zh, business_status, publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at)
         VALUES (:id, :category_id, :sku, :name_zh, :summary_zh, :content_zh, :business_status, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, :created_at, :updated_at)'
    );

    $products = [
        ['id' => 11, 'sku' => 'P-11', 'name_zh' => '产品11', 'is_home_featured' => 1, 'manual_sort' => 20, 'publish_time' => '2026-06-01 09:00:00', 'created_at' => '2026-06-01 09:00:00', 'updated_at' => '2026-06-01 09:00:00'],
        ['id' => 12, 'sku' => 'P-12', 'name_zh' => '产品12', 'is_home_featured' => 0, 'manual_sort' => 999, 'publish_time' => '2026-06-03 10:00:00', 'created_at' => '2026-06-03 10:00:00', 'updated_at' => '2026-06-03 10:00:00'],
        ['id' => 13, 'sku' => 'P-13', 'name_zh' => '产品13', 'is_home_featured' => 1, 'manual_sort' => 20, 'publish_time' => '2026-06-04 11:00:00', 'created_at' => '2026-06-04 11:00:00', 'updated_at' => '2026-06-04 11:00:00'],
        ['id' => 14, 'sku' => 'P-14', 'name_zh' => '产品14', 'is_home_featured' => 1, 'manual_sort' => 100, 'publish_time' => '2026-05-01 08:00:00', 'created_at' => '2026-05-01 08:00:00', 'updated_at' => '2026-05-01 08:00:00'],
    ];

    foreach ($products as $p) {
        $productStmt->execute([
            'id' => $p['id'],
            'category_id' => 1,
            'sku' => $p['sku'],
            'name_zh' => $p['name_zh'],
            'summary_zh' => '',
            'content_zh' => '',
            'business_status' => 'on_sale',
            'publish_status' => 'published',
            'translation_status' => 'completed',
            'seo_status' => 'generated',
            'is_home_featured' => $p['is_home_featured'],
            'manual_sort' => $p['manual_sort'],
            'slug' => 'product-' . $p['id'],
            'seo_title' => '',
            'seo_keywords' => '',
            'seo_description' => '',
            'publish_time' => $p['publish_time'],
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => $p['created_at'],
            'updated_at' => $p['updated_at'],
        ]);
    }
}

function createHomepageServiceFixture(): array
{
    $homepageRepository = new HomepageRepository();
    $systemSettingRepository = new SystemSettingRepository();
    $productRepository = new ProductRepository();
    $solutionRepository = new SolutionRepository();
    $articleRepository = new ArticleRepository();
    $operationLogService = new OperationLogService();

    setPrivateProperty($homepageRepository, 'systemSettingRepository', $systemSettingRepository);

    $service = new HomepageService(
        $homepageRepository,
        $productRepository,
        $solutionRepository,
        $articleRepository,
        $operationLogService
    );

    return [
        'service' => $service,
        'homepage_repository' => $homepageRepository,
        'product_repository' => $productRepository,
        'system_setting_repository' => $systemSettingRepository,
    ];
}

function testPreviewOnlyReturnsPickedItemsAndRespectsOrdering(): void
{
    insertHomepageTestData();

    $fixture = createHomepageServiceFixture();
    /** @var HomepageService $service */
    $service = $fixture['service'];

    $preview = $service->previewPayload();
    $items = $preview['sections'][0]['items'] ?? [];

    assertSameValue([14, 13, 11], array_column($items, 'id'), 'preview should only include homepage picked products ordered by manual_sort desc then publish_time desc');
    assertTrueValue(!in_array(12, array_column($items, 'id'), true), 'preview should exclude published but unpicked products');
    assertSameValue([1, 1, 1], array_column($items, 'is_picked'), 'preview should mark all returned items as picked');
}

function testPublishAndRestoreLiveRecoverFeaturedState(): void
{
    insertHomepageTestData();

    $fixture = createHomepageServiceFixture();
    /** @var HomepageService $service */
    $service = $fixture['service'];
    /** @var ProductRepository $productRepository */
    $productRepository = $fixture['product_repository'];
    /** @var HomepageRepository $homepageRepository */
    $homepageRepository = $fixture['homepage_repository'];

    $service->publish(['nickname' => 'QA']);
    $publishedSnapshot = $homepageRepository->publishedSnapshot();
    $publishedProductSnapshot = [];
    foreach (($publishedSnapshot['featured_pool']['product'] ?? []) as $item) {
        $publishedProductSnapshot[(int) ($item['id'] ?? 0)] = $item;
    }
    assertSameValue(
        ['id' => 11, 'is_home_featured' => 1, 'manual_sort' => 20],
        $publishedProductSnapshot[11] ?? [],
        'publish should snapshot featured pool state for restore-live'
    );
    $service->updateFeaturedItem('product', 11, ['is_home_featured' => 0, 'manual_sort' => 1], ['id' => 9, 'nickname' => 'QA']);
    $service->updateFeaturedItem('product', 12, ['is_home_featured' => 1, 'manual_sort' => 300], ['id' => 9, 'nickname' => 'QA']);

    $service->restoreLive(['nickname' => 'QA']);

    $product11 = $productRepository->find(11);
    $product12 = $productRepository->find(12);

    assertSameValue(1, (int) ($product11['is_home_featured'] ?? -1), 'restoreLive should recover the published featured flag');
    assertSameValue(20, (int) ($product11['manual_sort'] ?? -1), 'restoreLive should recover the published manual_sort');
    assertSameValue(0, (int) ($product12['is_home_featured'] ?? -1), 'restoreLive should remove draft-only featured picks');
    assertSameValue(999, (int) ($product12['manual_sort'] ?? -1), 'restoreLive should recover non-featured item sort from published state');
}

try {
    testPreviewOnlyReturnsPickedItemsAndRespectsOrdering();
    testPublishAndRestoreLiveRecoverFeaturedState();
    echo "homepage publish/preview tests passed.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

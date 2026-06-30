<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "database connection unavailable\n");
    exit(1);
}

$bridge = new \app\service\content\ContentEntityBridge();
$translationRepository = new \app\repository\TranslationRepository();

$now = date('Y-m-d H:i:s');
$operatorId = 1;

$productSeed = [
    [
        'slug' => 'cake-batter-mixer',
        'category_id' => 9,
        'sku' => 'HZ-MIX-200',
        'name_zh' => '蛋糕面糊搅拌机',
        'summary_zh' => '适用于蛋糕、纸杯蛋糕和海绵蛋糕生产前段的稳定搅拌工位。',
        'content_zh' => '<p>适用于蛋糕面糊连续化搅拌，支持配方切换、批次稳定控制与清洁切换。</p><p>适合作为蛋糕自动生产线前段混料模块，帮助工厂提升一致性与交付效率。</p>',
        'seo_title' => '蛋糕面糊搅拌机',
        'seo_keywords' => '蛋糕面糊搅拌机,蛋糕生产设备,烘焙搅拌机',
        'seo_description' => '涵尊蛋糕面糊搅拌机，适用于蛋糕自动生产线前段稳定混料。',
        'en' => [
            'name' => 'Cake Batter Mixer',
            'summary' => 'A stable mixing station for cake, cupcake and sponge cake preparation.',
            'content' => '<p>Designed for continuous cake batter mixing with stable recipe control, quick changeover and easier cleaning.</p><p>It works as a front-end module for automatic cake production lines to improve consistency and delivery efficiency.</p>',
        ],
        'manual_sort' => 97,
    ],
    [
        'slug' => 'bread-proofing-cabinet',
        'category_id' => 3,
        'sku' => 'HZ-PROOF-120',
        'name_zh' => '面包醒发柜',
        'summary_zh' => '用于面包和吐司生产过程中的温湿度稳定醒发。',
        'content_zh' => '<p>适合面包、吐司与甜面团产品的标准化醒发，支持温湿度分区控制。</p><p>可与上游整形、下游烘烤模块衔接，形成顺畅的面包连续生产节拍。</p>',
        'seo_title' => '面包醒发柜',
        'seo_keywords' => '面包醒发柜,吐司生产设备,面包生产线',
        'seo_description' => '涵尊面包醒发柜，提供稳定温湿度控制，适配面包连续生产。',
        'en' => [
            'name' => 'Bread Proofing Cabinet',
            'summary' => 'A controlled proofing module for bread and toast production.',
            'content' => '<p>Built for standardized proofing of bread, toast and sweet dough products with reliable temperature and humidity control.</p><p>It connects smoothly with upstream forming and downstream baking modules in continuous bread production.</p>',
        ],
        'manual_sort' => 96,
    ],
    [
        'slug' => 'servo-filling-depositor',
        'category_id' => 7,
        'sku' => 'HZ-FILL-160',
        'name_zh' => '伺服充填灌装机',
        'summary_zh' => '适合蛋糕、夹心、酱料等多种充填场景的高精度灌装模块。',
        'content_zh' => '<p>支持奶油、果酱、蛋液和流动性面糊等多种物料，适合单机或整线集成。</p><p>伺服控制帮助工厂在速度与重量一致性之间取得更好的平衡。</p>',
        'seo_title' => '伺服充填灌装机',
        'seo_keywords' => '伺服充填灌装机,蛋糕灌装机,食品充填设备',
        'seo_description' => '高精度伺服充填灌装机，适合蛋糕与食品生产线集成。',
        'en' => [
            'name' => 'Servo Filling Depositor',
            'summary' => 'A precise depositing module for cake batter, cream, jam and other fillings.',
            'content' => '<p>Suitable for cream, jam, egg liquid and flowing batter in both stand-alone machines and full lines.</p><p>Servo control improves the balance between running speed and filling weight consistency.</p>',
        ],
        'manual_sort' => 95,
    ],
];

$solutionSeed = [
    [
        'slug' => 'bread-line',
        'category_id' => 1,
        'name_zh' => '面包自动生产线',
        'summary_zh' => '覆盖搅拌、整形、醒发、烘烤到冷却包装的面包整线方案。',
        'content_zh' => '<p>适合标准吐司、餐包与软欧等产品的连续化生产，支持产能规划与模块化扩展。</p><p>方案强调稳定节拍、工艺衔接与海外交付支持。</p>',
        'flow_text_zh' => '搅拌 -> 分块 -> 整形 -> 醒发 -> 烘烤 -> 冷却 -> 包装',
        'capacity_text_zh' => '3000-8000 pcs/h',
        'seo_title' => '面包自动生产线',
        'seo_keywords' => '面包自动生产线,吐司生产线,烘焙整线方案',
        'seo_description' => '涵尊面包自动生产线，适合面包和吐司连续化生产。',
        'en' => [
            'name' => 'Automatic Bread Production Line',
            'summary' => 'A turnkey bread line covering mixing, forming, proofing, baking, cooling and packing.',
            'content' => '<p>Suitable for toast, buns and soft bread products with modular capacity planning and line expansion.</p><p>The solution focuses on stable rhythm, process continuity and overseas delivery support.</p>',
            'flow_text' => 'Mixing -> Dividing -> Forming -> Proofing -> Baking -> Cooling -> Packing',
            'capacity_text' => '3000-8000 pcs/h',
        ],
        'manual_sort' => 97,
    ],
    [
        'slug' => 'biscuit-line',
        'category_id' => 1,
        'name_zh' => '饼干自动生产线',
        'summary_zh' => '面向曲奇、夹心和烘焙饼干产品的自动化整线配置。',
        'content_zh' => '<p>支持配料、成型、烘烤、冷却和包装的连续化布局，适合出口工厂做标准化交付。</p><p>可根据厂房条件配置单层或多段烤炉方案。</p>',
        'flow_text_zh' => '配料 -> 成型 -> 烘烤 -> 冷却 -> 理料 -> 包装',
        'capacity_text_zh' => '500-1500 kg/h',
        'seo_title' => '饼干自动生产线',
        'seo_keywords' => '饼干自动生产线,曲奇生产线,烘焙整线',
        'seo_description' => '涵尊饼干自动生产线，覆盖成型、烘烤到包装的连续化工艺。',
        'en' => [
            'name' => 'Automatic Biscuit Production Line',
            'summary' => 'An automated line for cookies, sandwich biscuits and baked snack products.',
            'content' => '<p>Supports continuous layouts for dosing, forming, baking, cooling and packing, making it suitable for export-oriented bakery factories.</p><p>Single-deck and multi-zone oven setups can be configured for the plant layout.</p>',
            'flow_text' => 'Dosing -> Forming -> Baking -> Cooling -> Feeding -> Packing',
            'capacity_text' => '500-1500 kg/h',
        ],
        'manual_sort' => 96,
    ],
    [
        'slug' => 'central-kitchen-line',
        'category_id' => 1,
        'name_zh' => '中央厨房烘焙线',
        'summary_zh' => '适合连锁门店和中央工厂的半成品烘焙集中生产方案。',
        'content_zh' => '<p>用于集中生产蛋糕胚、面包坯和预制烘焙产品，帮助连锁系统统一品质标准。</p><p>支持分阶段建设和多站点配送节奏设计。</p>',
        'flow_text_zh' => '原料处理 -> 预加工 -> 烘焙 -> 冷却 -> 分装 -> 配送',
        'capacity_text_zh' => 'Custom by project',
        'seo_title' => '中央厨房烘焙线',
        'seo_keywords' => '中央厨房烘焙线,中央工厂方案,烘焙配送',
        'seo_description' => '面向连锁品牌和中央工厂的中央厨房烘焙线方案。',
        'en' => [
            'name' => 'Central Kitchen Bakery Line',
            'summary' => 'A centralized bakery line for chain stores and central factory operations.',
            'content' => '<p>Built for centralized production of cake bases, bread blanks and pre-baked products to keep quality standards consistent across chain operations.</p><p>It supports phased investment and multi-site delivery planning.</p>',
            'flow_text' => 'Raw Material Prep -> Pre-processing -> Baking -> Cooling -> Portioning -> Distribution',
            'capacity_text' => 'Custom by project',
        ],
        'manual_sort' => 95,
    ],
];

$newsSeed = [
    [
        'slug' => 'indonesia-bakery-demo',
        'category_id' => 1,
        'title_zh' => '印尼客户到厂测试蛋糕线',
        'summary_zh' => '围绕蛋糕灌装、烘烤和出料节拍进行了整线测试。',
        'content_zh' => '<p>客户团队在工厂现场验证了蛋糕灌装精度、输送稳定性和烘烤衔接节拍。</p><p>测试完成后同步确认了设备配置和交付节奏。</p>',
        'seo_title' => '印尼客户到厂测试蛋糕线',
        'seo_keywords' => '印尼客户,蛋糕线测试,烘焙设备',
        'seo_description' => '印尼客户到厂测试蛋糕自动生产线，确认配置与交付节奏。',
        'en' => [
            'title' => 'Indonesia Customer Runs Cake Line Trial',
            'summary' => 'The team reviewed depositing, baking and discharge rhythm for the full cake line.',
            'content' => '<p>The customer team verified depositing accuracy, conveyor stability and baking rhythm during the factory trial.</p><p>After the test, both sides confirmed the equipment configuration and delivery schedule.</p>',
        ],
        'manual_sort' => 97,
    ],
    [
        'slug' => 'mexico-line-shipment',
        'category_id' => 1,
        'title_zh' => '墨西哥项目整线发运完成',
        'summary_zh' => '完成烘焙整线装柜与出运，进入海外安装准备阶段。',
        'content_zh' => '<p>本次发运包含上料、成型、烘烤、冷却和包装关键模块，项目团队同步输出安装清单。</p><p>后续将进入远程预调试和现场安装准备阶段。</p>',
        'seo_title' => '墨西哥项目整线发运完成',
        'seo_keywords' => '墨西哥项目,整线发运,烘焙生产线',
        'seo_description' => '墨西哥烘焙整线项目完成发运，进入安装准备阶段。',
        'en' => [
            'title' => 'Mexico Bakery Line Shipment Completed',
            'summary' => 'The turnkey bakery line has been packed and shipped for the next installation phase.',
            'content' => '<p>The shipment covered feeding, forming, baking, cooling and packing modules, with the project team releasing the installation checklist at the same time.</p><p>The next stage will move into remote pre-commissioning and on-site installation preparation.</p>',
        ],
        'manual_sort' => 96,
    ],
    [
        'slug' => 'saudi-factory-visit',
        'category_id' => 1,
        'title_zh' => '沙特客户考察中央厨房方案',
        'summary_zh' => '围绕中央工厂产能规划与门店配送节奏展开方案讨论。',
        'content_zh' => '<p>客户重点关注中央厨房产品结构、设备布局和后续扩线能力。</p><p>双方初步确认了样线测试和方案深化的下一步时间表。</p>',
        'seo_title' => '沙特客户考察中央厨房方案',
        'seo_keywords' => '沙特客户,中央厨房,烘焙方案',
        'seo_description' => '沙特客户到厂考察中央厨房烘焙线方案。',
        'en' => [
            'title' => 'Saudi Customer Reviews Central Kitchen Plan',
            'summary' => 'The discussion focused on capacity planning and store distribution rhythm for a central bakery factory.',
            'content' => '<p>The customer focused on product mix, equipment layout and future expansion capability for a central kitchen project.</p><p>Both sides aligned on the next schedule for pilot testing and solution refinement.</p>',
        ],
        'manual_sort' => 95,
    ],
];

$caseSeed = [
    [
        'slug' => 'mexico-cupcake-project',
        'category_id' => 2,
        'country_code' => 'MX',
        'title_zh' => '墨西哥纸杯蛋糕项目交付',
        'summary_zh' => '面向连锁门店配送的纸杯蛋糕自动化项目完成验收。',
        'content_zh' => '<p>项目覆盖搅拌、灌装、烘烤、冷却和包装，重点解决了多规格切换效率问题。</p><p>客户通过该项目建立了稳定的中央工厂供货节奏。</p>',
        'case_tags' => '纸杯蛋糕,中央工厂,出口',
        'seo_title' => '墨西哥纸杯蛋糕项目交付',
        'seo_keywords' => '墨西哥案例,纸杯蛋糕项目,烘焙整线',
        'seo_description' => '墨西哥纸杯蛋糕自动化项目案例，支持中央工厂稳定供货。',
        'en' => [
            'title' => 'Mexico Cupcake Project Delivery',
            'summary' => 'An automated cupcake project for chain store distribution has been accepted.',
            'content' => '<p>The project covered mixing, depositing, baking, cooling and packing, with a strong focus on faster size changeover.</p><p>It helped the customer establish a stable central factory supply rhythm.</p>',
        ],
        'manual_sort' => 97,
        'product_slug' => 'servo-filling-depositor',
        'solution_slug' => 'central-kitchen-line',
    ],
    [
        'slug' => 'saudi-bread-line-project',
        'category_id' => 3,
        'country_code' => 'SA',
        'title_zh' => '沙特面包生产线项目落地',
        'summary_zh' => '完成吐司与餐包兼容生产线的安装调试。',
        'content_zh' => '<p>该项目面向区域配送工厂，重点关注醒发稳定性、烘烤节拍和包装衔接。</p><p>调试完成后顺利进入试生产阶段。</p>',
        'case_tags' => '面包生产线,海外安装,吐司',
        'seo_title' => '沙特面包生产线项目落地',
        'seo_keywords' => '沙特案例,面包生产线,吐司项目',
        'seo_description' => '沙特面包生产线项目完成安装调试并进入试生产。',
        'en' => [
            'title' => 'Saudi Bread Line Project Commissioned',
            'summary' => 'A bread line for toast and buns has completed installation and commissioning.',
            'content' => '<p>This project served a regional distribution bakery and focused on proofing stability, baking rhythm and packing continuity.</p><p>After commissioning, the factory moved smoothly into trial production.</p>',
        ],
        'manual_sort' => 96,
        'product_slug' => 'bread-proofing-cabinet',
        'solution_slug' => 'bread-line',
    ],
    [
        'slug' => 'singapore-central-kitchen-project',
        'category_id' => 2,
        'country_code' => 'SG',
        'title_zh' => '新加坡中央厨房项目验收',
        'summary_zh' => '面向连锁甜品门店的中央厨房烘焙线完成交付。',
        'content_zh' => '<p>项目采用分阶段建设方式，优先上线蛋糕胚和甜品预制模块。</p><p>后续预留冷链配送和门店扩张所需的扩展接口。</p>',
        'case_tags' => '中央厨房,甜品工厂,连锁门店',
        'seo_title' => '新加坡中央厨房项目验收',
        'seo_keywords' => '新加坡案例,中央厨房,甜品工厂',
        'seo_description' => '新加坡中央厨房烘焙线项目完成验收，服务连锁甜品门店。',
        'en' => [
            'title' => 'Singapore Central Kitchen Project Accepted',
            'summary' => 'A bakery line for a dessert chain central kitchen has been delivered.',
            'content' => '<p>The project used a phased build strategy, launching cake base and dessert pre-processing modules first.</p><p>Expansion interfaces were reserved for cold-chain distribution and future store growth.</p>',
        ],
        'manual_sort' => 95,
        'product_slug' => 'cake-batter-mixer',
        'solution_slug' => 'central-kitchen-line',
    ],
];

$findIdBySlug = static function (PDO $pdo, string $table, string $slug): int {
    $statement = $pdo->prepare(sprintf('SELECT id FROM %s WHERE slug = :slug LIMIT 1', $table));
    $statement->execute(['slug' => $slug]);

    return (int) ($statement->fetchColumn() ?: 0);
};

$fetchOne = static function (PDO $pdo, string $sql, array $params = []): ?array {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
};

$upsertTranslation = static function (\app\service\content\ContentEntityBridge $bridge, \app\repository\TranslationRepository $translationRepository, string $entityType, int $entityId, array $payload): void {
    $bridge->upsertTranslation($entityType, $entityId, 'en', $payload, 'completed');
    $bridge->updateTranslationStatus($entityType, $entityId, 'completed');
    $translationRepository->upsertJob($entityType, $entityId, 'en', 'completed', null);
};

$upsertProduct = static function (PDO $pdo, array $item, string $now, int $operatorId) use ($findIdBySlug): int {
    $existingId = $findIdBySlug($pdo, 'products', $item['slug']);
    $payload = [
        'category_id' => $item['category_id'],
        'sku' => $item['sku'],
        'name_zh' => $item['name_zh'],
        'summary_zh' => $item['summary_zh'],
        'content_zh' => $item['content_zh'],
        'business_status' => 'on_sale',
        'publish_status' => 'published',
        'translation_status' => 'completed',
        'seo_status' => 'generated',
        'is_home_featured' => 1,
        'manual_sort' => $item['manual_sort'],
        'slug' => $item['slug'],
        'seo_title' => $item['seo_title'],
        'seo_keywords' => $item['seo_keywords'],
        'seo_description' => $item['seo_description'],
        'publish_time' => $now,
        'created_by' => $operatorId,
        'updated_by' => $operatorId,
    ];

    if ($existingId > 0) {
        $statement = $pdo->prepare(
            'UPDATE products
             SET category_id = :category_id, sku = :sku, name_zh = :name_zh, summary_zh = :summary_zh, content_zh = :content_zh,
                 business_status = :business_status, publish_status = :publish_status, translation_status = :translation_status,
                 seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug,
                 seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time,
                 updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $existingId,
            'category_id' => $payload['category_id'],
            'sku' => $payload['sku'],
            'name_zh' => $payload['name_zh'],
            'summary_zh' => $payload['summary_zh'],
            'content_zh' => $payload['content_zh'],
            'business_status' => $payload['business_status'],
            'publish_status' => $payload['publish_status'],
            'translation_status' => $payload['translation_status'],
            'seo_status' => $payload['seo_status'],
            'is_home_featured' => $payload['is_home_featured'],
            'manual_sort' => $payload['manual_sort'],
            'slug' => $payload['slug'],
            'seo_title' => $payload['seo_title'],
            'seo_keywords' => $payload['seo_keywords'],
            'seo_description' => $payload['seo_description'],
            'publish_time' => $payload['publish_time'],
            'updated_by' => $payload['updated_by'],
        ]);

        return $existingId;
    }

    $statement = $pdo->prepare(
        'INSERT INTO products (
            category_id, sku, name_zh, summary_zh, content_zh, business_status, publish_status, translation_status,
            seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time,
            created_by, updated_by, created_at, updated_at
        ) VALUES (
            :category_id, :sku, :name_zh, :summary_zh, :content_zh, :business_status, :publish_status, :translation_status,
            :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time,
            :created_by, :updated_by, NOW(), NOW()
        )'
    );
    $statement->execute($payload);

    return (int) $pdo->lastInsertId();
};

$upsertSolution = static function (PDO $pdo, array $item, string $now, int $operatorId) use ($findIdBySlug): int {
    $existingId = $findIdBySlug($pdo, 'solutions', $item['slug']);
    $payload = [
        'category_id' => $item['category_id'],
        'name_zh' => $item['name_zh'],
        'summary_zh' => $item['summary_zh'],
        'content_zh' => $item['content_zh'],
        'flow_text_zh' => $item['flow_text_zh'],
        'capacity_text_zh' => $item['capacity_text_zh'],
        'manual_asset_id' => null,
        'publish_status' => 'published',
        'translation_status' => 'completed',
        'seo_status' => 'generated',
        'is_home_featured' => 1,
        'manual_sort' => $item['manual_sort'],
        'slug' => $item['slug'],
        'seo_title' => $item['seo_title'],
        'seo_keywords' => $item['seo_keywords'],
        'seo_description' => $item['seo_description'],
        'publish_time' => $now,
        'created_by' => $operatorId,
        'updated_by' => $operatorId,
    ];

    if ($existingId > 0) {
        $statement = $pdo->prepare(
            'UPDATE solutions
             SET category_id = :category_id, name_zh = :name_zh, summary_zh = :summary_zh, content_zh = :content_zh,
                 flow_text_zh = :flow_text_zh, capacity_text_zh = :capacity_text_zh, manual_asset_id = :manual_asset_id,
                 publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status,
                 is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title,
                 seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time,
                 updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $existingId,
            'category_id' => $payload['category_id'],
            'name_zh' => $payload['name_zh'],
            'summary_zh' => $payload['summary_zh'],
            'content_zh' => $payload['content_zh'],
            'flow_text_zh' => $payload['flow_text_zh'],
            'capacity_text_zh' => $payload['capacity_text_zh'],
            'manual_asset_id' => $payload['manual_asset_id'],
            'publish_status' => $payload['publish_status'],
            'translation_status' => $payload['translation_status'],
            'seo_status' => $payload['seo_status'],
            'is_home_featured' => $payload['is_home_featured'],
            'manual_sort' => $payload['manual_sort'],
            'slug' => $payload['slug'],
            'seo_title' => $payload['seo_title'],
            'seo_keywords' => $payload['seo_keywords'],
            'seo_description' => $payload['seo_description'],
            'publish_time' => $payload['publish_time'],
            'updated_by' => $payload['updated_by'],
        ]);

        return $existingId;
    }

    $statement = $pdo->prepare(
        'INSERT INTO solutions (
            category_id, name_zh, summary_zh, content_zh, flow_text_zh, capacity_text_zh, manual_asset_id,
            publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title,
            seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :category_id, :name_zh, :summary_zh, :content_zh, :flow_text_zh, :capacity_text_zh, :manual_asset_id,
            :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title,
            :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW()
        )'
    );
    $statement->execute($payload);

    return (int) $pdo->lastInsertId();
};

$upsertLegacyArticle = static function (PDO $pdo, array $item, string $contentType, string $now, int $operatorId, array $relationIds = []) use ($fetchOne): int {
    $existing = $fetchOne(
        $pdo,
        'SELECT id FROM articles WHERE slug = :slug AND content_type = :content_type LIMIT 1',
        ['slug' => $item['slug'], 'content_type' => $contentType]
    );
    $payload = [
        'category_id' => $item['category_id'],
        'content_type' => $contentType,
        'migrated_to_new_tables' => 1,
        'title_zh' => $item['title_zh'],
        'summary_zh' => $item['summary_zh'],
        'content_zh' => $item['content_zh'],
        'country_code' => $item['country_code'] ?? null,
        'case_tags' => $item['case_tags'] ?? null,
        'related_solution_ids' => isset($relationIds['solution_id']) ? json_encode([(int) $relationIds['solution_id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
        'related_product_ids' => isset($relationIds['product_id']) ? json_encode([(int) $relationIds['product_id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
        'publish_status' => 'published',
        'translation_status' => 'completed',
        'seo_status' => 'generated',
        'is_home_featured' => 1,
        'manual_sort' => $item['manual_sort'],
        'slug' => $item['slug'],
        'seo_title' => $item['seo_title'],
        'seo_keywords' => $item['seo_keywords'],
        'seo_description' => $item['seo_description'],
        'publish_time' => $now,
        'created_by' => $operatorId,
        'updated_by' => $operatorId,
    ];

    if ($existing !== null) {
        $statement = $pdo->prepare(
            'UPDATE articles
             SET category_id = :category_id, content_type = :content_type, migrated_to_new_tables = :migrated_to_new_tables,
                 title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh, country_code = :country_code,
                 case_tags = :case_tags, related_solution_ids = :related_solution_ids, related_product_ids = :related_product_ids,
                 publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status,
                 is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title,
                 seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time,
                 updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => (int) $existing['id'],
            'category_id' => $payload['category_id'],
            'content_type' => $payload['content_type'],
            'migrated_to_new_tables' => $payload['migrated_to_new_tables'],
            'title_zh' => $payload['title_zh'],
            'summary_zh' => $payload['summary_zh'],
            'content_zh' => $payload['content_zh'],
            'country_code' => $payload['country_code'],
            'case_tags' => $payload['case_tags'],
            'related_solution_ids' => $payload['related_solution_ids'],
            'related_product_ids' => $payload['related_product_ids'],
            'publish_status' => $payload['publish_status'],
            'translation_status' => $payload['translation_status'],
            'seo_status' => $payload['seo_status'],
            'is_home_featured' => $payload['is_home_featured'],
            'manual_sort' => $payload['manual_sort'],
            'slug' => $payload['slug'],
            'seo_title' => $payload['seo_title'],
            'seo_keywords' => $payload['seo_keywords'],
            'seo_description' => $payload['seo_description'],
            'publish_time' => $payload['publish_time'],
            'updated_by' => $payload['updated_by'],
        ]);

        return (int) $existing['id'];
    }

    $statement = $pdo->prepare(
        'INSERT INTO articles (
            category_id, content_type, migrated_to_new_tables, title_zh, summary_zh, content_zh, country_code, case_tags,
            related_solution_ids, related_product_ids, publish_status, translation_status, seo_status, is_home_featured,
            manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :category_id, :content_type, :migrated_to_new_tables, :title_zh, :summary_zh, :content_zh, :country_code, :case_tags,
            :related_solution_ids, :related_product_ids, :publish_status, :translation_status, :seo_status, :is_home_featured,
            :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW()
        )'
    );
    $statement->execute($payload);

    return (int) $pdo->lastInsertId();
};

$upsertMirrorContent = static function (PDO $pdo, string $table, int $id, array $item, string $now, int $operatorId, array $relationIds = []): void {
    $isCase = $table === 'cases';
    $existing = $pdo->prepare(sprintf('SELECT id FROM %s WHERE id = :id LIMIT 1', $table));
    $existing->execute(['id' => $id]);
    $exists = (bool) $existing->fetchColumn();

    $payload = [
        'id' => $id,
        'category_id' => $item['category_id'],
        'title_zh' => $item['title_zh'],
        'summary_zh' => $item['summary_zh'],
        'content_zh' => $item['content_zh'],
        'country_code' => $isCase ? ($item['country_code'] ?? '') : null,
        'case_tags' => $isCase ? ($item['case_tags'] ?? '') : null,
        'related_solution_ids' => $isCase && isset($relationIds['solution_id']) ? json_encode([(int) $relationIds['solution_id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
        'related_product_ids' => $isCase && isset($relationIds['product_id']) ? json_encode([(int) $relationIds['product_id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
        'publish_status' => 'published',
        'translation_status' => 'completed',
        'seo_status' => 'generated',
        'is_home_featured' => 1,
        'manual_sort' => $item['manual_sort'],
        'slug' => $item['slug'],
        'seo_title' => $item['seo_title'],
        'seo_keywords' => $item['seo_keywords'],
        'seo_description' => $item['seo_description'],
        'publish_time' => $now,
        'created_by' => $operatorId,
        'updated_by' => $operatorId,
    ];

    if ($exists) {
        if ($isCase) {
            $statement = $pdo->prepare(
                'UPDATE cases
                 SET category_id = :category_id, title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh,
                     country_code = :country_code, case_tags = :case_tags, related_solution_ids = :related_solution_ids,
                     related_product_ids = :related_product_ids, publish_status = :publish_status, translation_status = :translation_status,
                     seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug,
                     seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description,
                     publish_time = :publish_time, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
        } else {
            $statement = $pdo->prepare(
                'UPDATE news
                 SET category_id = :category_id, title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh,
                     publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status,
                     is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title,
                     seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time,
                     updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
        }
        $statement->execute($isCase ? $payload : [
            'id' => $payload['id'],
            'category_id' => $payload['category_id'],
            'title_zh' => $payload['title_zh'],
            'summary_zh' => $payload['summary_zh'],
            'content_zh' => $payload['content_zh'],
            'publish_status' => $payload['publish_status'],
            'translation_status' => $payload['translation_status'],
            'seo_status' => $payload['seo_status'],
            'is_home_featured' => $payload['is_home_featured'],
            'manual_sort' => $payload['manual_sort'],
            'slug' => $payload['slug'],
            'seo_title' => $payload['seo_title'],
            'seo_keywords' => $payload['seo_keywords'],
            'seo_description' => $payload['seo_description'],
            'publish_time' => $payload['publish_time'],
            'updated_by' => $payload['updated_by'],
        ]);

        return;
    }

    if ($isCase) {
        $statement = $pdo->prepare(
            'INSERT INTO cases (
                id, category_id, title_zh, summary_zh, content_zh, country_code, case_tags, related_solution_ids, related_product_ids,
                publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords,
                seo_description, publish_time, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :id, :category_id, :title_zh, :summary_zh, :content_zh, :country_code, :case_tags, :related_solution_ids, :related_product_ids,
                :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords,
                :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW()
            )'
        );
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO news (
                id, category_id, title_zh, summary_zh, content_zh, publish_status, translation_status, seo_status, is_home_featured,
                manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :id, :category_id, :title_zh, :summary_zh, :content_zh, :publish_status, :translation_status, :seo_status, :is_home_featured,
                :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW()
            )'
        );
    }
    $statement->execute($isCase ? $payload : [
        'id' => $payload['id'],
        'category_id' => $payload['category_id'],
        'title_zh' => $payload['title_zh'],
        'summary_zh' => $payload['summary_zh'],
        'content_zh' => $payload['content_zh'],
        'publish_status' => $payload['publish_status'],
        'translation_status' => $payload['translation_status'],
        'seo_status' => $payload['seo_status'],
        'is_home_featured' => $payload['is_home_featured'],
        'manual_sort' => $payload['manual_sort'],
        'slug' => $payload['slug'],
        'seo_title' => $payload['seo_title'],
        'seo_keywords' => $payload['seo_keywords'],
        'seo_description' => $payload['seo_description'],
        'publish_time' => $payload['publish_time'],
        'created_by' => $payload['created_by'],
        'updated_by' => $payload['updated_by'],
    ]);
};

$productIds = [];
foreach ($productSeed as $item) {
    $productId = $upsertProduct($pdo, $item, $now, $operatorId);
    $productIds[$item['slug']] = $productId;
    $upsertTranslation($bridge, $translationRepository, 'product', $productId, $item['en']);
}

$solutionIds = [];
foreach ($solutionSeed as $item) {
    $solutionId = $upsertSolution($pdo, $item, $now, $operatorId);
    $solutionIds[$item['slug']] = $solutionId;
    $upsertTranslation($bridge, $translationRepository, 'solution', $solutionId, $item['en']);
}

foreach ($newsSeed as $item) {
    $articleId = $upsertLegacyArticle($pdo, $item, 'news', $now, $operatorId);
    $upsertTranslation($bridge, $translationRepository, 'article', $articleId, $item['en']);
    $upsertMirrorContent($pdo, 'news', $articleId, $item, $now, $operatorId);
    $upsertTranslation($bridge, $translationRepository, 'news', $articleId, $item['en']);
}

foreach ($caseSeed as $item) {
    $relationIds = [
        'product_id' => (int) ($productIds[$item['product_slug']] ?? 0),
        'solution_id' => (int) ($solutionIds[$item['solution_slug']] ?? 0),
    ];
    $articleId = $upsertLegacyArticle($pdo, $item, 'case', $now, $operatorId, $relationIds);
    $upsertTranslation($bridge, $translationRepository, 'article', $articleId, $item['en']);
    $upsertMirrorContent($pdo, 'cases', $articleId, $item, $now, $operatorId, $relationIds);
    $upsertTranslation($bridge, $translationRepository, 'case', $articleId, $item['en']);
}

$summary = [
    'products' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE publish_status = 'published'")->fetchColumn(),
    'solutions' => (int) $pdo->query("SELECT COUNT(*) FROM solutions WHERE publish_status = 'published'")->fetchColumn(),
    'articles_news' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE publish_status = 'published' AND content_type = 'news'")->fetchColumn(),
    'articles_cases' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE publish_status = 'published' AND content_type = 'case'")->fetchColumn(),
    'news' => (int) $pdo->query("SELECT COUNT(*) FROM news WHERE publish_status = 'published'")->fetchColumn(),
    'cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE publish_status = 'published'")->fetchColumn(),
];

fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

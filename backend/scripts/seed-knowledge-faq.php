<?php

declare(strict_types=1);

/**
 * 导入国际客服 FAQ 到知识库
 *
 * 用法: php backend/scripts/seed-knowledge-faq.php
 */

use app\common\bootstrap\Autoloader;
use app\common\bootstrap\EnvLoader;
use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use app\service\knowledge\KnowledgeService;

$basePath = dirname(__DIR__);

require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';

Autoloader::register($basePath);
EnvLoader::load($basePath . '/.env');

$configRepository = ConfigRepository::instance();
$configRepository->load($basePath . '/config');
DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));

$faqContent = <<<'FAQ'
# HANZUN FAQ – International Customer Service

## 1. Are you a manufacturer?
Yes, we have a manufacturer with 15+ years of experience.

## 2. How much for a sample?
Please tell me the samples and other details you want, and I will quote you as soon as possible.

## 3. How long is your production cycle?
The production cycle for each product is different. If you need further clarification, send me the product link, and I will reply as soon as possible.

## 4. Can I customize products?
Yes. Let me know the specific customization requirements, and I will get back to you as soon as possible.

## 5. Can I customize the logo and packaging?
Yes, we can customize products based on your design and packaging requirements. Please send your detailed requirements to us.

## 6. What is the price?
For an accurate quote, please provide us with more details like logo or packaging customization requirements, quantity preference, shipping address, etc.

## 7. What specific customization options do you offer for your products?
We offer customizations such as size, color, material, and branding. Please specify your requirements.

## 8. Can you provide a product catalogue?
Yes. Here is a list of our most popular products.

## 9. How much time do you need to prepare the quotation for our order?
Each order has its own unique timeline. Typically, I will need approximately 3 business days to prepare your quotation.

## 10. What specific product details do you need for an accurate quotation?
Please provide the product specifications, quantity, and delivery location for an accurate quote.

## 11. What is the estimated delivery time for the original brand new product?
The estimated delivery time for the original brand new product is 2-4 weeks after order confirmation.

## 12. Who covers the shipping costs?
Typically, the buyer covers the shipping costs, but we can arrange for prepaid shipping if needed.

## 13. Do you have your own shipping agent for handling international shipments?
Yes, we have a dedicated shipping agent who manages all international shipments to ensure timely delivery.

## 14. Can I request product samples for testing before placing a bulk order?
Yes, we can provide samples. Please specify the product and quantity, and we'll arrange it.

## 15. Can you deliver product samples to my warehouse?
Yes, we can send a product sample to your warehouse. Please provide the warehouse address and any specific product details, and we will arrange the shipment.

## 16. Can I receive a sample before approving production for my order?
Yes, we provide a sample for your approval before starting production on your order.

## 17. Are samples available for free, and what are the express shipping fees?
Not free. We are a professional manufacturer of customized food machinery. Please discuss in detail. Thank you.

## 18. How can I get accurate quote for different shipping options?
Please send us your detailed address so that we can provide you with the shipping options and relevant costs.

## 19. Which shipping methods do you use?
We offer sea, air, and land freight as well as express delivery. The specific options depend on the quantity of the order.

## 20. What is the minimum order quantity?
Please specify the product type and any custom specifications you need so we can assist you further.

## 21. Can you compare your product specifications to my specific requirements?
Please provide your specific requirements, and I will compare them with our product specifications.

## 22. What is the voltage of the equipment?
220V or 380V. 220V uses a standard plug. 380V requires professional wiring (ordinary electricians are sufficient).
FAQ;

$title = 'International Customer Service FAQ (22 Items)';

try {
    $service = new KnowledgeService();
    $existing = $service->listDocuments([
        'keyword' => 'International Customer Service FAQ',
        'page' => 1,
        'page_size' => 10,
    ]);

    $items = is_array($existing['items'] ?? null) ? $existing['items'] : [];
    foreach ($items as $item) {
        if (str_contains((string) ($item['title'] ?? ''), 'International Customer Service FAQ')) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $document = $service->reindexDocument($id, $faqContent);
                echo "updated knowledge document #{$id}, chunks={$document['chunk_count']}, status={$document['status']}\n";
                exit(0);
            }
        }
    }

    $document = $service->createManual([
        'title' => $title,
        'content' => $faqContent,
        'language_code' => 'en',
        'tags' => ['category' => 'faq', 'audience' => 'international'],
    ]);

    echo "created knowledge document #{$document['id']}, chunks={$document['chunk_count']}, status={$document['status']}\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'seed failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

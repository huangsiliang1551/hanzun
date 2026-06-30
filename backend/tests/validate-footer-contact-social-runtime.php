<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

chdir($backendRoot);
require_once $backendRoot . '/tests/test-bootstrap.php';

use app\repository\ContactRepository;
use app\service\StaticPublisher;

$contactRepository = new ContactRepository();
$createdId = 0;
$createdFieldTypeId = 0;

try {
    $linkedinFieldType = null;
    foreach ($contactRepository->listFieldTypes() as $fieldType) {
        if ((string) ($fieldType['field_key'] ?? '') === 'linkedin') {
            $linkedinFieldType = $fieldType;
            break;
        }
    }

    if ($linkedinFieldType === null) {
        $linkedinFieldType = $contactRepository->createFieldType([
            'field_key' => 'linkedin',
            'name_zh' => 'LinkedIn',
            'icon' => 'link',
            'validation_rule' => 'url',
            'sort' => 97,
            'is_enabled' => 1,
        ]);
        $createdFieldTypeId = (int) ($linkedinFieldType['id'] ?? 0);
    }

    $linkedinFieldTypeId = (int) ($linkedinFieldType['id'] ?? 0);
    if ($linkedinFieldTypeId <= 0) {
        throw new RuntimeException('linkedin field type unavailable');
    }

    $created = $contactRepository->create([
        'field_type_id' => $linkedinFieldTypeId,
        'label_zh' => 'LinkedIn Showcase',
        'value' => 'https://linkedin.example.com/hanzun-footer-test',
        'description_zh' => 'runtime footer social validation',
        'display_scope' => 'footer',
        'sort' => 1000,
        'is_enabled' => 1,
    ]);
    $createdId = (int) ($created['id'] ?? 0);

    $publisher = new StaticPublisher();
    $reflection = new ReflectionClass($publisher);
    $method = $reflection->getMethod('renderFooterContactCardsHtml');
    $method->setAccessible(true);

    $html = (string) $method->invoke($publisher, 'en');

    $issues = [];
    if (!str_contains($html, 'https://linkedin.example.com/hanzun-footer-test')) {
        $issues[] = 'footer contact cards must render linkedin href from Contact Center footer scope';
    }
    if (!str_contains($html, 'LinkedIn')) {
        $issues[] = 'footer contact cards must expose linkedin label';
    }

    if ($issues !== []) {
        fwrite(STDERR, "Footer contact social validation failed:\n- " . implode("\n- ", $issues) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "Footer contact social validation passed.\n");
} finally {
    if ($createdId > 0) {
        $contactRepository->delete($createdId);
    }
    if ($createdFieldTypeId > 0) {
        $contactRepository->deleteFieldType($createdFieldTypeId);
    }
}

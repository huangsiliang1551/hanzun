<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/service/StaticPublisher.php');
if ($source === false || $source === '') {
    fwrite(STDERR, "StaticPublisher source is not readable.
");
    exit(1);
}

$issues = [];

foreach ([
    'validateFullBuildStaging' => 'full build must validate staging before deploy',
    'expectedFullBuildLanguageCodes' => 'full build must calculate expected language directories before deploy',
    'full build staging directory is missing; publish stopped to keep existing static pages' => 'missing staging directory must stop publish',
    'full build staging language directory is missing; publish stopped to keep existing static pages' => 'missing language directory must stop publish',
    'full build staging language homepage is missing; publish stopped to keep existing static pages' => 'missing language homepage must stop publish',
    'full build staging root file is missing; existing root file was kept' => 'missing root files must keep old root files',
    'full build staging public directory is missing; existing public directory was kept' => 'missing public directories must keep old directories',
] as $needle => $message) {
    if (!str_contains($source, $needle)) {
        $issues[] = $message;
    }
}

$deployPos = strpos($source, 'private function deployFullBuildOutputs');
$validatePos = strpos($source, '$this->validateFullBuildStaging($stagingDir, $codes)');
$syncPos = strpos($source, '$this->syncDirectory($sourceDir, $targetDir)', $deployPos ?: 0);
if ($deployPos === false || $validatePos === false || $syncPos === false || !($deployPos < $validatePos && $validatePos < $syncPos)) {
    $issues[] = 'full build must validate staging before syncing directories';
}

$unsafeDelete = "if (is_dir(\$targetDir)) {
                \$this->removeDirectory(\$targetDir);
            }";
if (str_contains($source, $unsafeDelete)) {
    $issues[] = 'full build deploy must not delete a language directory when its staging source is missing';
}

if ($issues !== []) {
    fwrite(STDERR, "Site build staging deploy contract failed:
 - " . implode("
 - ", $issues) . "
");
    exit(1);
}

fwrite(STDOUT, "Site build staging deploy contract passed.
");

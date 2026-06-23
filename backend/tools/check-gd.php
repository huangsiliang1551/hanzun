<?php
echo 'GD: ' . (extension_loaded('gd') ? 'YES' : 'NO') . PHP_EOL;
echo 'PNG createfrompng: ' . (function_exists('imagecreatefrompng') ? 'YES' : 'NO') . PHP_EOL;
echo 'PNG imagepng: ' . (function_exists('imagepng') ? 'YES' : 'NO') . PHP_EOL;
$dir = __DIR__ . '/public/uploads/images';
echo 'Uploads dir: ' . (is_dir($dir) ? 'YES' : 'NO') . PHP_EOL;
if (is_dir($dir)) {
    $files = glob($dir . '/*');
    echo 'Uploads count: ' . count($files) . PHP_EOL;
}
$tdir = __DIR__ . '/public/uploads/thumbs';
echo 'Thumbs dir: ' . (is_dir($tdir) ? 'YES' : 'NO') . PHP_EOL;
if (is_dir($tdir)) {
    $tfiles = glob($tdir . '/*');
    echo 'Thumbs count: ' . count($tfiles) . PHP_EOL;
}

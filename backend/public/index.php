<?php

declare(strict_types=1);

use app\common\bootstrap\Application;

require_once dirname(__DIR__) . '/app/common/bootstrap/Autoloader.php';
require_once dirname(__DIR__) . '/app/common/bootstrap/EnvLoader.php';
require_once dirname(__DIR__) . '/app/common/bootstrap/Application.php';
require_once dirname(__DIR__) . '/app/common/bootstrap/helpers.php';

$application = Application::boot(dirname(__DIR__));
$application->run();

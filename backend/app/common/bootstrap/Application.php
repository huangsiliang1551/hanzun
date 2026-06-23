<?php

declare(strict_types=1);

namespace app\common\bootstrap;

use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use app\common\http\Request;
use app\common\http\RequestContext;
use app\common\http\ResponseEmitter;
use app\common\http\Router;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly Request $request
    ) {
    }

    public static function boot(string $basePath): self
    {
        require_once $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'helpers.php';

        Autoloader::register($basePath);
        EnvLoader::load($basePath . DIRECTORY_SEPARATOR . '.env');

        $configRepository = ConfigRepository::instance();
        $configRepository->load($basePath . DIRECTORY_SEPARATOR . 'config');
        DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));

        $routes = [];
        $publicRouteFile = $basePath . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR . 'publicapi.php';
        if (is_file($publicRouteFile)) {
            $routes = array_merge($routes, require $publicRouteFile);
        }

        $adminRouteFile = $basePath . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR . 'adminapi.php';
        if (is_file($adminRouteFile)) {
            $routes = array_merge($routes, require $adminRouteFile);
        }

        $router = new Router($routes);

        return new self($router, Request::capture());
    }

    public function run(): void
    {
        if ($this->request->method() === 'OPTIONS') {
            ResponseEmitter::noContent();

            return;
        }

        try {
            RequestContext::setRequest($this->request);
            $result = $this->router->dispatch($this->request);
            ResponseEmitter::json($result, 200);
        } catch (BusinessException $exception) {
            // FIX-13: Return 401 for authentication failures
            $errorCode = $exception->getErrorCode();
            $statusCode = in_array($errorCode, [ErrorCode::UNAUTHORIZED, ErrorCode::INVALID_REFRESH_TOKEN, ErrorCode::USER_DISABLED], true) ? 401 : 200;

            ResponseEmitter::json([
                'code' => $errorCode,
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => $exception->getMeta(),
                'request_id' => $this->request->requestId(),
                'timestamp' => time(),
            ], $statusCode);
        } catch (Throwable $exception) {
            // FIX-05: Hide exception details in production
            $debug = (bool) env('APP_DEBUG', false);
            $message = $debug ? $exception->getMessage() : '服务器内部错误';

            // Log the real exception for debugging
            error_log(sprintf(
                '[FATAL] Uncaught %s: %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));

            ResponseEmitter::json([
                'code' => 50001,
                'message' => $message,
                'data' => null,
                'meta' => [],
                'request_id' => $this->request->requestId(),
                'timestamp' => time(),
            ], 500);
        }
    }
}

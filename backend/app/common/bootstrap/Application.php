<?php

declare(strict_types=1);

namespace app\common\bootstrap;

use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use app\common\exception\BusinessException;
use app\common\http\Request;
use app\common\http\RequestContext;
use app\common\http\ResponseEmitter;
use app\common\http\Router;
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
            ResponseEmitter::json([
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => $exception->getMeta(),
                'request_id' => $this->request->requestId(),
                'timestamp' => time(),
            ], $this->statusFromBusinessException($exception));
        } catch (Throwable $exception) {
            $debug = (bool) env('APP_DEBUG', false);
            $message = $debug ? $exception->getMessage() : 'Internal server error.';

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
        } finally {
            RequestContext::clear();
        }
    }

    private function statusFromBusinessException(BusinessException $exception): int
    {
        $errorCode = $exception->getErrorCode();

        return match (true) {
            in_array($errorCode, [
                ErrorCode::UNAUTHORIZED,
                ErrorCode::INVALID_REFRESH_TOKEN,
                ErrorCode::USER_DISABLED,
            ], true) => 401,
            in_array($errorCode, [
                ErrorCode::FORBIDDEN,
                ErrorCode::ACTION_FORBIDDEN,
            ], true) => 403,
            $errorCode === ErrorCode::NOT_FOUND => 404,
            $errorCode === ErrorCode::ALREADY_EXISTS => 409,
            $errorCode === ErrorCode::INVALID_PARAMS => 422,
            $errorCode === 429 => 429,
            $errorCode === ErrorCode::INTERNAL_ERROR => 500,
            default => 400,
        };
    }
}

<?php

declare(strict_types=1);

namespace app\enum;

final class ErrorCode
{
    public const SUCCESS = 0;
    public const INVALID_PARAMS = 42201;
    public const UNAUTHORIZED = 40101;
    public const INVALID_REFRESH_TOKEN = 40102;
    public const USER_DISABLED = 40103;
    public const FORBIDDEN = 40301;
    public const ACTION_FORBIDDEN = 40302;
    public const NOT_FOUND = 40401;
    public const ALREADY_EXISTS = 40901;
    public const INVALID_STATUS_TRANSITION = 40902;
    public const UNSUPPORTED_FILE_TYPE = 54001;
    public const FILE_TOO_LARGE = 54002;
    public const UPLOAD_FAILED = 54003;
    public const TASK_DISPATCH_FAILED = 55001;
    public const INTERNAL_ERROR = 50001;
}

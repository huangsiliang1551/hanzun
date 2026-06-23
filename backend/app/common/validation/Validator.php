<?php

declare(strict_types=1);

namespace app\common\validation;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;

/**
 * Lightweight input validator for Controller layer.
 * FIX-24: Provide unified input validation.
 *
 * Usage:
 *   Validator::validate($request->input(), [
 *       'name'     => 'required|max:64',
 *       'email'    => 'email',
 *       'age'      => 'int|min:1|max:150',
 *       'status'   => 'in:active,inactive',
 *   ]);
 */
final class Validator
{
    /**
     * @var array<string, string> Validation errors [field => message]
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @return static
     */
    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $instance->applyRules($field, $value, $rulesList);
        }
        return $instance;
    }

    /**
     * Throw BusinessException if validation fails.
     */
    public function validate(): void
    {
        if (!empty($this->errors)) {
            $firstError = reset($this->errors);
            throw new BusinessException($firstError, ErrorCode::INVALID_PARAMS);
        }
    }

    /**
     * Check if validation passes.
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all validation errors.
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRules(string $field, mixed $value, array $rules): void
    {
        $label = $field;
        $stringValue = is_scalar($value) ? (string) $value : '';

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            $ruleName = $parts[0];
            $ruleParam = $parts[1] ?? null;

            switch ($ruleName) {
                case 'required':
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $this->errors[$field] = "$label 不能为空";
                        return; // Stop further rules for this field
                    }
                    break;

                case 'max':
                    $max = (int) ($ruleParam ?? 0);
                    if (mb_strlen($stringValue) > $max) {
                        $this->errors[$field] = "$label 最多 $max 个字符";
                    }
                    break;

                case 'min':
                    $min = (int) ($ruleParam ?? 0);
                    if (mb_strlen($stringValue) < $min && $stringValue !== '') {
                        $this->errors[$field] = "$label 至少 $min 个字符";
                    }
                    break;

                case 'int':
                    if ($value !== null && $value !== '' && !ctype_digit(ltrim((string) $value, '-'))) {
                        $this->errors[$field] = "$label 必须是整数";
                    }
                    break;

                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $this->errors[$field] = "$label 必须是数字";
                    }
                    break;

                case 'email':
                    if ($value !== null && $value !== '' && !filter_var($stringValue, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field] = "$label 格式不正确";
                    }
                    break;

                case 'in':
                    if ($value !== null && $value !== '') {
                        $allowed = explode(',', $ruleParam ?? '');
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->errors[$field] = "$label 值无效";
                        }
                    }
                    break;

                case 'boolean':
                    if ($value !== null && $value !== '') {
                        if (!in_array((string) $value, ['0', '1', 'true', 'false', '0', '1'], true)) {
                            $this->errors[$field] = "$label 必须是布尔值";
                        }
                    }
                    break;

                case 'array':
                    if ($value !== null && !is_array($value)) {
                        $this->errors[$field] = "$label 必须是数组";
                    }
                    break;
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace app\common\validation;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;

/**
 * Lightweight input validator for Controller layer.
 */
final class Validator
{
    /**
     * @var array<string, string>
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
            $rulesList = explode('|', (string) $ruleString);
            $value = $data[$field] ?? null;
            $instance->applyRules($field, $value, $rulesList);
        }
        return $instance;
    }

    public function validate(): void
    {
        if (!empty($this->errors)) {
            $firstError = reset($this->errors);
            throw new BusinessException((string) $firstError, ErrorCode::INVALID_PARAMS);
        }
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRules(string $field, mixed $value, array $rules): void
    {
        $label = $field;
        $normalizedValue = is_scalar($value) ? trim((string) $value) : '';
        $stringValue = $normalizedValue;

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            $ruleName = trim((string) $parts[0]);
            $ruleParam = $parts[1] ?? null;

            switch ($ruleName) {
                case 'required':
                    if ($value === null || $value === '' || (is_array($value) && empty($value)) || (is_string($value) && trim($value) === '')) {
                        $this->errors[$field] = "$label is required.";
                        return;
                    }
                    break;

                case 'max':
                    $max = (int) ($ruleParam ?? 0);
                    if (mb_strlen($stringValue) > $max) {
                        $this->errors[$field] = "$label is too long.";
                    }
                    break;

                case 'min':
                    $min = (int) ($ruleParam ?? 0);
                    if ($stringValue !== '' && mb_strlen($stringValue) < $min) {
                        $this->errors[$field] = "$label is too short.";
                    }
                    break;

                case 'int':
                    if ($value !== null && $value !== '' && !ctype_digit(ltrim((string) $value, '-'))) {
                        $this->errors[$field] = "$label must be integer.";
                    }
                    break;

                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $this->errors[$field] = "$label must be number.";
                    }
                    break;

                case 'email':
                    if ($value !== null && $value !== '' && !filter_var($stringValue, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field] = "$label must be a valid email.";
                    }
                    break;

                case 'in':
                    if ($value !== null && $value !== '') {
                        $allowed = explode(',', (string) ($ruleParam ?? ''));
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->errors[$field] = "$label is invalid.";
                        }
                    }
                    break;

                case 'boolean':
                    if ($value !== null && $value !== '') {
                        if (!in_array((string) $value, ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                            $this->errors[$field] = "$label must be boolean.";
                        }
                    }
                    break;

                case 'array':
                    if ($value !== null && !is_array($value)) {
                        $this->errors[$field] = "$label must be array.";
                    }
                    break;
            }
        }
    }
}

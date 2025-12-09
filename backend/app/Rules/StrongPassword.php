<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strong Password Validation Rule
 * 
 * Enforces password policy:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 */
class StrongPassword implements ValidationRule
{
    protected int $minLength;
    protected bool $requireUppercase;
    protected bool $requireLowercase;
    protected bool $requireNumbers;
    protected bool $requireSpecialChars;

    public function __construct(
        int $minLength = 8,
        bool $requireUppercase = true,
        bool $requireLowercase = true,
        bool $requireNumbers = true,
        bool $requireSpecialChars = true
    ) {
        $this->minLength = $minLength;
        $this->requireUppercase = $requireUppercase;
        $this->requireLowercase = $requireLowercase;
        $this->requireNumbers = $requireNumbers;
        $this->requireSpecialChars = $requireSpecialChars;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $errors = [];

        if (strlen($value) < $this->minLength) {
            $errors[] = "at least {$this->minLength} characters";
        }

        if ($this->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            $errors[] = 'at least one uppercase letter';
        }

        if ($this->requireLowercase && !preg_match('/[a-z]/', $value)) {
            $errors[] = 'at least one lowercase letter';
        }

        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            $errors[] = 'at least one number';
        }

        if ($this->requireSpecialChars && !preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/`~]/', $value)) {
            $errors[] = 'at least one special character (!@#$%^&*(),.?":{}|<>)';
        }

        // Check for common weak passwords
        $commonPasswords = [
            'password', 'password123', '123456', '12345678', 'qwerty',
            'abc123', 'monkey', 'master', 'dragon', 'letmein',
            'login', 'admin', 'welcome', 'password1', 'changeme'
        ];

        if (in_array(strtolower($value), $commonPasswords)) {
            $errors[] = 'not be a commonly used password';
        }

        if (!empty($errors)) {
            $fail("The {$attribute} must contain " . implode(', ', $errors) . '.');
        }
    }
}

<?php

namespace App\Rules;

use App\Scanning\UrlTools;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ScannableUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || UrlTools::normalize($value) === null) {
            $fail('The :attribute must be a full http:// or https:// address.');

            return;
        }

        if (! UrlTools::isAllowedTarget($value)) {
            $fail('The :attribute points to a private or unreachable address. Set CURBCUT_ALLOW_PRIVATE_NETWORKS=true to scan intranet sites.');
        }
    }
}

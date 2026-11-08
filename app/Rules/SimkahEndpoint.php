<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SimkahEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = parse_url((string) $value);

        $valid = is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'simkah4.kemenag.go.id'
            && filled($parts['path'] ?? null)
            && ($parts['path'] ?? '') !== '/'
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443);

        if (! $valid) {
            $fail('Gunakan URL HTTPS dari simkah4.kemenag.go.id tanpa query atau fragment.');
        }
    }
}

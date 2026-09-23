<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ConsecutiveAcademicYear implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isConsecutive = is_string($value)
            && preg_match('/^(\d{4})-(\d{4})$/D', $value, $years) === 1
            && (int) $years[2] === (int) $years[1] + 1;

        if (! $isConsecutive) {
            $fail('The :attribute must be two consecutive years in the format YYYY-YYYY, such as 2026-2027.');
        }
    }
}

<?php

namespace Tests\Unit\Rules;

use App\Rules\ConsecutiveAcademicYear;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsecutiveAcademicYearTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function consecutiveYears(): array
    {
        return [
            'ordinary years' => ['2026-2027'],
            'across a century' => ['1999-2000'],
            'round decade' => ['2020-2021'],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'years two apart' => ['2026-2028'],
            'descending years' => ['2027-2026'],
            'same year twice' => ['2026-2026'],
            'two digit years' => ['26-27'],
            'no separator' => ['20262027'],
            'words' => ['first year'],
            'three years' => ['2026-2027-2028'],
            'leading space' => [' 2026-2027'],
            'empty string' => [''],
            'integer' => [2026],
            'null' => [null],
        ];
    }

    #[DataProvider('consecutiveYears')]
    public function test_passes_for_two_consecutive_years(string $value): void
    {
        $this->assertNull($this->failureMessageFor($value));
    }

    #[DataProvider('invalidValues')]
    public function test_fails_for_a_value_that_is_not_two_consecutive_years(mixed $value): void
    {
        $this->assertSame(
            'The :attribute must be two consecutive years in the format YYYY-YYYY, such as 2026-2027.',
            $this->failureMessageFor($value),
        );
    }

    private function failureMessageFor(mixed $value): ?string
    {
        $message = null;

        (new ConsecutiveAcademicYear)->validate('academic_year', $value, function (string $failure) use (&$message): void {
            $message = $failure;
        });

        return $message;
    }
}

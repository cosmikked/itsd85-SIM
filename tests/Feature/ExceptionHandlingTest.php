<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    public function test_unhandled_exceptions_return_consistent_500_json(): void
    {
        // Define a temporary API route that deliberately crashes
        Route::get('/api/test-500', function () {
            throw new \Exception('Database went away, or some other unexpected disaster.');
        })->middleware('api');

        $response = $this->getJson('/api/test-500');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Internal Server Error.',
            ])
            ->assertJsonMissing(['trace']); // Ensure stack traces don't leak
    }
}

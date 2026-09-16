<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationBootTest extends TestCase
{
    public function test_application_boots_and_connects_to_database(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }
}

<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // '/' merutekan guest ke login (route sudah diset: auth ? dashboard : login)
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}

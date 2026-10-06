<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}

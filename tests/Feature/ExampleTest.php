<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root aplikasi tidak menampilkan halaman sendiri. Ia mengarahkan ke
     * dashboard, yang berada di balik middleware auth.
     */
    public function test_root_mengarahkan_ke_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_menolak_tamu(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}

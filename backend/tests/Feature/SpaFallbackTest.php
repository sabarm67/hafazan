<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_404s_when_no_frontend_build_is_present(): void
    {
        $indexPath = public_path('index.html');
        if (file_exists($indexPath)) {
            $this->markTestSkipped('A frontend build is present in public/ — fallback behavior differs, see the next test.');
        }

        $this->get('/some/unmatched/path')->assertNotFound();
    }

    public function test_it_serves_the_built_spa_when_present(): void
    {
        $indexPath = public_path('index.html');
        file_put_contents($indexPath, '<html><body>hafazan</body></html>');

        try {
            $this->get('/mushaf')->assertOk()->assertSee('hafazan');
        } finally {
            unlink($indexPath);
        }
    }

    public function test_api_routes_are_not_shadowed_by_the_fallback(): void
    {
        $this->getJson('/api/v1/surahs')->assertOk();
    }
}

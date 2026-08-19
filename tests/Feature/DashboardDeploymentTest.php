<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardDeploymentTest extends TestCase
{
    public function test_dashboard_shell_is_served_for_admin_deep_links(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/users/42')->assertOk();
    }

    public function test_dashboard_build_references_existing_same_domain_assets(): void
    {
        $html = file_get_contents(public_path('dist/index.html'));

        $this->assertIsString($html);
        $this->assertStringNotContainsString('src="/assets/', $html);
        $this->assertStringNotContainsString('href="/assets/', $html);

        preg_match_all('/(?:src|href)="(\/dist\/[^"?#]+)[^\"]*"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The dashboard build did not emit /dist asset URLs.');

        foreach ($matches[1] as $url) {
            $this->assertFileExists(public_path(ltrim($url, '/')), "Missing dashboard asset: {$url}");
        }
    }
}

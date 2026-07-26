<?php

namespace Tests\Unit;

use App\Models\Ad;
use Tests\TestCase;

class PublishedAdScopeTest extends TestCase
{
    public function test_public_scope_requires_published_status_and_valid_expiry(): void
    {
        $query = Ad::query()->published();
        $sql = $query->toSql();

        $this->assertStringContainsString('`status` = ?', $sql);
        $this->assertStringContainsString('`expires_at` is null', $sql);
        $this->assertStringContainsString('`expires_at` > ?', $sql);
        $this->assertSame('published', $query->getBindings()[0]);
    }
}

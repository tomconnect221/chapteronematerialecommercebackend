<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Subadmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubadminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_subadmin_with_null_permissions_keeps_default_access(): void
    {
        $branch = Branch::factory()->create();

        $subadmin = Subadmin::factory()->create([
            'branch_id' => $branch->id,
            'permissions' => null,
        ]);

        $this->assertTrue($subadmin->hasPermission('products.create'));
        $this->assertTrue($subadmin->canAccessPage('products'));
        $this->assertTrue($subadmin->hasPermission('orders.update'));
    }

    public function test_subadmin_with_empty_permissions_has_no_access(): void
    {
        $branch = Branch::factory()->create();

        $subadmin = Subadmin::factory()->create([
            'branch_id' => $branch->id,
            'permissions' => [],
        ]);

        $this->assertFalse($subadmin->hasPermission('products.view'));
        $this->assertFalse($subadmin->canAccessPage('products'));
    }

    public function test_subadmin_respects_granted_permissions_only(): void
    {
        $branch = Branch::factory()->create();

        $subadmin = Subadmin::factory()->create([
            'branch_id' => $branch->id,
            'permissions' => ['products.view', 'products.create'],
        ]);

        $this->assertTrue($subadmin->hasPermission('products.view'));
        $this->assertTrue($subadmin->hasPermission('products.create'));
        $this->assertFalse($subadmin->hasPermission('products.delete'));
        $this->assertFalse($subadmin->canAccessPage('orders'));
    }
}

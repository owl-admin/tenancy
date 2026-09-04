<?php

namespace OwlAdmin\Tenancy\Tests\Feature;

use OwlAdmin\Tenancy\Tests\TestCase;
use OwlAdmin\Tenancy\Models\Tenant;
use OwlAdmin\Tenancy\Models\TenantUser;
use OwlAdmin\Tenancy\Tests\Fixtures\DemoPost;
use OwlAdmin\Tenancy\Tests\Fixtures\FakeAdminUser;

class TenancyManagerTest extends TestCase
{
    /**
     * setTenant 之后 id() 必须指向该租户，clear 后应为空。
     */
    public function test_set_and_clear_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'A',
            'slug' => 'a',
            'enabled' => true,
        ]);

        tenancy()->setTenant($tenant, persist: false);

        $this->assertSame($tenant->id, tenancy()->id());
        $this->assertFalse(tenancy()->isBypassed());

        tenancy()->clear(persist: false);

        $this->assertNull(tenancy()->id());
    }

    /**
     * bypass 打开后清掉当前租户；再关闭旁路时仍未绑定。
     */
    public function test_bypass_clears_current_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        tenancy()->setTenant($tenant, persist: false);

        tenancy()->bypass();

        $this->assertTrue(tenancy()->isBypassed());
        $this->assertNull(tenancy()->id());

        tenancy()->bypass(false);

        $this->assertFalse(tenancy()->isBypassed());
    }

    /**
     * 停用租户不能被 setTenant 绑上。
     */
    public function test_set_tenant_ignores_disabled_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => false]);

        tenancy()->setTenant($tenant, persist: false);

        $this->assertNull(tenancy()->id());
    }

    /**
     * 绑定租户后，带 trait 的模型只能看到该租户的行。
     */
    public function test_belongs_to_tenant_scope_filters_rows(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        $b = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'enabled' => true]);

        DemoPost::query()->create(['tenant_id' => $a->id, 'title' => 'ta']);
        DemoPost::query()->create(['tenant_id' => $b->id, 'title' => 'tb']);

        tenancy()->setTenant($a, persist: false);

        $this->assertSame(['ta'], DemoPost::query()->pluck('title')->all());

        tenancy()->setTenant($b, persist: false);

        $this->assertSame(['tb'], DemoPost::query()->pluck('title')->all());
    }

    /**
     * 旁路打开后作用域失效，能看到全部租户的数据。
     */
    public function test_bypass_skips_scope(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        DemoPost::query()->create(['tenant_id' => $a->id, 'title' => 'ta']);
        DemoPost::query()->create(['tenant_id' => 999, 'title' => 'other']);

        tenancy()->bypass();

        $this->assertCount(2, DemoPost::query()->get());
    }

    /**
     * 未绑定且未强制隔离时，作用域不加条件，能看到全部行。
     */
    public function test_unforced_scope_does_not_filter_without_tenant(): void
    {
        config(['owl_admin_tenancy.forced' => false]);

        DemoPost::query()->create(['tenant_id' => 1, 'title' => 'ta']);
        DemoPost::query()->create(['tenant_id' => 2, 'title' => 'tb']);

        $this->assertFalse(tenancy()->forced());
        $this->assertNull(tenancy()->id());
        $this->assertCount(2, DemoPost::query()->get());
    }

    /**
     * 强制隔离且未绑定租户时，查询应为空，避免误查全表。
     */
    public function test_forced_scope_hides_all_rows_without_tenant(): void
    {
        config(['owl_admin_tenancy.forced' => true]);

        DemoPost::query()->create(['tenant_id' => 1, 'title' => 'ta']);
        DemoPost::query()->create(['tenant_id' => 2, 'title' => 'tb']);

        $this->assertTrue(tenancy()->forced());
        $this->assertNull(tenancy()->id());
        $this->assertCount(0, DemoPost::query()->get());
        $this->assertSame(['ta', 'tb'], DemoPost::allTenants()->pluck('title')->all());
    }

    /**
     * 新建记录未传 tenant_id 时，写入当前绑定的租户。
     */
    public function test_creating_fills_current_tenant_id(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        tenancy()->setTenant($a, persist: false);

        $post = DemoPost::query()->create(['title' => 'auto']);

        $this->assertSame($a->id, (int) $post->tenant_id);
    }

    /**
     * 调用方已指定 tenant_id 时，creating 钩子不得覆盖。
     */
    public function test_creating_does_not_override_explicit_tenant_id(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        $b = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'enabled' => true]);
        tenancy()->setTenant($a, persist: false);

        $post = DemoPost::withoutGlobalScopes()->create([
            'title' => 'explicit',
            'tenant_id' => $b->id,
        ]);

        $this->assertSame($b->id, (int) $post->tenant_id);
    }

    /**
     * 未登录用户不能进入任何租户。
     */
    public function test_user_can_access_requires_user(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);

        $this->assertFalse(tenancy()->userCanAccess($tenant->id));
        $this->assertFalse(tenancy()->userCanAccess(0));
    }

    /**
     * 普通成员只能进成员表里的启用租户。
     */
    public function test_member_can_access_assigned_enabled_tenant(): void
    {
        $allowed = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        $denied = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'enabled' => true]);
        $disabled = Tenant::query()->create(['name' => 'C', 'slug' => 'c', 'enabled' => false]);
        $user = new FakeAdminUser(id: 7, administrator: false);

        TenantUser::query()->create([
            'tenant_id' => $allowed->id,
            'admin_user_id' => $user->id,
        ]);
        TenantUser::query()->create([
            'tenant_id' => $disabled->id,
            'admin_user_id' => $user->id,
        ]);

        $this->actingAsTenancyUser($user);

        $this->assertTrue(tenancy()->userCanAccess($allowed->id));
        $this->assertFalse(tenancy()->userCanAccess($denied->id));
        $this->assertFalse(tenancy()->userCanAccess($disabled->id));
        $this->assertFalse(tenancy()->userCanAccess(9999));
    }

    /**
     * 超管可以进入任意启用租户，不必出现在成员表。
     */
    public function test_administrator_can_access_any_enabled_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        $disabled = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'enabled' => false]);
        $user = new FakeAdminUser(id: 1, administrator: true);

        $this->actingAsTenancyUser($user);

        $this->assertTrue(tenancy()->userCanAccess($tenant->id));
        $this->assertFalse(tenancy()->userCanAccess($disabled->id));
    }
}

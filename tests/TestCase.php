<?php

namespace OwlAdmin\Tenancy\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use OwlAdmin\Tenancy\TenancyManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    /**
     * 只注册租户管理器，不启动 OwlAdmin 后台。
     *
     * TenancyServiceProvider 依赖扩展内核，CI 里不完整装 Admin 时无法 boot。
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app->scoped(TenancyManager::class);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('admin.database.connection', 'testing');
        $app['config']->set('owl_admin_tenancy.forced', false);
        $app['config']->set('owl_admin_tenancy.super_admin_bypass', true);
        $app['config']->set('owl_admin_tenancy.column', 'tenant_id');
    }

    /**
     * 建出租户表、成员表和一张带 tenant_id 的业务表。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');

        Schema::create('demo_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('title');
        });
    }

    /**
     * 把桩用户塞进默认 guard，TenancyManager::user() 在 Admin 未启动时会读到它。
     */
    protected function actingAsTenancyUser(object $user): void
    {
        Auth::setUser($user);
    }
}

<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use OwlAdmin\Tenancy\TenancyManager;
use OwlAdmin\Tenancy\Models\Tenant;
use OwlAdmin\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class TenancyManagerTest extends TestCase
{
    /**
     * 用内存库搭出租户表和一张带 tenant_id 的业务表。
     */
    protected function setUp(): void
    {
        if (!class_exists(\Slowlyo\OwlAdmin\AdminServiceProvider::class)
            || !is_file(base_path('packages/slowlyo/owl-admin/src/AdminServiceProvider.php'))) {
            $this->markTestSkipped('owl-admin 源码未检出，跳过租户测试');
        }

        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('admin.database.connection', 'testing');
        config()->set('cache.default', 'array');

        $this->app['db']->purge('testing');
        $this->app['db']->reconnect('testing');

        $this->app->scoped(TenancyManager::class);

        $helpers = base_path('extensions/owl-admin/tenancy/src/Support/helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('demo_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('title');
        });
    }

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

        tenancy()->clear(persist: false);

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
     * 新建记录未传 tenant_id 时，写入当前绑定的租户。
     */
    public function test_creating_fills_current_tenant_id(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'enabled' => true]);
        tenancy()->setTenant($a, persist: false);

        $post = DemoPost::query()->create(['title' => 'auto']);

        $this->assertSame($a->id, (int) $post->tenant_id);
    }
}

/**
 * 测试用业务模型，只为验证 BelongsToTenant。
 */
class DemoPost extends Model
{
    use BelongsToTenant;

    protected $table = 'demo_posts';

    public $timestamps = false;

    protected $guarded = [];
}

<?php

namespace OwlAdmin\Tenancy;

use Slowlyo\OwlAdmin\Admin;
use OwlAdmin\Tenancy\TenancyManager;
use Slowlyo\OwlAdmin\Extend\ServiceProvider;
use OwlAdmin\Tenancy\Http\Middleware\ResolveTenant;

class TenancyServiceProvider extends ServiceProvider
{
    protected $middleware = [
        ResolveTenant::class,
    ];

    /**
     * 切换接口必须登录，但不能依赖菜单权限，否则普通成员选不了租户。
     */
    protected $exceptRoutes = [
        'permission' => [
            'owl-tenancy/switch',
            'owl-tenancy/current',
            'owl-tenancy/options',
        ],
    ];

    protected $menu = [
        [
            'title' => '多租户',
            'url'   => '',
            'icon'  => 'ph:buildings',
        ],
        [
            'parent' => '多租户',
            'title'  => '租户管理',
            'url'    => '/owl-tenancy/tenants',
            'icon'   => 'ph:building-office',
        ],
        [
            'parent' => '多租户',
            'title'  => '成员分配',
            'url'    => '/owl-tenancy/members',
            'icon'   => 'ph:users-three',
        ],
        [
            'parent' => '多租户',
            'title'  => '切换租户',
            'url'    => '/owl-tenancy/switch',
            'icon'   => 'ph:arrows-clockwise',
        ],
    ];

    /**
     * 把 TenancyManager 注册成 scoped，每个请求一份，避免 Octane 串租户。
     */
    public function register()
    {
        parent::register();

        $this->app->scoped(TenancyManager::class);

        try {
            $this->loadMigrationsFrom($this->path('database/migrations'));
        } catch (\Throwable) {
            // 扩展目录尚未就绪时不打断应用启动
        }
    }

    /**
     * 域名/子域名解析入口，v1 未实现。
     */
    public function customInitAfter()
    {
        // TODO: 独立数据库 / 域名路由尚未实现
    }

    /**
     * 每个后台请求先绑租户，再往顶栏塞切换器。
     *
     * 必须在 ResolveTenant 之前完成绑定，顶栏才能读到当前租户。
     */
    public function requestBoot(): void
    {
        $manager = app(TenancyManager::class);
        $manager->bindCurrentRequest();

        $this->registerSwitcherNav();
    }

    /**
     * 扩展设置：强制隔离、超管旁路、业务表租户列名。
     */
    public function settingForm()
    {
        return $this->baseSettingForm()->body([
            amis()->SwitchControl('forced', '强制租户隔离')
                ->onText('开启')
                ->offText('关闭')
                ->value(false)
                ->description('开启后，未绑定租户的请求在业务表上查不到数据。关闭则未绑定租户时不加 tenant_id 条件。'),
            amis()->SwitchControl('super_admin_bypass', '超级管理员旁路')
                ->onText('开启')
                ->offText('关闭')
                ->value(true)
                ->description('开启后，超级管理员未选择租户时跳过隔离，可查看全部数据。选择具体租户后仍按该租户过滤。'),
            amis()->TextControl('column', '租户字段名')
                ->value('tenant_id')
                ->required()
                ->description('业务表上的租户列名，BelongsToTenant 和作用域都读这个配置，默认 tenant_id。'),
        ]);
    }

    /**
     * 已登录且有可选租户时，在顶栏追加下拉切换。
     *
     * _settings 在登录后会带 token 再请求一次，未登录时不注入，避免登录页报错。
     */
    protected function registerSwitcherNav(): void
    {
        $user = Admin::user();

        if (!$user) {
            return;
        }

        $tenancy = tenancy();

        if (!$tenancy->tenantsTableReady()) {
            return;
        }
        $options = $tenancy->availableTenants();

        if ($options->isEmpty() && !$tenancy->isBypassed()) {
            return;
        }

        $select = amis()->SelectControl('tenant_id')
            ->placeholder($tenancy->isBypassed() ? '全部租户' : '切换租户')
            ->value($tenancy->id())
            ->clearable($tenancy->superAdminBypass() && method_exists($user, 'isAdministrator') && $user->isAdministrator())
            ->searchable()
            ->size('sm')
            ->options(
                $options->map(fn ($tenant) => [
                    'label' => $tenant->name,
                    'value' => $tenant->id,
                ])->values()->all()
            )
            ->onEvent([
                'change' => [
                    'actions' => [
                        [
                            'actionType' => 'ajax',
                            'api'        => [
                                'method' => 'post',
                                'url'    => admin_url('owl-tenancy/switch'),
                                'data'   => [
                                    'tenant_id' => '${event.data.value}',
                                ],
                            ],
                        ],
                        [
                            'actionType' => 'custom',
                            'script'     => 'window.location.reload()',
                        ],
                    ],
                ],
            ]);

        Admin::appendNav(
            amis()->Wrapper()->size('none')->className('px-2 min-w-40')->body($select)
        );
    }
}

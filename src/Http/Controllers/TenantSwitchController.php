<?php

namespace OwlAdmin\Tenancy\Http\Controllers;

use Illuminate\Http\Request;
use Slowlyo\OwlAdmin\Controllers\AdminController;

class TenantSwitchController extends AdminController
{
    /**
     * 切换租户页：单租户用户进来时已经自动绑定，多租户必须在这里选。
     */
    public function index()
    {
        $tenancy = tenancy();
        $options = $this->optionsPayload();
        $currentId = $tenancy->id();

        $form = $this->baseForm(false)
            ->mode('normal')
            ->api(admin_url('owl-tenancy/switch'))
            ->initApi(admin_url('owl-tenancy/current'))
            ->data([
                'tenant_id' => $currentId,
            ])
            ->onEvent([
                'submitSucc' => [
                    'actions' => [
                        [
                            'actionType' => 'custom',
                            'script'     => 'window.location.hash = "#/"; window.location.reload();',
                        ],
                    ],
                ],
            ])
            ->body([
                amis()->Alert()
                    ->level('info')
                    ->showIcon()
                    ->body($this->hint()),
                amis()->SelectControl('tenant_id', '当前租户')
                    ->searchable()
                    ->clearable($tenancy->isBypassed() || $tenancy->superAdminBypass())
                    ->options($options)
                    ->placeholder($tenancy->isBypassed() ? '未选择（当前为全部租户）' : '请选择租户'),
            ]);

        $page = $this->basePage()->body(
            amis()->Card()->header(['title' => '切换租户'])->body($form)
        );

        return $this->response()->success($page);
    }

    /**
     * 把选择写入 cache/session，后续请求的全局作用域都吃这个值。
     */
    public function switch(Request $request)
    {
        $tenancy = tenancy();
        $tenantId = $request->input('tenant_id');

        // 空值表示超管退回旁路，普通用户不允许清空后再去看全表
        if (!filled($tenantId)) {
            admin_abort_if(!$tenancy->superAdminBypass() || !$this->isSuperAdmin(), '必须选择一个租户');
            $tenancy->clear();

            return $this->response()->successMessage('已切换为全部租户');
        }

        $tenantId = (int) $tenantId;
        admin_abort_if(!$tenancy->userCanAccess($tenantId), '无权进入该租户');

        $tenancy->setTenant($tenantId);

        $name = $tenancy->tenant()?->name ?: (string) $tenantId;

        return $this->response()->success(['tenant_id' => $tenantId], '已切换到：' . $name);
    }

    /**
     * 给顶栏切换器和本页表单提供当前绑定状态。
     */
    public function current()
    {
        $tenancy = tenancy();

        return $this->response()->success([
            'tenant_id'     => $tenancy->id(),
            'tenant'        => $tenancy->tenant()?->only(['id', 'name', 'slug']),
            'bypassed'      => $tenancy->isBypassed(),
            'needs_select'  => $tenancy->needsSelection(),
            'forced'        => $tenancy->forced(),
            'options'       => $this->optionsPayload(),
        ]);
    }

    /**
     * 仅返回下拉选项，避免顶栏再查一遍完整状态。
     */
    public function options()
    {
        return $this->response()->success($this->optionsPayload());
    }

    /**
     * 下拉选项：label 带 slug，方便重名租户区分。
     */
    protected function optionsPayload(): array
    {
        return tenancy()->availableTenants()->map(fn ($tenant) => [
            'label' => $tenant->name . ' (' . $tenant->slug . ')',
            'value' => $tenant->id,
        ])->values()->all();
    }

    /**
     * 页顶提示：告诉用户为什么现在必须选，或者已经自动绑上了。
     */
    protected function hint(): string
    {
        $tenancy = tenancy();

        if ($tenancy->needsSelection()) {
            return '你属于多个租户，请选择当前要进入的租户。未选择时，开启强制隔离的业务数据将不可见。';
        }

        if ($tenancy->id()) {
            return '当前租户：' . ($tenancy->tenant()?->name ?: $tenancy->id()) . '。切换后页面会刷新，列表数据按新租户过滤。';
        }

        if ($tenancy->isBypassed()) {
            return '当前未绑定租户，超级管理员旁路已开启，业务查询不会自动加 tenant_id。需要排障时再选一个租户。';
        }

        return '你还没有加入任何租户。请联系管理员在「成员分配」里添加，或先在「租户管理」创建租户。';
    }

    /**
     * 当前登录用户是否超级管理员。
     */
    protected function isSuperAdmin(): bool
    {
        $user = $this->user();

        return $user && method_exists($user, 'isAdministrator') && $user->isAdministrator();
    }
}

<?php

namespace OwlAdmin\Tenancy\Http\Controllers;

use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Controllers\AdminController;
use OwlAdmin\Tenancy\Services\TenantMemberService;

/**
 * @property TenantMemberService $service
 */
class TenantMemberController extends AdminController
{
    protected string $serviceName = TenantMemberService::class;

    protected string $queryPath = 'owl-tenancy/members';

    /**
     * 把后台用户划进某个租户，可选填租户内角色。
     */
    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                $this->createButton('drawer'),
                ...$this->baseHeaderToolBar(),
            ])
            ->filter($this->baseFilter()->body([
                amis()->SelectControl('tenant_id', '租户')
                    ->size('md')
                    ->clearable()
                    ->searchable()
                    ->options($this->tenantOptions()),
                amis()->SelectControl('admin_user_id', '管理员')
                    ->size('md')
                    ->clearable()
                    ->searchable()
                    ->options($this->userOptions()),
            ]))
            ->columns([
                amis()->TableColumn('id', 'ID')->sortable(),
                amis()->TableColumn('tenant.name', '租户'),
                amis()->TableColumn('user.username', '用户名'),
                amis()->TableColumn('user.name', '姓名'),
                amis()->TableColumn('role', '租户内角色')->type('mapping')->map([
                    'owner'  => '负责人',
                    'admin'  => '管理员',
                    'member' => '成员',
                ]),
                amis()->TableColumn('created_at', admin_trans('admin.created_at'))->type('datetime')->sortable(),
                $this->rowActions([
                    $this->rowEditButton('drawer'),
                    $this->rowDeleteButton(),
                ]),
            ]);

        return $this->baseList($crud);
    }

    /**
     * 成员表单：租户 + 管理员 + 可选角色。
     */
    public function form($isEdit = false)
    {
        return $this->baseForm()->mode('normal')->body([
            amis()->SelectControl('tenant_id', '租户')
                ->required()
                ->searchable()
                ->options($this->tenantOptions()),
            amis()->SelectControl('admin_user_id', '管理员')
                ->required()
                ->searchable()
                ->labelField('label')
                ->valueField('value')
                ->options($this->userOptions()),
            amis()->SelectControl('role', '租户内角色')
                ->clearable()
                ->options($this->roleOptions())
                ->description('仅表示该用户在此租户内的身份，不影响后台 RBAC'),
        ]);
    }

    /**
     * 详情回显关联名称。
     */
    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->TextControl('id', 'ID')->static(),
            amis()->TextControl('tenant.name', '租户')->static(),
            amis()->TextControl('user.username', '用户名')->static(),
            amis()->TextControl('user.name', '姓名')->static(),
            amis()->TextControl('role', '租户内角色')->static(),
            amis()->TextControl('created_at', admin_trans('admin.created_at'))->static(),
        ]);
    }

    /**
     * 当前用户能管理的租户选项。
     */
    protected function tenantOptions(): array
    {
        return tenancy()->availableTenants()->map(fn ($tenant) => [
            'label' => $tenant->name . ' (' . $tenant->slug . ')',
            'value' => $tenant->id,
        ])->values()->all();
    }

    /**
     * 后台用户选项。成员分配需要看到全部启用用户。
     */
    protected function userOptions(): array
    {
        $model = Admin::adminUserModel();

        return $model::query()
            ->orderBy('id')
            ->get(['id', 'username', 'name'])
            ->map(fn ($user) => [
                'label' => $user->name
                    ? ($user->username . ' / ' . $user->name)
                    : $user->username,
                'value' => $user->id,
            ])
            ->values()
            ->all();
    }

    /**
     * 租户内角色，不是 admin_roles。
     */
    protected function roleOptions(): array
    {
        return [
            ['label' => '负责人', 'value' => 'owner'],
            ['label' => '管理员', 'value' => 'admin'],
            ['label' => '成员', 'value' => 'member'],
        ];
    }
}

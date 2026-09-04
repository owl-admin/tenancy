<?php

namespace OwlAdmin\Tenancy\Http\Controllers;

use OwlAdmin\Tenancy\Services\TenantService;
use Slowlyo\OwlAdmin\Controllers\AdminController;

/**
 * @property TenantService $service
 */
class TenantController extends AdminController
{
    protected string $serviceName = TenantService::class;

    protected string $queryPath = 'owl-tenancy/tenants';

    /**
     * 租户列表：名称、标识、启用状态。
     */
    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                $this->createButton('drawer'),
                ...$this->baseHeaderToolBar(),
            ])
            ->filter($this->baseFilter()->body([
                amis()->TextControl('name', '名称')->size('md')->clearable(),
                amis()->TextControl('slug', '标识')->size('md')->clearable(),
                amis()->SelectControl('enabled', '状态')
                    ->size('md')
                    ->clearable()
                    ->options([
                        ['label' => '启用', 'value' => 1],
                        ['label' => '停用', 'value' => 0],
                    ]),
            ]))
            ->columns([
                amis()->TableColumn('id', 'ID')->sortable(),
                amis()->TableColumn('name', '名称')->searchable(),
                amis()->TableColumn('slug', '标识'),
                amis()->TableColumn('enabled', '状态')->quickEdit(
                    amis()->SwitchControl()
                        ->mode('inline')
                        ->saveImmediately()
                        ->onText('启用')
                        ->offText('停用')
                ),
                amis()->TableColumn('created_at', admin_trans('admin.created_at'))->type('datetime')->sortable(),
                $this->rowActions([
                    $this->rowEditButton('drawer'),
                    $this->rowDeleteButton(),
                ]),
            ]);

        return $this->baseList($crud);
    }

    /**
     * 新增/编辑表单。slug 一旦被业务表引用就不要轻易改。
     */
    public function form($isEdit = false)
    {
        return $this->baseForm()->mode('normal')->body([
            amis()->TextControl('name', '名称')->required(),
            amis()->TextControl('slug', '标识')
                ->required()
                ->description('唯一标识，建议用英文或拼音，业务数据按租户隔离时会用到'),
            amis()->SwitchControl('enabled', '启用')
                ->onText('启用')
                ->offText('停用')
                ->value(1),
        ]);
    }

    /**
     * 详情只读展示。
     */
    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->TextControl('id', 'ID')->static(),
            amis()->TextControl('name', '名称')->static(),
            amis()->TextControl('slug', '标识')->static(),
            amis()->TextControl('enabled', '启用')->static(),
            amis()->TextControl('created_at', admin_trans('admin.created_at'))->static(),
            amis()->TextControl('updated_at', admin_trans('admin.updated_at'))->static(),
        ]);
    }
}

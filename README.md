## OwlAdmin 多租户

## 效果

共享数据库 + `tenant_id` 列隔离，提供租户管理、成员分配、租户切换，以及业务模型作用域。

## 安装

#### zip 下载地址

[https://github.com/owl-admin/tenancy/archive/refs/heads/main.zip](https://github.com/owl-admin/tenancy/archive/refs/heads/main.zip)

#### composer

```bash
composer require owl-admin/tenancy
```

## 使用说明

1. 安装扩展
2. 在扩展管理中启用扩展
3. 在「租户管理」中创建租户，并在「成员分配」中绑定后台用户
4. 多租户用户通过顶栏或「切换租户」选择当前租户；只属于一个租户时会自动绑定

## 扩展配置

在扩展管理中可以配置以下内容

- 强制租户隔离
- 超级管理员旁路
- 租户字段名（默认 `tenant_id`）

## 业务表接入

1. 业务表增加租户字段（与扩展配置中的字段名一致，默认 `tenant_id`）

```php
Schema::table('articles', function (Blueprint $table) {
    $table->unsignedBigInteger('tenant_id')->nullable()->index();
});
```

2. 模型使用 trait

```php
use OwlAdmin\Tenancy\Traits\BelongsToTenant;

class Article extends Model
{
    use BelongsToTenant;
}
```

### 调用

```php
tenancy()->id();
tenancy()->tenant();
tenancy()->setTenant($id);
tenancy()->clear();

use OwlAdmin\Tenancy\Facades\Tenancy;
Tenancy::id();
```

```php
Article::allTenants()->get();   // 关闭作用域
Article::forTenant($id)->get(); // 指定租户
```

请求头可额外携带 `X-Tenant-Id`，仅作用于当前请求。

### 注意事项

- 仅支持共享库 + 列隔离，不支持独立数据库 / 域名识别
- 不要把 `BelongsToTenant` 加在租户表、成员表或后台用户 / 角色 / 菜单等全局表上
- 租户内角色只是标记，不会替换后台权限系统
- 卸载扩展会回滚本扩展 migration 并删除扩展菜单，业务表上的 `tenant_id` 列不会自动删除

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 后台用户与租户的多对多关系，role 是租户内角色，不是后台 RBAC。
     */
    public function up(): void
    {
        Schema::create('admin_tenant_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('admin_user_id');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'admin_user_id']);
            $table->index('admin_user_id');
        });
    }

    /**
     * 卸载扩展时随 migrator rollback 删除。
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_tenant_users');
    }
};

<?php

namespace OwlAdmin\Tenancy\Tests\Fixtures;

use OwlAdmin\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * 测试用业务模型，只为验证 BelongsToTenant 作用域和自动填列。
 */
class DemoPost extends Model
{
    use BelongsToTenant;

    protected $table = 'demo_posts';

    public $timestamps = false;

    protected $guarded = [];
}

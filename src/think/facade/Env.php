<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/10/25
// +----------------------------------------------------------------------
// | Title:  Env.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\facade;

use think\Facade;

/**
 * Class Env
 * @package think\facade
 * @see \think\Env
 * @mixin \think\Env
 */
class Env extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'env';
    }
}
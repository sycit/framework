<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/10/25
// +----------------------------------------------------------------------
// | Title:  Middleware.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\facade;

use think\Facade;

/**
 * Class Middleware
 * @package think\facade
 * @see \think\Middleware
 * @mixin \think\Middleware
 */
class Middleware extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'middleware';
    }
}
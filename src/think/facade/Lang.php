<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/10/25
// +----------------------------------------------------------------------
// | Title:  Lang.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\facade;

use think\Facade;

/**
 * Class Lang
 * @package think\facade
 * @see \think\Lang
 * @mixin \think\Lang
 */
class Lang extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'lang';
    }
}
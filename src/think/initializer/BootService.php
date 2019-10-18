<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  BootService.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\initializer;

use think\App;

/**
 * 启动系统服务
 * Class BootService
 * @package think\initializer
 */
class BootService
{
    public function init(App $app)
    {
        $app->boot();
    }
}
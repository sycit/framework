<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/19
// +----------------------------------------------------------------------
// | Title:  CheckRequestCache.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\middleware;

use think\App;

/**
 * 全局缓存中间件
 * Class CheckRequestCache
 * @package think\middleware
 */
class CheckRequestCache
{
    /** @var App */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }
}
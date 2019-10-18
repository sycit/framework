<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Session.php
// +----------------------------------------------------------------------

declare(strict_types = 1);

namespace think;

use think\helper\Arr;
use think\session\Store;

/**
 * Session 管理类
 * Class Session
 * @package think
 * @mixin Store
 */
class Session extends Manager
{
    protected $namespace = '\\think\\session\\driver\\';

    protected function createDriver(string $name)
    {
        $handler = parent::createDriver($name);

        return new Store($this->config('name', 'PHPSESSID'), $handler, $this->config('serialize'));
    }

    /**
     * 导入配置
     * @param App $app
     * @return static
     */
    public static function __make(App $app)
    {
        return new static($app, $app->getReadsConfig('session'));
    }

    /**
     * 获取驱动配置
     * @param string $name
     * @return array|mixed
     */
    protected function resolveConfig(string $name)
    {
        $config = $this->config;
        // 剔除指定key
        Arr::forget($config, 'type');
        return $config;
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->config('type', 'file');
    }
}
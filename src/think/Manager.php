<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Manager.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use think\exception\InvalidArgumentException;
use think\helper\Str;

/**
 * 驱动管理
 * Class Manager
 * @package think
 */
abstract class Manager
{
    /** @var App */
    protected $app;

    /**
     * 配置
     * @var array
     */
    protected $config = [];

    /**
     * 驱动
     * @var array
     */
    protected $drivers = [];

    /**
     * 驱动的命名空间
     * @var string
     */
    protected $namespace = null;

    public function __construct(App $app, array $config = [])
    {
        $this->app    = $app;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 读取配置
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function config(string $name = '', $default = null)
    {
        // 无参数时获取所有
        if (empty($name)) {
            return $this->config;
        }

        if (strpos($name, '.')) {
            $name    = explode('.', $name);
            $config  = $this->config;

            // 按.拆分成多维数组进行判断
            foreach ($name as $val) {
                if (isset($config[$val])) {
                    $config = $config[$val];
                } else {
                    return $default;
                }
            }

            return $config;
        }

        return isset($this->config[$name]) ? (empty($this->config[$name]) ? $default : $this->config[$name]) : $default;
    }

    /**
     * 获取驱动实例
     * @param null|string $name
     * @return mixed
     */
    protected function driver(string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        if (is_null($name)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        return $this->drivers[$name] = $this->getDriver($name);
    }

    /**
     * 获取驱动实例
     * @param string $name
     * @return mixed
     */
    protected function getDriver(string $name)
    {
        return $this->drivers[$name] ?? $this->createDriver($name);
    }

    /**
     * 获取驱动类型
     * @param string $name
     * @return mixed
     */
    protected function resolveType(string $name)
    {
        return $name;
    }

    /**
     * 获取驱动配置
     * @param string $name
     * @return mixed
     */
    protected function resolveConfig(string $name)
    {
        return $name;
    }

    /**
     * 获取驱动类
     * @param string $type
     * @return string
     */
    protected function resolveClass(string $type): string
    {
        if ($this->namespace || false !== strpos($type, '\\')) {
            $class = false !== strpos($type, '\\') ? $type : $this->namespace . Str::studly($type);

            if (class_exists($class)) {
                return $class;
            }
        }

        throw new InvalidArgumentException("Driver [$type] not supported.");
    }

    /**
     * 创建驱动
     *
     * @param string $name
     * @return mixed
     *
     */
    protected function createDriver(string $name)
    {
        $type   = $this->resolveType($name);
        $config = $this->resolveConfig($name);

        $method = 'create' . Str::studly($type) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        $class = $this->resolveClass($type);

        return $this->app->invokeClass($class, [$config]);
    }

    /**
     * 移除一个驱动实例
     *
     * @param array|string|null $name
     * @return $this
     */
    public function forgetDriver($name = null)
    {
        $name = $name ?? $this->getDefaultDriver();

        foreach ((array) $name as $cacheName) {
            if (isset($this->drivers[$cacheName])) {
                unset($this->drivers[$cacheName]);
            }
        }

        return $this;
    }

    /**
     * 默认驱动
     * @return string|null
     */
    abstract public function getDefaultDriver();

    /**
     * 动态调用
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Cache.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use Psr\SimpleCache\CacheInterface;
use think\cache\Driver;
use think\cache\TagSet;
use think\helper\Arr;

/**
 * 缓存管理类
 * @mixin Driver
 * @mixin \think\cache\driver\File
 */
class Cache extends Manager implements CacheInterface
{
    protected $namespace = '\\think\\cache\\driver\\';

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->config('default');
    }

    public static function __make(App $app)
    {
        return new static($app, $app->getReadsConfig('cache'));
    }

    /**
     * 获取驱动配置
     * @param string $store
     * @param string $name
     * @param null   $default
     * @return array
     */
    public function getStoreConfig(string $store, string $name = null, $default = null)
    {
        if ($config = $this->config("stores.{$store}")) {
            return Arr::get($config, $name, $default);
        }

        throw new \InvalidArgumentException("Store [$store] not found.");
    }

    protected function resolveType(string $name)
    {
        return $this->getStoreConfig($name, 'type', 'file');
    }

    protected function resolveConfig(string $name)
    {
        return $this->getStoreConfig($name);
    }

    /**
     * 连接或者切换缓存
     * @access public
     * @param string $name 连接配置名
     * @return Driver
     */
    public function store(string $name = null): Driver
    {
        return $this->driver($name);
    }

    /**
     * 清空缓冲池
     * @access public
     * @return bool
     */
    public function clear(): bool
    {
        return $this->store()->clear();
    }

    /**
     * 读取缓存
     * @access public
     * @param string $key 缓存变量名
     * @param mixed $default 默认值
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        return $this->store()->get($key, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param string $key 缓存变量名
     * @param mixed $value 存储数据
     * @param int|\DateTime $ttl 有效时间 0为永久
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set($key, $value, $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $key 缓存变量名
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete($key): bool
    {
        return $this->store()->delete($key);
    }

    /**
     * 读取缓存
     * @access public
     * @param iterable $keys 缓存变量名
     * @param mixed $default 默认值
     * @return iterable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getMultiple($keys, $default = null): iterable
    {
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param iterable $values 缓存数据
     * @param null|int|\DateInterval $ttl 有效时间 0为永久
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * 删除缓存
     * @access public
     * @param iterable $keys 缓存变量名
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteMultiple($keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param string $key 缓存变量名
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($key): bool
    {
        return $this->store()->has($key);
    }

    /**
     * 缓存标签
     * @access public
     * @param string|array $name 标签名
     * @return TagSet
     */
    public function tag($name): TagSet
    {
        return $this->store()->tag($name);
    }
}
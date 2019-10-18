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

namespace think\session\driver;

use Psr\SimpleCache\CacheInterface;
use think\contract\SessionHandlerInterface;
use think\helper\Arr;

class Cache implements SessionHandlerInterface
{
    /** @var CacheInterface */
    protected $handler;

    /** @var integer */
    protected $expire;

    /** @var string */
    protected $prefix;

    public function __construct(\think\Cache $cache, array $config = [])
    {
        $this->handler = $cache->store(Arr::get($config, 'store'));
        $this->expire  = Arr::get($config, 'expire', 1440);
        $this->prefix  = Arr::get($config, 'prefix', '');
    }

    /**
     * 读取
     * @param string $sessionId
     * @return string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function read(string $sessionId): string
    {
        return (string) $this->handler->get($this->prefix . $sessionId);
    }

    /**
     * 删除
     * @param string $sessionId
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete(string $sessionId): bool
    {
        return $this->handler->delete($this->prefix . $sessionId);
    }

    /**
     * 写入
     * @param string $sessionId
     * @param string $sessData
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function write(string $sessionId, string $sessData): bool
    {
        return $this->handler->set($this->prefix . $sessionId, $sessData, $this->expire);
    }
}
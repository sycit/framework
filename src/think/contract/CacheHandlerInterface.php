<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  CacheHandlerInterface.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\contract;

/**
 * 缓存驱动接口
 * Interface CacheHandlerInterface
 * @package think\contract
 */
interface CacheHandlerInterface
{
    /**
     * 判断缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name);

    /**
     * 读取缓存
     * @access public
     * @param string $name    缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = null);

    /**
     * 写入缓存
     * @access public
     * @param string            $name   缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire 有效时间（秒）
     * @return bool
     */
    public function set($name, $value, $expire = null);

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int    $step 步长
     * @return false|int
     */
    public function inc(string $name, int $step = 1);

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int    $step 步长
     * @return false|int
     */
    public function dec(string $name, int $step = 1);

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function delete($name);

    /**
     * 清除缓存
     * @access public
     * @return bool
     */
    public function clear();

    /**
     * 删除缓存标签
     * @access public
     * @param array $keys 缓存标识列表
     * @return void
     */
    public function clearTag(array $keys);
}
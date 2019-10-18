<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  LogHandlerInterface.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\contract;

/**
 * 日志驱动接口
 * Interface LogHandlerInterface
 * @package think\contract
 */
interface LogHandlerInterface
{
    /**
     * 日志写入接口
     * @access public
     * @param  array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool;
}
<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  SessionHandlerInterface.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\contract;

/**
 * Session驱动接口
 * Interface SessionHandlerInterface
 * @package think\contract
 */
interface SessionHandlerInterface
{
    /**
     * 读取
     * @param string $sessionId
     * @return string
     */
    public function read(string $sessionId): string;

    /**
     * 删除
     * @param string $sessionId
     * @return bool
     */
    public function delete(string $sessionId): bool;

    /**
     * 写入
     * @param string $sessionId
     * @param string $sessData
     * @return bool
     */
    public function write(string $sessionId, string $sessData): bool;
}
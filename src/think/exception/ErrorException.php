<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/20
// +----------------------------------------------------------------------
// | Title:  ErrorException.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\exception;

use think\Exception;

/**
 * 封装 set_error_handler 和 register_shutdown_function 的错误
 * Class ErrorException
 * @package think\exception
 */
class ErrorException extends Exception
{
    /**
     * 用于保存错误级别
     * @var integer
     */
    protected $status;

    /**
     * 错误异常构造函数
     * @param  integer $status   错误级别
     * @param  string  $message  错误详细信息
     * @param  string  $file     出错文件路径
     * @param  integer $line     出错行号
     */
    public function __construct($status, string $message, string $file, int $line)
    {
        $this->status = $status;
        $this->file   = $file;
        $this->line   = $line;

        parent::__construct($message, 500);
    }

    /**
     * 获取错误级别
     * @return integer 错误级别
     */
    final public function getStatus()
    {
        return $this->status;
    }
}
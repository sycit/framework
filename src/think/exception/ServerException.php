<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\exception;

use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * 类不存在异常
 */
class ServerException extends \RuntimeException implements NotFoundExceptionInterface
{
    /**
     * 错误代码
     * @var string|int
     * @author Peter.Zhang
     */
    protected $errorCode;

    /**
     * 构造函数
     * @param string $errorCode    错误代码如：AS.001
     * @param string $message      错误描述
     * @param int $httpCode        返回的HTTP状态码
     * @param Throwable $previous  异常对象
     */
    public function __construct($errorCode, string $message = '', int $httpCode = 404, Throwable $previous = null)
    {
        $this->code       = $httpCode;
        $this->message    = $message;
        $this->errorCode  = $errorCode;

        parent::__construct($message, $httpCode, $previous);
    }

    /**
     * 获取错误代码
     * @access public
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }
}

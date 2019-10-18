<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  ServerException.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Class ServerException 服务器异常
 * @package think\exception
 */
class ServerException extends RuntimeException implements NotFoundExceptionInterface
{
    /**
     * API编码
     * @var string|int
     */
    private $apiCode;

    public function __construct($apiCode, string $message = null, Throwable $previous = null, int $httpCode = 0)
    {
        $this->message = $message;
        $this->apiCode = $apiCode;

        parent::__construct($message, $httpCode, $previous);
    }

    /**
     * 获取API状态码
     * @return string
     */
    public function getApiCode()
    {
        return $this->apiCode;
    }
}
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

/**
 * 服务器异常
 * Class ServerException
 * @package think\exception
 */
class ServerException extends \RuntimeException
{
    /**
     * API编码
     * @var int
     */
    private $status;

    public function __construct(int $status, string $message = null, \Throwable $previous = null, int $code = 500)
    {
        $this->status  = $status;
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取API状态码
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
}
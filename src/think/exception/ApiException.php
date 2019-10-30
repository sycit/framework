<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  ApiException.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\exception;

use RuntimeException;

/**
 * API异常
 * Class ApiException
 * @package think\exception
 */
class ApiException extends RuntimeException
{
    /**
     * API编码
     * @var int
     */
    private $status;

    /**
     * 响应头
     * @var array
     */
    private $headers;

    public function __construct(int $status, string $message = null, array $headers = [], int $code = 200)
    {
        $this->status  = $status;
        $this->headers = $headers;

        parent::__construct($message, $code);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
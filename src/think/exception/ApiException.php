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
 * Class ApiException API异常
 * @package think\exception
 */
class ApiException extends RuntimeException
{
    /**
     * API编码
     * @var integer
     */
    private $apiCode = 0;

    /**
     * 响应头
     * @var array
     */
    private $headers;

    public function __construct($apiCode, string $message = null, \Exception $previous = null, array $headers = [], int $httpCode = 200)
    {
        $this->apiCode = $apiCode;
        $this->headers = $headers;

        parent::__construct($message, $httpCode, $previous);
    }

    public function getApiCode()
    {
        return $this->apiCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
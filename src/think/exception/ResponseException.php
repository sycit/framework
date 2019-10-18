<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/19
// +----------------------------------------------------------------------
// | Title:  ResponseException.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\exception;

use RuntimeException;
use think\Response;

/**
 * 响应异常
 * Class ResponseException
 * @package think\exception
 */
class ResponseException extends RuntimeException
{
    /**
     * @var Response
     */
    protected $response;

    public function __construct(Response $response)
    {
        $this->response = $response;

        parent::__construct();
    }

    public function getResponse()
    {
        return $this->response;
    }
}
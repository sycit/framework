<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/27
// +----------------------------------------------------------------------
// | Title:  InvalidArgumentException.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\exception;

/**
 * 非法数据异常
 * Class InvalidArgumentException
 * @package think\exception
 */
class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * API编码
     * @var integer
     */
    private $apiCode = 0;

    public function __construct(string $message, $apiCode = 4000)
    {
        $this->apiCode = $apiCode;
        parent::__construct('invalid argument: ' . $message,200);
    }

    public function getApiCode()
    {
        return $this->apiCode;
    }
}
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
class InvalidArgumentException extends \RuntimeException
{
    /**
     * API编码
     * @var int
     */
    private $status;

    public function __construct(int $status, string $message = null, \Exception $previous = null, int $code = 200)
    {
        $this->status = $status;
        parent::__construct($message, $code, $previous);
    }

    public function getStatus()
    {
        return $this->status;
    }
}
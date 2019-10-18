<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  LogWrite.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\event;

/**
 * 日志write方法事件类
 * Class LogWrite
 * @package think\event
 */
class LogWrite
{
    /** @var string */
    public $channel;

    /** @var array */
    public $log;

    public function __construct($channel, $log)
    {
        $this->channel = $channel;
        $this->log     = $log;
    }
}
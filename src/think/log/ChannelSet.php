<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  ChannelSet.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\log;

use think\Log;

/**
 * Class ChannelSet
 * @package think\log
 * @mixin Channel
 */
class ChannelSet
{
    protected $log;
    protected $channels;

    public function __construct(Log $log, array $channels)
    {
        $this->log      = $log;
        $this->channels = $channels;
    }

    public function __call($method, $arguments)
    {
        foreach ($this->channels as $channel) {
            $this->log->channel($channel)->{$method}(...$arguments);
        }
    }
}
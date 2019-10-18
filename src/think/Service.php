<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Service.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

/**
 * 系统服务基础类
 * Class Service
 * @package think
 * @method void register()
 * @method void boot()
 */
abstract class Service
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 添加指令
     * @access protected
     * @param array|string $commands 指令
     */
    protected function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        Console::starting(function (Console $console) use ($commands) {
            $console->addCommands($commands);
        });
    }
}
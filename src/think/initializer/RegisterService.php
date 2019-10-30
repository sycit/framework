<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  RegisterService.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\initializer;

use think\App;
use think\service\ModelService;
use think\service\SeasLogService;

/**
 * 注册系统服务
 * Class RegisterService
 * @package think\initializer
 */
class RegisterService
{
    protected $services = [
        SeasLogService::class,
        ModelService::class,
    ];

    public function init(App $app)
    {
        foreach ($this->services as $service) {
            if (class_exists($service)) {
                $app->register($service);
            }
        }
    }
}
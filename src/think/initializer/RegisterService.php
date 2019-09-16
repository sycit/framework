<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\initializer;

use think\App;
use think\service\AnnotationRoute;
use think\service\ModelService;
use think\service\ValidateService;

/**
 * 注册系统服务
 */
class RegisterService
{

    protected $services = [
        AnnotationRoute::class,
        ValidateService::class,
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

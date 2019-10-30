<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/10/26
// +----------------------------------------------------------------------
// | Title:  SeasLogService.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\service;

use SeasLog;
use think\exception\ServerException;
use think\Service;

/**
 * SeasLog 日志系统服务类
 * Class SeasLogService
 * @package think\service
 */
class SeasLogService extends Service
{
    public function boot()
    {
        try {
            // 设置日志目录
            $path = $this->app->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR;
            SeasLog::setBasePath($path);
        } catch (\Throwable $exception) {
            throw new ServerException(5012, 'class not extension list: SeasLog', $exception);
        }
    }
}
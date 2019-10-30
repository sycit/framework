<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/19
// +----------------------------------------------------------------------
// | Title:  DevelopDebug.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\event\LogWrite;
use think\Request;
use think\Response;

/**
 * 开发者调试中间件
 * Class DevelopDebug
 * @package think\middleware
 */
class DevelopDebug
{
    /**
     * Trace日志
     * @var array
     */
    protected $log = [];

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /** @var App */
    protected $app;

    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->config = array_merge($this->config, $app->getReadsConfig('trace'));
    }

    /**
     * 页面调试
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return void
     */
    public function handle($request, Closure $next)
    {
        $debug = $this->app->isDebug();

        // 注册日志监听
        if ($debug) {
            $this->log = [];
            $this->app->event->listen(LogWrite::class, function ($event) {
                if (empty($this->config['channel']) || $this->config['channel'] == $event->channel) {
                    $this->log = array_merge_recursive($this->log, $event->log);
                }
            });
        }

        $response = $next($request);

        // 调试注入
        if ($debug) {
            $this->traceDebug($response, $data);
            $response->debug($data);
        }

        return $response;
    }

    public function traceDebug(Response $response, &$data)
    {
        $config = $this->config;
        $type   = !empty($config['type']) ? $config['type'] : '\\think\\debug\\Json';

        unset($config['type']);

        $trace = App::factory($type, '', $config);

        $log  = $this->app->log->getLog();
        $log  = array_merge_recursive($this->log, $log);
        $data = $trace->output($this->app, $response, $log);
    }
}
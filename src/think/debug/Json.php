<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/8/31
// +----------------------------------------------------------------------
// | Title:  Json.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\debug;

use think\App;
use think\Response;

/**
 * Json调试输出
 */
class Json
{
    protected $config = [];

    // 实例化并传入参数
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function output(App $app, Response $response, array $log = [])
    {
        $request = $app->request;

        // 获取基本信息
        $runtime = number_format(microtime(true) - $app->getBeginTime(), 10);
        $reqs    = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';
        $mem     = number_format((memory_get_usage() - $app->getBeginMem()) / 1024, 2);

        if ($request->host()) {
            $uri = $request->server('SERVER_PROTOCOL') . ' ' . $request->method() . ' : ' . $request->url(true);
        } else {
            $uri = 'cmd:' . implode(' ', $_SERVER['argv']);
        }

        // 信息
        $info = $this->getFileInfo();

        $base = [
            '请求信息: ' . date('Y-m-d H:i:s', $request->time()) . ' ' . $uri,
            '运行时间: ' . number_format((float) $runtime, 6) . 's',
            '吞 吐 率: ' . $reqs . 'req/s',
            '内存消耗: ' . $mem . 'kb',
            '文件加载: ' . count($info),
            '查询信息: ' . $app->db->getQueryTimes() . ' queries',
        ];

        if ($app->has('cache')) {
            $base[] = '缓存信息: ' . $app->cache->getReadTimes() . ' reads,' . $app->cache->getWriteTimes() . ' writes';
        }

        if ($app->has('session') && $app->session->getId()) {
            $base[] = '会话信息: SESSION_ID=' . $app->session->getId();
        }

        // 页面Trace信息
        $trace = [];
        foreach ($this->config['trace_tabs'] as $name => $title) {
            $name = strtolower($name);
            switch ($name) {
                case 'base': // 基本信息
                    $trace[$title] = $base;
                    break;
                case 'file': // 文件信息
                    $trace[$title] = $info;
                    break;
                default: // 调试信息
                    if (strpos($name, '|')) {
                        // 多组信息
                        $names  = explode('|', $name);
                        $result = [];
                        foreach ($names as $item) {
                            $result = array_merge($result, $log[$item] ?? []);
                        }
                        $trace[$title] = $result;
                    } else {
                        $trace[$title] = $log[$name] ?? '';
                    }
            }
        }

        //输出到控制台
        $lines = [];
        foreach ($trace as $type => $msg) {
            $lines[$type] = $this->console($type, $msg);
        }

        unset($log);
        unset($info);
        unset($base);
        unset($trace);
        unset($runtime);
        unset($request);

        return $lines;
    }

    protected function console(string $type, $msg)
    {
        $type = strtolower($type);
        $line = [];

        foreach ((array) $msg as $key => $m) {
            switch ($type) {
                case '调试':
                    $var_type = gettype($m);
                    if (in_array($var_type, ['array', 'string'])) {
                        $line[] = $m;
                    } else {
                        $line[] = var_export($m, true);
                    }
                    break;
                case '错误':
                case 'sql':
                    $line[] = str_replace("\n", '\n', addslashes($m));
                    break;
                default:
                    $key = is_string($key) ? $key : $key + 1;
                    $line[$key] = $m;
                    break;
            }
        }

        return $line;
    }

    /**
     * 获取文件加载信息
     * @access protected
     * @return integer|array
     */
    protected function getFileInfo()
    {
        $files = get_included_files();
        $info  = [];

        foreach ($files as $key => $file) {
            $info[] = $file . ' ( ' . number_format(filesize($file) / 1024, 2) . ' KB )';
        }

        return $info;
    }
}
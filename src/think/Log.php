<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Log.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use SeasLog;
use Psr\Log\LoggerInterface;

/**
 * 日志管理类
 * Class Log
 * @package think
 */
class Log implements LoggerInterface
{
    /**
     * @var App
     */
    protected $app;

    /**
     * 模块目录
     * @var string
     */
    protected $module = 'default';

    /**
     * 日志信息
     * @var array
     */
    protected $log = [];

    /**
     * 关闭日志
     * @var array
     */
    protected $close = false;

    /**
     * 日志等级
     * @var array
     */
    protected $level = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG', 'SQL', 'LOG'];

    /**
     * 构造方法
     * Log constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 设置/获取 日志根目录
     * @param string $path
     * @return $this|string
     */
    public function path(string $path = '')
    {
        if ('' === $path) {
            return SeasLog::getBasePath();
        }
        SeasLog::setBasePath($path);
        return $this;
    }

    /**
     * 设置/获取 模块目录
     * @param string $module
     * @return $this|string
     */
    public function module(string $module = '')
    {
        if ('' === $module) {
            return $this->module;
        }
        $this->module = $module;
        return $this;
    }

    /**
     * 获取日志信息
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * 关闭日志
     */
    public function close()
    {
        $this->log   = [];
        $this->close = true;
    }

    /**
     * 记录日志信息
     * @param $message
     * @param string $level
     * @param array  $content
     * @param string $module
     * @return bool
     */
    public function record($message, string $level = 'LOG', array $content = [], string $module = ''): bool
    {
        if ($this->close) {
            return false;
        }

        $level  = mb_strtoupper($level, 'UTF-8');
        $level  = in_array($level, $this->level, true) ? $level : 'LOG';

        $module = '' === $module ? $this->module() : $module;
        $logger = $this->app->config->get('app.logger_module', []);
        $module = in_array($module, $logger) ? $module : 'default';

        $log = [
            'level'   => $level,
            'message' => $message,
            'content' => $content,
            'module'  => $module,
        ];

        $this->log[] = $log;

        return true;
    }

    /**
     * 写入日志信息
     * @return bool
     */
    public function save()
    {
        foreach ($this->log as $log) {
            SeasLog::log($log['level'], $log['message'], $log['content'], $log['module']);
        }

        return true;
    }

    /**
     * 通用日志方法
     * @param string $level   日志级别
     * @param mixed  $message 日志信息
     * @param array  $context 替换内容
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $this->record($message, $level, $context);
    }

    /**
     * 记录emergency日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /**
     * 记录alert日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    /**
     * 记录critical日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * 记录error日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * 记录warning日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * 记录notice日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    /**
     * 记录info日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * 记录debug日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * 记录sql日志
     * @param mixed $message 日志信息
     * @param array $context 替换内容
     * @return void
     */
    public function sql($message, array $context = []): void
    {
        $this->log('SQL', $message, $context);
    }

    public function __call($method, $parameters)
    {
        $this->log($method, ...$parameters);
    }
}
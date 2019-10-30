<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/8/10
// +----------------------------------------------------------------------
// | Title:  helper.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

//------------------------
// ThinkPMS 助手函数
//-------------------------

use think\App;
use think\Container;
use think\exception\ApiException;
use think\exception\ResponseException;
use think\facade\Lang;
use think\facade\Log;
use think\Response;

if (!function_exists('abort')) {
    /**
     * 抛出HTTP异常
     * @param integer|Response $status    错误码 或者 Response对象实例
     * @param string           $message 错误信息
     * @param array            $header  参数
     * @param int              $code    HTTP状态码
     */
    function abort($status, string $message = null, array $header = [], int $code = 200)
    {
        if ($status instanceof Response) {
            throw new ResponseException($status);
        }

        throw new ApiException($status, $message, $header, $code);
    }
}

if (!function_exists('app')) {
    /**
     * 快速获取容器中的实例 支持依赖注入
     * @param string $name        类名或标识 默认获取当前应用实例
     * @param array  $args        参数
     * @param bool   $newInstance 是否每次创建新的实例
     * @return object|App
     */
    function app(string $name = '', array $args = [], bool $newInstance = false)
    {
        return Container::getInstance()->make($name ?: App::class, $args, $newInstance);
    }
}

if (!function_exists('lang')) {
    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array  $vars 动态变量值
     * @param string $lang 语言
     * @return mixed
     */
    function lang(string $name, array $vars = [], string $lang = '')
    {
        return Container::getInstance()->has('lang') ? Lang::get($name, $vars, $lang) : $name;
    }
}

if (!function_exists('invoke')) {
    /**
     * 调用反射实例化对象或者执行方法 支持依赖注入
     * @param mixed $call 类名或者callable
     * @param array $args 参数
     * @return mixed
     */
    function invoke($call, array $args = [])
    {
        if (is_callable($call)) {
            return Container::getInstance()->invoke($call, $args);
        }

        return Container::getInstance()->invokeClass($call, $args);
    }
}

if (!function_exists('parse_name')) {
    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name    字符串
     * @param int    $type    转换类型
     * @param bool   $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    function parse_name(string $name, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

if (!function_exists('response')) {
    /**
     * 创建普通 Response 对象实例
     * @param mixed      $data   输出数据
     * @param int|string $code   状态码
     * @param array      $header 头信息
     * @param string     $type
     * @return Response
     */
    function response($data = '', $code = 200, $header = [], $type = ''): Response
    {
        return Response::create($data, $type, $code)->header($header);
    }
}

if (!function_exists('log')) {
    /**
     * 记录日志信息
     * @param mixed  $log      log信息 支持字符串和数组
     * @param string $level   日志级别
     * @param array  $content 替换内容
     * @param string $module  日志模块
     * @return array|void
     */
    function log($log = '', string $level = 'LOG', array $content = [], string $module = '')
    {
        if ('' === $log) {
            return Log::getLog();
        }

        Log::record($log, $level, $content, $module);
    }
}
<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/23
// +----------------------------------------------------------------------
// | Title:  Domain.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\route;

use think\helper\Str;
use think\Request;
use think\Route;

/**
 * 域名路由绑定
 * Class Domain
 * @package think\route
 */
class Domain
{
    /**
     * 路由对象
     * @var Route
     */
    protected $route;

    /**
     * url地址
     * @var string|array
     */
    protected $url;

    /**
     * 架构函数
     * @param  Route $route   路由对象
     */
    public function __construct(Route $route)
    {
        $this->route  = $route;
    }

    public function checkBind(Request $request, string $url)
    {
        $this->url = $url;

        // 路由绑定规则
        list($rule, $bind) = $this->parseBind();

        if (!empty($bind)) {

            $this->parseBindAppendParam($rule, $bind);

            // 如果有URL绑定 则进行绑定检测
            $type = substr($bind, 0, 1);
            $bind = substr($bind, 1);

            $bindTo = [
                '\\' => 'bindToClass',      // 绑定到类
                '@'  => 'bindToController', // 绑定到控制器
                ':'  => 'bindToNamespace',  // 绑定到命名空间
            ];

            if (isset($bindTo[$type])) {
                return $this->{$bindTo[$type]}($request, $url, $bind);
            }
        }

        return false;
    }

    /**
     * 解析绑定规则
     * @return array
     * @author Peter.Zhang
     */
    protected function parseBind(): array
    {
        $value = [null, null];

        $array  = explode('|', $this->url);
        $array0 = !empty($array[0]) ? (string)$array[0] : '';
        $array1 = !empty($array[1]) ? (string)$array[1] : '';

        if ($array0 == '' ||  $array1 == '') {
            return $value;
        }

        // 第一个单元移出
        array_shift($array);

        $bind   = $this->route->bind();
        $bindTo = ['\\', '@', ':'];

        foreach ($bind as $item => $val) {
            $rule = explode('/', $item);
            if ($rule[0] == $array0 && is_string($val) && in_array(substr($val, 0, 1), $bindTo)) {
                if ($this->verifyItemRule($item, $array)) {
                    $value = [$item, $val];
                    break;
                }
            }

            unset($rule);
        }

        return $value;
    }

    /**
     * 验证规则 [:name, :id]
     * @param string $item  路由规则
     * @param array  $url   URL数组
     * @return bool
     * @author Peter.Zhang
     */
    protected function verifyItemRule(string $item, array $url): bool
    {
        $item = explode('/', $item);

        // 第一个单元移出
        array_shift($item);

        $count = count($item);

        if ($count !== count($url)) {
            return false;
        }

        $regex = 0;
        foreach ($item as $key => $val) {
            if (isset($url[$key]) && ':' == substr($val, 0, 1)) {
                $regex = $this->regex($url[$key], $val) ? $regex + 1 : $regex + 0;
            }
        }

        return $count === $regex ? true : false;
    }

    /**
     * 路由隐式参数
     * @param string $rule
     * @param string $bind
     * @author Peter.Zhang
     */
    protected function parseBindAppendParam(string $rule, string &$bind): void
    {
        if (false !== strpos($bind, '?')) {
            list($bind, $query) = explode('?', $bind);

            parse_str($query, $vars);

            $url  = explode('|', $this->url);
            $rule = explode('/', $rule);

            array_shift($url);
            array_shift($rule);

            foreach ($vars as $item => $value) {
                foreach ($rule as $key=> $val) {
                    if ($val != $value) {
                        continue;
                    }
                    if (isset($url[$key])) {
                        $vars[$item] = $url[$key];
                    }

                }
            }

            $this->route->setAppend($vars);
        }
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access protected
     * @param  string $url 路由规则
     * @param  array  $var 变量
     * @return void
     */
    protected function parseUrlParams(string $url, array &$var = []): void
    {
        if ($url) {
            preg_replace_callback('/(\w+)\|([^|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, $url);
        }
    }

    /**
     * URL 正则匹配
     * @param string $value
     * @param string $rule
     * @return bool
     * @author Peter.Zhang
     */
    protected function regex(string $value, string $rule): bool
    {
        switch ($rule) {
            case ':name':
                $result = preg_match('/^[A-Za-z0-9\-_]+$/', $value) ? true : false;
                break;
            case ':year':
                // 是否是一个有效日期
                $result = false !== strtotime($value);
                break;
            case ':id':
                $result = ctype_digit($value) ? true : false;
                break;
            default:
                $result = false;
                break;
        }

        return $result;
    }

    /**
     * 绑定到类
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $class 类名（带命名空间）
     * @return Dispatch
     */
    protected function bindToClass(Request $request, string $url, string $class): Dispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->route->config('default_action');
        $param  = [];

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1], $param);
        }

        return new Dispatch($request, $this->route, [$class, Str::snake((string)$action)], $param, true);
    }

    /**
     * 绑定到命名空间
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $namespace 命名空间
     * @return Dispatch
     */
    protected function bindToNamespace(Request $request, string $url, string $namespace): Dispatch
    {
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : $this->route->config('default_controller');
        $method = !empty($array[1]) ? $array[1] : $this->route->config('default_action');
        $param  = [];

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2], $param);
        }

        return new Dispatch($request, $this->route, [$namespace . '\\' . Str::studly((string)$class), Str::snake((string)$method)], $param, true);
    }

    /**
     * 绑定到控制器
     * @access protected
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $controller 控制器名
     * @return Dispatch
     */
    protected function bindToController(Request $request, string $url, string $controller): Dispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->route->config('default_action');
        $param  = [];

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1], $param);
        }

        $controller = $this->route->getNamespace($controller);

        return new Dispatch($request, $this->route, [$controller, Str::snake((string)$action)], $param, true);
    }
}
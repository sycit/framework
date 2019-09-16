<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/8/10
// +----------------------------------------------------------------------
// | Title:  Route.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use Closure;
use think\exception\ResponseException;
use think\helper\Str;
use think\middleware\CheckRequestCache;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;

/**
 * 路由管理类
 */
class Route
{
    /**
     * 当前应用
     * @var App
     */
    protected $app;

    /**
     * 请求URL
     * @var string
     * @author Peter.Zhang
     */
    protected $reqUrl;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 当前HOST
     * @var string
     */
    protected $host;

    /**
     * 路由绑定
     * @var array
     */
    protected $bindDomain = [];

    /**
     * 路由变量
     * @var array
     */
    protected $param;

    /**
     * 路由参数
     * @var array
     */
    protected $option = [];

    /**
     * 路由变量规则
     * @var array
     */
    protected $pattern = [];

    /**
     * 分组路由接口
     * @var array
     */
    protected $rules = [];

    /**
     * URL变量
     * @var array
     */
    protected $vars = [];

    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->reqUrl = str_replace($this->app->config->get('app.pathinfo_depr'), '|', $this->app->request->pathinfo());
    }

    public function config(string $name = null)
    {
        if (is_null($name)) {
            return $this->app->config->get($name);
        }

        return $this->app->config->get($name) ?? null;
    }

    /**
     * 路由调度
     * @param Request $request
     * @param Closure $withRoute
     * @return Response
     * @author Peter.Zhang
     */
    public function dispatch(Request $request, $withRoute = null): Response
    {
        $this->request = $request;
        $this->host    = $this->request->host(true);

        if ($withRoute) {
            $checkCallback = function () use ($withRoute) {
                //加载路由
                $withRoute();
                return $this->check();
            };

            $dispatch = $checkCallback();
        } else {
            $dispatch = $this->urlDispatch($this->reqUrl);
        }

        $dispatch->init($this->app);

        $this->app->middleware->add(function () use ($dispatch) {
            try {
                $response = $dispatch->run();
            } catch (ResponseException $exception) {
                $response = $exception->getResponse();
            }

            return $response;
        });

        return $this->app->middleware->dispatch($request);
    }

    /**
     * 检测URL路由
     * @access public
     * @return ControllerDispatch
     */
    public function check()
    {
        $result = $this->checkBind();

        if ($result && !empty($this->option['append'])) {
            $this->request->setRoute($this->option['append']);
            unset($this->option['append']);
        }

        if (false !== $result) {
            return $result;
        }

        return $this->urlDispatch($this->reqUrl);
    }

    /**
     * 设置路由参数
     * @access public
     * @param  array $option 参数
     * @return $this
     */
    public function option(array $option)
    {
        $this->option = array_merge($this->option, $option);

        return $this;
    }

    /**
     * 设置单个路由参数
     * @access public
     * @param  string $name  参数名
     * @param  mixed  $value 值
     * @return $this
     */
    public function setOption(string $name, $value)
    {
        $this->option[$name] = $value;

        return $this;
    }

    /**
     * 获取路由参数定义
     * @access public
     * @param  string $name 参数名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function getOption(string $name = '', $default = null)
    {
        if ('' === $name) {
            return $this->option;
        }

        return $this->option[$name] ?? $default;
    }

    /**
     * 附加路由隐式参数
     * @access public
     * @param  array $append 追加参数
     * @return $this
     */
    public function append(array $append = [])
    {
        $this->option['append'] = $append;

        return $this;
    }

    /**
     * 设置参数过滤检查
     * @access public
     * @param  array $filter 参数过滤
     * @return $this
     */
    public function filter(array $filter)
    {
        $this->option['filter'] = $filter;

        return $this;
    }

    /**
     * 绑定验证
     * @access public
     * @param  mixed  $validate 验证器类
     * @param  string $scene 验证场景
     * @param  array  $message 验证提示
     * @param  bool   $batch 批量验证
     * @return $this
     */
    public function validate($validate, string $scene = null, array $message = [], bool $batch = false)
    {
        $this->option['validate'] = [$validate, $scene, $message, $batch];

        return $this;
    }

    /**
     * 指定路由中间件
     * @access public
     * @param  string|array|Closure $middleware 中间件
     * @param  mixed                $param 参数
     * @return $this
     */
    public function middleware($middleware, $param = null)
    {
        if (is_null($param) && is_array($middleware)) {
            $this->option['middleware'] = $middleware;
        } else {
            foreach ((array) $middleware as $item) {
                $this->option['middleware'][] = [$item, $param];
            }
        }

        return $this;
    }

    /**
     * 注册变量规则
     * @access public
     * @param  array $pattern 变量规则
     * @return $this
     */
    public function pattern(array $pattern)
    {
        $this->pattern = array_merge($this->pattern, $pattern);

        return $this;
    }

    /**
     * 设置路由缓存
     * @access public
     * @param  array|string $cache 缓存
     * @return $this
     */
    public function cache($cache)
    {
        return $this->middleware(CheckRequestCache::class, $cache);
    }

    /**
     * URL 正则匹配
     * @param string $value
     * @param string $rule
     * @return bool
     * @author Peter.Zhang
     */
    public function regex(string $value, string $rule): bool
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
     * 解析URL的pathinfo参数
     * @access public
     * @param  string $url URL地址
     * @return array
     */
    public function parseUrlPath(string $url): array
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');

        if (strpos($url, '/')) {
            // [控制器/操作]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }

        return $path;
    }

    /**
     * 设定绑定规则
     * @param array $bind
     * @return $this
     * @author Peter.Zhang
     */
    public function bindDomain(array $bind)
    {
        $this->bindDomain = $bind;

        return $this;
    }

    /**
     * 默认URL调度
     * @access public
     * @param string $url URL地址
     * @return ControllerDispatch
     */
    public function urlDispatch(string $url): ControllerDispatch
    {
        return new ControllerDispatch($this->request, $this, $url);
    }

    /**
     * 检测域名的路由规则
     * @return mixed
     */
    protected function checkBind()
    {
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
                return $this->{$bindTo[$type]}($this->reqUrl, $bind);
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

        if (empty($this->bindDomain)) {
            return $value;
        }

        $array  = explode('|', $this->reqUrl);
        $array0 = !empty($array[0]) ? (string)$array[0] : '';
        $array1 = !empty($array[1]) ? (string)$array[1] : '';

        if ($array0 == '' ||  $array1 == '') {
            return $value;
        }

        // 第一个单元移出
        array_shift($array);

        $bindTo = ['\\', '@', ':'];

        foreach ($this->bindDomain as $item => $val) {
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

            $url  = explode('|', $this->reqUrl);
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

            $this->append($vars);
        }
    }

    /**
     * 绑定到类
     * @access protected
     * @param  string    $url URL地址
     * @param  string    $class 类名（带命名空间）
     * @return CallbackDispatch
     */
    protected function bindToClass(string $url, string $class): CallbackDispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->config('default_action');
        $param  = [];

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1], $param);
        }

        return new CallbackDispatch($this->request, $this, [$class, $action], $param);
    }

    /**
     * 绑定到命名空间
     * @access protected
     * @param  string    $url URL地址
     * @param  string    $namespace 命名空间
     * @return CallbackDispatch
     */
    protected function bindToNamespace(string $url, string $namespace): CallbackDispatch
    {
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : $this->config('default_controller');
        $method = !empty($array[1]) ? $array[1] : $this->config('default_action');
        $param  = [];

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2], $param);
        }

        return new CallbackDispatch($this->request, $this, [$namespace . '\\' . Str::studly((string)$class), $method], $param);
    }

    /**
     * 绑定到控制器
     * @access protected
     * @param  string    $url URL地址
     * @param  string    $controller 控制器名
     * @return ControllerDispatch
     */
    protected function bindToController(string $url, string $controller): ControllerDispatch
    {
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->config('default_action');
        $param  = [];

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1], $param);
        }

        return new ControllerDispatch($this->request, $this, $controller . '/' . $action, $param);
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
     * 设置全局的路由分组参数
     * @access public
     * @param string $method 方法名
     * @param array $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (count($args) > 1) {
            $args[0] = $args;
        }
        array_unshift($args, $method);

        return call_user_func_array([$this, 'setOption'], $args);
    }
}
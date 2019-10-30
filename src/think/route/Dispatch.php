<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/19
// +----------------------------------------------------------------------
// | Title:  Dispatch.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\route;

use ReflectionException;
use ReflectionMethod;
use think\App;
use think\exception\InvalidArgumentException;
use think\exception\ApiException;
use think\helper\Str;
use think\Request;
use think\Response;
use think\Route;

/**
 * 路由调度管理类
 * Class Dispatch
 * @package think\route
 */
class Dispatch
{
    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 路由规则
     * @var Route
     */
    protected $route;

    /**
     * 调度信息
     * @var array
     */
    protected $dispatch = [];

    /**
     * 参数
     * @var array
     */
    protected $param = [];

    /**
     * 域名绑定
     * @var bool
     */
    protected $domain = false;

    // 构造函数
    public function __construct(Request $request, Route $route, $dispatch, array $param = [], bool $domain = false)
    {
        $this->request  = $request;
        $this->route    = $route;
        $this->dispatch = $dispatch;
        $this->param    = array_merge($this->param, $param);
        $this->domain   = $domain;
    }

    /**
     * 调度初始化
     * @param App $app
     */
    public function init(App $app)
    {
        $this->app = $app;

        if (!$this->domain) {
            $this->dispatch($this->dispatch);
            // 判断绑定的应用
            if ($this->hasDefinedBindDomain()) {
                throw new InvalidArgumentException(5022, 'invalid request: ' . $this->request->app());
            }
        }

        // 执行路由注解操作
        $this->doAnnotation();
    }

    /**
     * 执行路由调度
     * @access public
     * @return mixed
     */
    public function run(): Response
    {
        // 执行实例化控制器
        $data = $this->exec();

        return $this->autoResponse($data);
    }

    /**
     * 路由注解操作
     * @access protected
     * @return void
     */
    protected function doAnnotation()
    {
        list($class, $action) = $this->dispatch;

        // 返回注解数组
        $comment = $this->app->annotation->dispatch($class, $action);

        // 判断URL请求方法
        $this->hasMethod($action,$comment['url'] ?? 'GET');

        // 解释Input参数
        $this->hasInput($comment['input'] ?? []);

        // 添加中间件
        $this->app->middleware->import($comment['middleware'] ?? []);

        // 记录接口参数
        $this->request->set($this->param);
    }

    /**
     * 判断Input参数
     * @param array $input
     * @return void
     */
    protected function hasInput(array $input): void
    {
        foreach ($input as $item => $value) {
            $param = $this->request->data($item);

            if (is_null($param) && !empty($value['default'])) {
                $this->param[$item] = $value['default'];
                continue;
            }

            if (is_null($param)) {
                throw new ApiException(4003,'invalid argument: ' . $item);
            }

            // 判断有效格式 支持自定 'ctype_' 开头函数
            if (isset($value['type']) && !$this->app->validate->is($param, $value['type'])) {
                throw new ApiException(4003,'invalid argument: ' . $item);
            }

            // 判断长度
            if (isset($value['length']) && !$this->app->validate->length($param, $value['length'])) {
                throw new ApiException(4003,'invalid argument: ' . $item);
            }

            $this->param[$item] = $param;
        }
    }

    /**
     * 判断URL请求方法
     * @param string $name  操作名
     * @param string $value 请求值
     * @return void
     */
    protected function hasMethod(string $name, string $value): void
    {
        $method = $this->request->method();
        $rest   = $this->app->config->get('app.route_rest_action', []);

        if (isset($rest[$name])) {
            $rest   = Str::upper($rest[$name]);
            $result = $method === $rest ? true : false;
        } elseif (strpos($value, '|')) {
            $value  = explode('|', $value);
            $result = in_array($method, $value) ? true : false;
        } elseif ('*' == $value || $value === $method) {
            $result = true;
        } else {
            $result = false;
        }

        if (false === $result) {
            throw new ApiException(4003,'request method error');
        }

        // 设置请求方法
        $this->request->setMethod($method);
    }

    /**
     * 判断绑定的应用
     * @return bool
     * @author Peter.Zhang
     */
    protected function hasDefinedBindDomain(): bool
    {
        $multi = $this->route->config('app.auto_multi_app');
        $bind  = $this->route->config('app.domain_bind');

        if (!$multi || empty($bind)) {
            return false;
        }

        $appName   = $this->request->app();
        $subDomain = $this->request->subDomain();

        foreach ($bind as $key => $val) {
            if ('*' == $key) {
                continue;
            }

            if ($subDomain != $key && $appName == $val) {
                return true;
            }
        }

        return false;
    }

    /**
     * 严格解释调度信息
     * @param $dispatch
     */
    protected function dispatch($dispatch)
    {
        if (is_string($dispatch)) {
            $dispatch = explode('/', $dispatch);
        }

        // 获取控制器名
        $controller = strip_tags($dispatch[0] ?: $this->app->config->get('app.default_controller'));

        if (strpos($controller, '.')) {
            $pos        = strrpos($controller, '.');
            $controller = substr($controller, 0, $pos) . '.' . Str::studly(substr($controller, $pos + 1));
        } else {
            $controller = Str::studly($controller);
        }

        // 获取操作名
        $actionName = Str::snake(strip_tags($dispatch[1] ?: $this->app->config->get('app.default_action')));

        // 补全控制器
        $controller = $this->route->getNamespace($controller);

        // 重置调度信息
        $this->dispatch = [$controller, $actionName];
    }

    /**
     * 执行实例化控制器
     * @return mixed
     */
    protected function exec()
    {
        list($class, $action) = $this->dispatch;

        // 实例化控制器
        $instance = $this->app->make($class, [], true);

        // 获取控制器名
        $controller = explode('\\', $class);
        $controller = array_pop($controller);

        // 设置当前请求的控制器
        $this->request->setController($controller);

        return $this->app->middleware->pipeline('controller')->send($this->request)
            ->then(function () use ($instance, $action) {
                try {
                    $reflect = new ReflectionMethod($instance, $action);
                    $vars    = $this->request->get();
                    // 设置当前请求的操作方法名
                    $this->request->setAction($reflect->getName());
                    // 执行类的方法
                    $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);
                } catch (ReflectionException $e) {
                    throw new InvalidArgumentException(5024, 'method not exists: ' . $action, $e);
                }

                return $this->autoResponse($data);
            });
    }

    /**
     * 响应输出类型
     * @param $data
     * @return Response
     */
    protected function autoResponse($data): Response
    {
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认响应输出类型
            $response = Response::create($data, $this->app->response());
        } else {
            $data = ob_get_clean();
            $content  = false === $data ? '' : $data;
            $code     = '' === $content  ? 204 : 200;
            $response = Response::create($content, $this->app->response(), $code);
        }

        return $response;
    }
}
<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\route\dispatch;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use think\App;
use think\exception\ServerException;
use think\exception\ApiException;
use think\helper\Str;
use think\Request;
use think\Route;
use think\route\Dispatch;

/**
 * Controller Dispatcher
 */
class Controller extends Dispatch
{
    /**
     * 控制器名
     * @var string
     */
    protected $controller;

    /**
     * 操作名
     * @var string
     */
    protected $actionName;

    public function __construct(Request $request, Route $route, $dispatch, array $param = [], int $code = null)
    {
        $this->request = $request;
        $this->route   = $route;
        $this->param   = array_merge($this->param, $param);
        // 解析默认的URL规则
        $dispatch = $this->parseUrl($dispatch);

        parent::__construct($request, $route, $dispatch, $this->param, $code);
    }

    public function init(App $app)
    {
        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        // 获取控制器名
        $controller = strip_tags($result[0] ?: $this->route->config('app.default_controller'));

        if (strpos($controller, '.')) {
            $pos              = strrpos($controller, '.');
            $this->controller = substr($controller, 0, $pos) . '.' . Str::studly(substr($controller, $pos + 1));
        } else {
            $this->controller = Str::studly($controller);
        }

        // 获取操作名
        $this->actionName = Str::snake(strip_tags($result[1] ?: $this->route->config('app.default_action')));

        // 设置当前请求的控制器、操作
        $this->request
            ->setController($this->controller)
            ->setAction($this->actionName);


        // 执行路由后续操作
        parent::init($app);
    }

    public function exec()
    {
        try {
            // 实例化控制器
            $instance = $this->controller($this->controller);

            // 注册控制器中间件
            $this->registerControllerMiddleware($instance);
        } catch (ServerException $e) {
            throw new ApiException($e->getErrorCode(), 'controller not exists:' . $e->getMessage(), $e, [] , 404);
        } catch (ReflectionException $e) {
            throw new ApiException(404, 'Error: register controller middleware', $e, [] , 404);
        }

        $this->app->middleware->controller(function (Request $request, $next) use ($instance) {
            // 获取当前操作名
            $action = $this->actionName;

            if (is_callable([$instance, $action])) {
                $vars = $this->request->param();
                try {
                    $reflect = new ReflectionMethod($instance, $action);
                    // 严格获取当前操作方法名
                    $actionName = $reflect->getName();
                    $this->request->setAction($actionName);
                } catch (ReflectionException $e) {
                    $reflect = new ReflectionMethod($instance, '__call');
                    $vars    = [$action, $vars];
                    $this->request->setAction($action);
                }
            } else {
                // 操作不存在
                throw new ApiException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
            }

            $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);

            return $this->autoResponse($data);
        });

        return $this->app->middleware->dispatch($this->request, 'controller');
    }

    /**
     * 使用反射机制注册控制器中间件
     * @access public
     * @param object $controller 控制器实例
     * @return void
     * @throws ReflectionException
     */
    protected function registerControllerMiddleware($controller): void
    {
        $class = new ReflectionClass($controller);

        if ($class->hasProperty('middleware')) {
            $reflectionProperty = $class->getProperty('middleware');
            $reflectionProperty->setAccessible(true);

            $middlewares = $reflectionProperty->getValue($controller);

            foreach ($middlewares as $key => $val) {
                if (!is_int($key)) {
                    if (isset($val['only']) && !in_array($this->request->action(true), array_map(function ($item) {
                        return strtolower($item);
                    }, $val['only']))) {
                        continue;
                    } elseif (isset($val['except']) && in_array($this->request->action(true), array_map(function ($item) {
                        return strtolower($item);
                    }, $val['except']))) {
                        continue;
                    } else {
                        $val = $key;
                    }
                }

                $this->app->middleware->controller($val);
            }
        }
    }

    /**
     * 实例化访问控制器
     * @access public
     * @param  string $name 资源地址
     * @return object
     * @throws ServerException
     */
    public function controller(string $name)
    {
        $class = $this->app->parseClass('controller', $name);

        if (class_exists($class)) {
            return $this->app->make($class, [], true);
        }

        throw new ServerException(50014, $class);
    }
}

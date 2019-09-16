<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/8/13
// +----------------------------------------------------------------------
// | Title:  Dispatch.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\route;

use think\App;
use think\Container;
use think\exception\ApiException;
use think\Request;
use think\Response;
use think\Route;
use think\Validate;

/**
 * 路由调度基础类
 */
abstract class Dispatch
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
     * @var mixed
     */
    protected $dispatch;

    /**
     * 路由变量
     * @var array
     */
    protected $param = [];

    /**
     * 状态码
     * @var int
     */
    protected $code;

    public function __construct(Request $request, Route $route, $dispatch, array $param = [], int $code = null)
    {
        $this->request  = $request;
        $this->route    = $route;
        $this->dispatch = $dispatch;
        $this->param    = array_merge($this->param, $param);
        $this->code     = $code;
    }

    public function init(App $app)
    {
        $this->app = $app;

        // 执行路由后置操作
        $this->doRouteAfter();
    }

    /**
     * 执行路由调度
     * @access public
     * @return mixed
     */
    public function run(): Response
    {
        $data = $this->exec();

        return $this->autoResponse($data);
    }

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
            $status   = '' === $content  ? 204 : 200;
            $response = Response::create($content, $this->app->response(), $status);
        }

        return $response;
    }

    /**
     * 检查路由后置操作
     * @access protected
     * @return void
     */
    protected function doRouteAfter(): void
    {
        $option = $this->route->getOption();

        // 数据自动验证
        if (isset($option['validate'])) {
            $this->autoValidate($option['validate']);
        }

        // 添加中间件
        if (!empty($option['middleware'])) {
            $this->app->middleware->import($option['middleware']);
        }

        if (!empty($option['append'])) {
            $this->param = array_merge($this->param, $option['append']);
        }

        // 绑定模型数据
        if (!empty($option['model'])) {
            $this->createBindModel($option['model'], $this->param);
        }

        // 记录当前请求的路由规则
        //$this->request->setRule($this->rule);

        // 记录路由变量
        $this->request->setRoute($this->param);
    }

    /**
     * 路由绑定模型实例
     * @access protected
     * @param array $bindModel 绑定模型
     * @param array $matches   路由变量
     * @return void
     */
    protected function createBindModel(array $bindModel, array $matches): void
    {
        foreach ($bindModel as $key => $val) {
            if ($val instanceof \Closure) {
                $result = $this->app->invokeFunction($val, $matches);
            } else {
                $fields = explode('&', $key);

                if (is_array($val)) {
                    list($model, $exception) = $val;
                } else {
                    $model     = $val;
                    $exception = true;
                }

                $where = [];
                $match = true;

                foreach ($fields as $field) {
                    if (!isset($matches[$field])) {
                        $match = false;
                        break;
                    } else {
                        $where[] = [$field, '=', $matches[$field]];
                    }
                }

                if ($match) {
                    $result = $model::where($where)->failException($exception)->find();
                }
            }

            if (!empty($result)) {
                // 注入容器
                $this->app->instance(get_class($result), $result);
            }
        }
    }

    /**
     * 验证数据
     * @access protected
     * @param array $option
     * @return void
     * @throws \think\exception\ValidateException
     */
    protected function autoValidate(array $option): void
    {
        list($validate, $scene, $message, $batch) = $option;

        if (is_array($validate)) {
            // 指定验证规则
            $v = new Validate();
            $v->rule($validate);
        } else {
            // 调用验证器
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);

            $v = new $class();

            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        /** @var Validate $v */
        $v->message($message)
            ->batch($batch)
            ->failException(true)
            ->check($this->request->param());
    }

    /**
     * 解析URL地址
     * @access protected
     * @param  string $url URL
     * @return array
     */
    protected function parseUrl(string $url): array
    {
        $path = $this->route->parseUrlPath($url);

        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z][\w|.]*$/', $controller)) {
            throw new ApiException(404, 'controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;
        $var    = [];

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        $panDomain = $this->request->panDomain();
        if ($panDomain && $key = array_search('*', $var)) {
            // 泛域名赋值
            $var[$key] = $panDomain;
        }

        // 设置当前请求的参数
        $this->param = array_merge($this->param, $var);

        // 封装路由
        return [$controller, $action];
    }

    public function getDispatch()
    {
        return $this->dispatch;
    }

    public function getParam(): array
    {
        return $this->param;
    }

    abstract public function exec();

    public function __sleep()
    {
        return ['route', 'dispatch', 'param', 'code', 'controller', 'actionName'];
    }

    public function __wakeup()
    {
        $this->app     = Container::pull('app');
        $this->request = $this->app->request;
    }
}
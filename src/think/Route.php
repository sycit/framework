<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Route.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use Closure;
use think\exception\InvalidArgumentException;
use think\middleware\CheckRequestCache;
use think\route\Dispatch;
use think\route\Domain;

/**
 * 路由管理类
 * Class Route
 * @package think
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
     */
    protected $url;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 路由绑定
     * @var array
     */
    protected $bind = [];

    /**
     * 路由参数
     * @var array
     */
    protected $append = [];

    public function __construct(App $app)
    {
        $this->app = $app;
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
        $this->url = $this->path();

        if ($withRoute) {
            $checkCallback = function () use ($withRoute) {
                //加载路由
                $withRoute();
                return $this->check();
            };

            $dispatch = $checkCallback();
        } else {
            $dispatch = $this->execDispatch($this->url);
        }

        $dispatch->init($this->app);

        return $this->app->middleware->pipeline()
            ->send($request)
            ->then(function () use ($dispatch) {
                return $dispatch->run();
            });
    }

    /**
     * 检测URL路由
     * @access public
     * @return Dispatch
     */
    public function check()
    {
        if (empty($this->bind)) {
            $result = false;
        } else {
            $result = $this->execDomain()->checkBind($this->request, $this->url);
        }

        if (false !== $result) {
            return $result;
        }

        return $this->execDispatch($this->url);
    }

    /**
     * 获取路由参数
     * @return array
     */
    public function append()
    {
        return $this->append;
    }

    /**
     * 设置路由参数
     * @param  array $append 参数
     * @return $this
     */
    public function setAppend(array $append = [])
    {
        $this->append = array_merge($this->append, $append);
        return $this;
    }

    /**
     * 设置路由缓存
     * @param  array|string $cache 缓存
     */
    public function cache($cache)
    {
        $this->app->middleware->import([CheckRequestCache::class, $cache]);
    }

    /**
     * 获取配置
     * @param string|null $name
     * @return mixed|null
     */
    public function config(string $name = null)
    {
        if (is_null($name)) {
            return $this->app->config->get($name);
        }
        return $this->app->config->get($name) ?? null;
    }

    /**
     * 设定绑定规则
     * @param array $bind
     * @return $this
     * @author Peter.Zhang
     */
    public function setBind(array $bind)
    {
        $this->bind = $bind;
        return $this;
    }

    /**
     * 获取绑定规则
     * @return array
     */
    public function bind()
    {
        return $this->bind;
    }

    /**
     * 获取控制器类的类名
     * @param string $name 控制器名
     * @return string
     */
    public function getNamespace(string $name)
    {
        return $this->app->parseClass('controller', $name);
    }

    /**
     * 默认路由调度
     * @access public
     * @param string|array $url   URL地址
     * @param array        $param 请求参数
     * @return Dispatch
     */
    protected function execDispatch($url, array $param = []): Dispatch
    {
        // 解析默认URL地址
        $dispatch = $this->parseDefaultUrl($url);
        return new Dispatch($this->request, $this, $dispatch, $param);
    }

    /**
     * 域名绑定调度
     * @return Domain
     */
    protected function execDomain(): Domain
    {
        return new Domain($this);
    }

    /**
     * 解析默认URL地址
     * @access protected
     * @param  string $url URL
     * @return array
     */
    protected function parseDefaultUrl($url): array
    {
        $path = $this->parseUrlPath($url);

        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z][\w|.]*$/', $controller)) {
            throw new InvalidArgumentException(5021, 'controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;
        $append = [];

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^|]+)/', function ($match) use (&$append) {
                $append[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        // 设置当前请求的参数
        $this->append = array_merge($this->append, $append);

        // 封装路由
        return [$controller, $action];
    }

    /**
     * 解析URL的pathinfo参数
     * @access public
     * @param  string $url URL地址
     * @return array
     */
    protected function parseUrlPath(string $url): array
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
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @return string
     */
    protected function path(): string
    {
        $depr = $this->config('app.pathinfo_depr');
        $type = $this->config('app.auto_response_type');
        $path = $this->request->pathinfo();
        $name = pathinfo($path, PATHINFO_EXTENSION);
        // 设置响应输出类型
        if (isset($type[$name])) {
            $this->app->setResponse($type[$name]);
        }
        // 去除URL后缀
        $url = preg_replace('/\.' . $name . '$/i', '', $path);
        $url = str_replace($depr, '|', $url);

        unset($type);
        unset($path);

        return $url;
    }
}
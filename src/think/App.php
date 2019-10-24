<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  App.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use think\helper\Str;
use think\initializer\BootService;
use think\initializer\Error;
use think\initializer\RegisterService;

/**
 * 基础类
 * Class App
 * @package think
 *
 * @property Annotation $annotation
 * @property Middleware $middleware
 * @property Validate   $validate
 * @property Session    $session
 * @property Request    $request
 * @property Console    $console
 * @property Config     $config
 * @property Cookie     $cookie
 * @property Route      $route
 * @property Event      $event
 * @property Cache      $cache
 * @property Http       $http
 * @property Lang       $lang
 * @property Log        $log
 * @property Env        $env
 * @property Db         $db
 */
class App extends Container
{
    const VERSION = '1.1.1';

    /**
     * 应用调试模式
     * @var bool
     */
    protected $appDebug = false;

    /**
     * 应用开始时间
     * @var float
     */
    protected $beginTime;

    /**
     * 应用内存初始占用
     * @var integer
     */
    protected $beginMem;

    /**
     * 当前应用类库命名空间
     * @var string
     */
    protected $namespace = 'app';

    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath = '';

    /**
     * 框架目录
     * @var string
     */
    protected $thinkPath = '';

    /**
     * 应用目录
     * @var string
     */
    protected $appPath = '';

    /**
     * Runtime目录
     * @var string
     */
    protected $runtimePath = '';

    /**
     * 配置后缀
     * @var string
     */
    protected $configExt = '.php';

    /**
     * 应用初始化器
     * @var array
     */
    protected $initializers = [
        Error::class,
        RegisterService::class,
        BootService::class,
    ];

    /**
     * 注册的系统服务
     * @var array
     */
    protected $services = [];

    /**
     * 初始化
     * @var bool
     */
    protected $initialized = false;

    /**
     * 响应输出类型 默认 Json
     * @var string
     */
    protected $response = '';

    /**
     * 容器绑定标识
     * @var array
     */
    protected $bind = [
        'db'                      => Db::class,
        'app'                     => App::class,
        'env'                     => Env::class,
        'log'                     => Log::class,
        'http'                    => Http::class,
        'cache'                   => Cache::class,
        'event'                   => Event::class,
        'route'                   => Route::class,
        'cookie'                  => Cookie::class,
        'config'                  => Config::class,
        'console'                 => Console::class,
        'request'                 => Request::class,
        'response'                => Response::class,
        'validate'                => Validate::class,
        'middleware'              => Middleware::class,
        'annotation'              => Annotation::class,

        'think\DbManager'         => Db::class,
        'think\LogManager'        => Log::class,
        'think\CacheManager'      => Cache::class,

        // 接口依赖注入
        'Psr\Log\LoggerInterface' => Log::class,
    ];

    /**
     * 架构方法
     * @access public
     * @param string $rootPath 应用根目录
     */
    public function __construct(string $rootPath = '')
    {
        $this->thinkPath   = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $this->rootPath    = $rootPath ? rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $this->getDefaultRootPath();
        $this->appPath     = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;

        if (is_file($this->appPath . 'provider.php')) {
            $this->bind(include $this->appPath . 'provider.php');
        }

        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance('think\Container', $this);
    }

    /**
     * 注册服务
     * @access public
     * @param Service|string $service 服务
     * @param bool           $force   强制重新注册
     * @return Service|null
     */
    public function register($service, bool $force = false)
    {
        $registered = $this->getService($service);

        if ($registered && !$force) {
            return $registered;
        }

        if (is_string($service)) {
            $service = new $service($this);
        }

        if (method_exists($service, 'register')) {
            $service->register();
        }

        if (property_exists($service, 'bind')) {
            $this->bind($service->bind);
        }

        $this->services[] = $service;
    }

    /**
     * 执行服务
     * @access public
     * @param Service $service 服务
     * @return mixed
     */
    public function bootService($service)
    {
        if (method_exists($service, 'boot')) {
            return $this->invoke([$service, 'boot']);
        }
    }

    /**
     * 获取服务
     * @param string|Service $service
     * @return Service|null
     */
    public function getService($service)
    {
        $name = is_string($service) ? $service : get_class($service);
        return array_values(array_filter($this->services, function ($value) use ($name) {
                return $value instanceof $name;
            }, ARRAY_FILTER_USE_BOTH))[0] ?? null;
    }

    /**
     * 设置响应格式
     * @param string $name
     * @return $this
     */
    public function setResponse(string $name)
    {
        $this->response = $name;
        return $this;
    }

    /**
     * 获取响应格式
     * @return string
     */
    public function response(): string
    {
        return $this->response;
    }

    /**
     * 开启应用调试模式
     * @access public
     * @param bool $debug 开启应用调试模式
     * @return $this
     */
    public function debug(bool $debug = true)
    {
        $this->appDebug = $debug;
        return $this;
    }

    /**
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->appDebug;
    }

    /**
     * 设置应用命名空间
     * @access public
     * @param string $namespace 应用命名空间
     * @return $this
     */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * 获取应用类库命名空间
     * @access public
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * 获取框架版本
     * @access public
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 获取应用基础目录
     * @access public
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取当前应用目录
     * @access public
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * 设置应用目录
     * @param $path
     */
    public function setAppPath($path)
    {
        $this->appPath = $path;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath(): string
    {
        return $this->runtimePath;
    }

    /**
     * 设置runtime目录
     * @param $path
     */
    public function setRuntimePath($path)
    {
        $this->runtimePath = $path;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getThinkPath(): string
    {
        return $this->thinkPath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取配置后缀
     * @access public
     * @return string
     */
    public function getConfigExt(): string
    {
        return $this->configExt;
    }

    /**
     * 获取应用开启时间
     * @access public
     * @return float
     */
    public function getBeginTime(): float
    {
        return $this->beginTime;
    }

    /**
     * 获取应用初始内存占用
     * @access public
     * @return integer
     */
    public function getBeginMem(): int
    {
        return $this->beginMem;
    }

    /**
     * 获取单个配置文件
     * @param string $name
     * @return array
     */
    public function getReadsConfig(string $name)
    {
        $file = $this->getConfigPath() . $name . $this->configExt;
        return $this->config->load($file, '', false);
    }

    /**
     * 初始化应用
     * @access public
     * @return $this
     */
    public function initialize()
    {
        $this->initialized = true;

        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();

        // 加载环境变量
        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }

        $this->configExt = $this->env->get('config_ext', '.php');

        // 应用调试模式
        if (!$this->appDebug) {
            $this->appDebug = $this->env->get('app_debug') ? true : false;
            ini_set('display_errors', 'Off');
        }

        // 加载全局初始化文件
        $this->load();

        // 监听AppInit
        $this->event->trigger('AppInit');

        date_default_timezone_set($this->config->get('app.default_timezone', 'Asia/Shanghai'));

        // 初始化
        foreach ($this->initializers as $initializer) {
            $this->make($initializer)->init($this);
        }

        return $this;
    }

    /**
     * 是否初始化过
     * @return bool
     */
    public function initialized()
    {
        return $this->initialized;
    }

    /**
     * 加载语言包
     * @param string $langset 语言
     * @return void
     */
    public function loadLangPack($langset)
    {
        if (empty($langset)) {
            return;
        }

        // 加载应用语言包
        // 格式：zh-cn.php/zh-cn.default.php
        $files = glob($this->appPath . 'lang' . DIRECTORY_SEPARATOR . $langset . '.*');
        $this->lang->load($files);

        // 加载扩展（自定义）语言包
        $list = $this->lang->config('extend_list', []);
        if (isset($list[$langset])) {
            $this->lang->load($list[$langset]);
        }
    }

    /**
     * 引导应用
     * @access public
     * @return void
     */
    public function boot(): void
    {
        array_walk($this->services, function ($service) {
            $this->bootService($service);
        });
    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function load(): void
    {
        $appPath = $this->getAppPath();

        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }

        include_once $this->thinkPath . 'helper.php';

        $configPath = $this->getConfigPath();

        // 加载初始化配置
        if (is_dir($configPath)) {
            $files = [
                $configPath . 'app' . $this->configExt,
                $configPath . 'database' . $this->configExt,
            ];

            foreach ($files as $file) {
                $this->config->load($file, pathinfo($file, PATHINFO_FILENAME), true);
            }
        }

        if (is_file($appPath . 'event.php')) {
            $this->loadEvent(include $appPath . 'event.php');
        }

        if (is_file($appPath . 'service.php')) {
            $services = include $appPath . 'service.php';
            foreach ($services as $service) {
                $this->register($service);
            }
        }
    }

    /**
     * 注册应用事件
     * @access protected
     * @param array $event 事件数据
     * @return void
     */
    public function loadEvent(array $event): void
    {
        if (isset($event['bind'])) {
            $this->event->bind($event['bind']);
        }

        if (isset($event['listen'])) {
            $this->event->listenEvents($event['listen']);
        }

        if (isset($event['subscribe'])) {
            $this->event->subscribe($event['subscribe']);
        }
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param string $layer 层名 controller model ...
     * @param string $name  类名
     * @return string
     */
    public function parseClass(string $layer, string $name): string
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = Str::studly(array_pop($array));
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . $layer . '\\' . $path . $class;
    }

    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        return dirname($this->thinkPath) . DIRECTORY_SEPARATOR;
    }
}
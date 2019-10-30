<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Request.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use think\exception\ApiException;

/**
 * 请求管理类
 * Class Request
 * @package think
 */
class Request
{
    /**
     * 当前应用名
     * @var string
     */
    protected $app;

    /**
     * 当前控制器名
     * @var string
     */
    protected $controller;

    /**
     * 当前操作名
     * @var string
     */
    protected $action;

    /**
     * 原始参数
     * @var array
     */
    protected $data = [];

    /**
     * 已处理参数
     * @var array
     */
    protected $param = [];

    /**
     * 当前GET参数
     * @var array
     */
    protected $get = [];

    /**
     * 当前POST参数
     * @var array
     */
    protected $post = [];

    /**
     * 当前REQUEST参数
     * @var array
     */
    protected $request = [];

    /**
     * 当前HEADER参数
     * @var array
     */
    protected $header = [];

    /**
     * 请求类型
     * @var string
     */
    protected $method;

    /**
     * 子域名
     * @var string
     */
    protected $subDomain;

    /**
     * 当前执行的文件
     * @var string
     */
    protected $baseFile;

    /**
     * 访问的ROOT地址
     * @var string
     */
    protected $root;

    /**
     * 域名根
     * @var string
     */
    protected $rootDomain = '';

    /**
     * pathinfo
     * @var string
     */
    protected $pathinfo;

    /**
     * 中间件传递的参数
     * @var array
     */
    protected $middleware = [];

    /**
     * 当前PUT参数
     * @var array
     */
    protected $put;

    /**
     * SESSION对象
     * @var Session
     */
    protected $session;

    /**
     * COOKIE数据
     * @var array
     */
    protected $cookie = [];

    /**
     * php://input内容
     * @var string
     */
    // php://input
    protected $input;

    /**
     * 当前请求的IP地址
     * @var string
     */
    protected $realIP;

    /**
     * 是否合并Param
     * @var bool
     */
    protected $mergeParam = false;

    /**
     * 前端代理服务器IP
     * @var array
     */
    protected $proxyServerIp = [];

    /**
     * 前端代理服务器真实IP头
     * @var array
     */
    protected $proxyServerIpHeader = ['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'];

    /**
     * PATHINFO变量名 用于兼容模式
     * @var string
     */
    protected $varPathinfo = 's';

    /**
     * 兼容PATH_INFO获取
     * @var array
     */
    protected $pathinfoFetch = ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'];

    /**
     * 架构函数
     */
    public function __construct()
    {
        // 保存 php://input
        $this->input = file_get_contents('php://input');
    }

    public static function __make(App $app)
    {
        $request = new static();

        $request->get     = $_GET;
        $request->post    = $_POST ?: $request->getInputData($request->input);
        $request->put     = $request->getInputData($request->input);
        $request->request = $_REQUEST;
        $request->cookie  = $_COOKIE;

        if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
            $header = $result;
        } else {
            $header = [];
            $server = $_SERVER;
            foreach ($server as $key => $val) {
                if (0 === strpos($key, 'HTTP_')) {
                    $key          = str_replace('_', '-', strtolower(substr($key, 5)));
                    $header[$key] = $val;
                }
            }
            if (isset($server['CONTENT_TYPE'])) {
                $header['content-type'] = $server['CONTENT_TYPE'];
            }
            if (isset($server['CONTENT_LENGTH'])) {
                $header['content-length'] = $server['CONTENT_LENGTH'];
            }
            unset($server);
        }

        $request->header = array_change_key_case($header);

        return $request;
    }

    /**
     * 获取当前包含协议的域名
     * @param  bool $port 是否需要去除端口号
     * @return string
     */
    public function domain(bool $port = false): string
    {
        return $this->scheme() . '://' . $this->host($port);
    }

    /**
     * 获取当前根域名
     * @return string
     */
    public function rootDomain(): string
    {
        $root = $this->rootDomain;

        if (!$root) {
            $item  = explode('.', $this->host());
            $count = count($item);
            $root  = $count > 1 ? $item[$count - 2] . '.' . $item[$count - 1] : $item[0];
        }

        return $root;
    }

    /**
     * 获取当前子域名
     * @return string
     */
    public function subDomain(): string
    {
        if (is_null($this->subDomain)) {
            // 获取当前主域名
            $rootDomain = $this->rootDomain();

            if ($rootDomain) {
                $this->subDomain = rtrim(stristr($this->host(), $rootDomain, true), '.');
            } else {
                $this->subDomain = '';
            }
        }

        return $this->subDomain;
    }

    /**
     * 获取当前完整URL 包括QUERY_STRING
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function url(bool $complete = false): string
    {
        if ($this->server('HTTP_X_REWRITE_URL')) {
            $url = $this->server('HTTP_X_REWRITE_URL');
        } elseif ($this->server('REQUEST_URI')) {
            $url = $this->server('REQUEST_URI');
        } elseif ($this->server('ORIG_PATH_INFO')) {
            $url = $this->server('ORIG_PATH_INFO') . (!empty($this->server('QUERY_STRING')) ? '?' . $this->server('QUERY_STRING') : '');
        } elseif (isset($_SERVER['argv'][1])) {
            $url = $_SERVER['argv'][1];
        } else {
            $url = '';
        }

        return $complete ? $this->domain() . $url : $url;
    }

    /**
     * 获取当前执行的文件 SCRIPT_NAME
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function baseFile(bool $complete = false): string
    {
        if (!$this->baseFile) {
            $url = '';
            if (!PHP_SAPI == 'cli') {
                $script_name = basename($this->server('SCRIPT_FILENAME'));
                if (basename($this->server('SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('SCRIPT_NAME');
                } elseif (basename($this->server('PHP_SELF')) === $script_name) {
                    $url = $this->server('PHP_SELF');
                } elseif (basename($this->server('ORIG_SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('ORIG_SCRIPT_NAME');
                } elseif (($pos = strpos($this->server('PHP_SELF'), '/' . $script_name)) !== false) {
                    $url = substr($this->server('SCRIPT_NAME'), 0, $pos) . '/' . $script_name;
                } elseif ($this->server('DOCUMENT_ROOT') && strpos($this->server('SCRIPT_FILENAME'), $this->server('DOCUMENT_ROOT')) === 0) {
                    $url = str_replace('\\', '/', str_replace($this->server('DOCUMENT_ROOT'), '', $this->server('SCRIPT_FILENAME')));
                }
            }
            $this->baseFile = $url;
        }

        return $complete ? $this->domain() . $this->baseFile : $this->baseFile;
    }

    /**
     * 设置URL访问根地址
     * @param  string $url URL地址
     * @return $this
     */
    public function setRoot(string $url)
    {
        $this->root = $url;
        return $this;
    }

    /**
     * 获取URL访问根地址
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function root(bool $complete = false): string
    {
        if (!$this->root) {
            $file = $this->baseFile();
            if ($file && 0 !== strpos($this->url(), $file)) {
                $file = str_replace('\\', '/', dirname($file));
            }
            $this->root = rtrim($file, '/');
        }

        return $complete ? $this->domain() . $this->root : $this->root;
    }

    /**
     * 获取URL访问根目录
     * @return string
     */
    public function rootUrl(): string
    {
        $base = $this->root();
        $root = strpos($base, '.') ? ltrim(dirname($base), DIRECTORY_SEPARATOR) : $base;

        if ('' != $root) {
            $root = '/' . ltrim($root, '/');
        }

        return $root;
    }

    /**
     * 设置当前请求的pathinfo
     * @param  string $pathinfo
     * @return $this
     */
    public function setPathinfo(string $pathinfo)
    {
        $this->pathinfo = $pathinfo;
        return $this;
    }

    /**
     * 获取当前请求URL的pathinfo信息（含URL后缀）
     * @return string
     */
    public function pathinfo(): string
    {
        if (is_null($this->pathinfo)) {
            if (isset($_GET[$this->varPathinfo])) {
                // 判断URL里面是否有兼容模式参数
                $pathinfo = $_GET[$this->varPathinfo];
                unset($_GET[$this->varPathinfo]);
                unset($this->get[$this->varPathinfo]);
            } elseif ($this->server('PATH_INFO')) {
                $pathinfo = $this->server('PATH_INFO');
            } elseif (false !== strpos(PHP_SAPI, 'cli')) {
                $pathinfo = strpos($this->server('REQUEST_URI'), '?') ? strstr($this->server('REQUEST_URI'), '?', true) : $this->server('REQUEST_URI');
            }

            // 分析PATHINFO信息
            if (!isset($pathinfo)) {
                foreach ($this->pathinfoFetch as $type) {
                    if ($this->server($type)) {
                        $pathinfo = (0 === strpos($this->server($type), $this->server('SCRIPT_NAME'))) ?
                            substr($this->server($type), strlen($this->server('SCRIPT_NAME'))) : $this->server($type);
                        break;
                    }
                }
            }

            if (!empty($pathinfo)) {
                unset($this->get[$pathinfo], $this->request[$pathinfo]);
            }

            $this->pathinfo = empty($pathinfo) || '/' == $pathinfo ? '' : ltrim($pathinfo, '/');
        }

        return $this->pathinfo;
    }

    /**
     * 设置当前的应用名
     * @param  string $app 应用名
     * @return $this
     */
    public function setApp(string $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * 设置当前的控制器名
     * @param  string $controller 控制器名
     * @return $this
     */
    public function setController(string $controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * 设置当前的操作名
     * @param  string $action 操作名
     * @return $this
     */
    public function setAction(string $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * 获取当前的应用名
     * @return string
     */
    public function app(): string
    {
        return $this->app ?: '';
    }

    /**
     * 获取当前的控制器名
     * @param  bool $convert 转换为小写
     * @return string
     */
    public function controller(bool $convert = false): string
    {
        $name = $this->controller ?: '';
        return $convert ? strtolower($name) : $name;
    }

    /**
     * 获取当前的操作名
     * @param  bool $convert 转换为小写
     * @return string
     */
    public function action(bool $convert = false): string
    {
        $name = $this->action ?: '';
        return $convert ? strtolower($name) : $name;
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @return string
     */
    public function contentType(): string
    {
        $contentType = $this->server('CONTENT_TYPE');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
    }

    /**
     * 获取中间件传递的参数
     * @param  mixed $name 变量名
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function middleware($name, $default = null)
    {
        return $this->middleware[$name] ?? $default;
    }

    /**
     * 获取request变量
     * @param  mixed      $name    数据名称
     * @param  array|bool $default 默认值
     * @return mixed
     */
    public function request($name = '', $default = false)
    {
        if (is_array($name)) {
            return $this->intersectKey($name, $this->request, $default);
        }

        return $this->request[$name] ?? $this->handleParam($name, $default);
    }

    /**
     * 获取指定的参数
     * @param  array        $name 变量名
     * @param  mixed        $data 数据或者变量类型
     * @param  array|bool   $default 默认值
     * @param  mixed        $filter 过滤方法
     * @return array
     */
    public function only(array $name, $data = 'param', $default = false, $filter = ''): array
    {
        $data = is_array($data) ? $data : $this->$data;

        $data = $this->intersectKey($name, $data, $default);

        !empty($filter) && $data = $this->filterData($data, $filter, $name);

        return $data;
    }

    /**
     * 设置接口参数
     * @param  array $param 路由变量
     * @return $this
     */
    public function set(array $param)
    {
        $this->param = array_merge($this->param, $param);
        return $this;
    }

    /**
     * 获取接口参数
     * @param  string|array  $name    变量名
     * @param  array|bool    $default 默认值
     * @return mixed
     */
    public function get($name = '', $default = false)
    {
        if ('' === $name) {
            return $this->param;
        }

        $data = [];
        foreach ((array)$name as $val) {
            // param空值会获取原始数据
            $value = $this->param[$val] ?? $this->data($val);
            $data[$val] = !is_null($value) ? $value : $this->handleParam($val, is_array($default) ? $default[$val] : $default);;
        }

        return is_array($name) ? $data : $data[$name];
    }

    /**
     * 获取原始数据
     * @param  string|array $name 字段名
     * @return mixed
     */
    public function data($name = '')
    {
        if (!$this->mergeParam) {
            $method = $this->method(true);

            // 自动获取请求变量
            switch ($method) {
                case 'POST':
                    $vars = $this->post;
                    break;
                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                    $vars = $this->put;
                    break;
                default:
                    $vars = [];
            }

            // 当前请求参数和URL地址中的参数合并
            $this->data = array_merge($this->get, $vars);

            $this->mergeParam = true;
        }

        if (is_array($name)) {
            return array_intersect_key($this->data, array_flip((array) $name));
        }

        return '' === $name ? $this->data : ($this->data[$name] ?? null);
    }

    /**
     * 设置或者获取当前的Header
     * @param  string       $name header名称
     * @param  array|bool   $default 默认值
     * @return string|array
     */
    public function header(string $name = '', $default = false)
    {
        if ('' === $name) {
            return $this->header;
        }

        $name = str_replace('_', '-', strtolower($name));

        return $this->header[$name] ?? $this->handleParam($name, $default);
    }

    /**
     * 当前URL地址中的scheme参数
     * @return string
     */
    public function scheme(): string
    {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * 当前请求的host
     * @param bool $strict  true 仅仅获取HOST
     * @return string
     */
    public function host(bool $strict = false): string
    {
        $host = strval($this->server('HTTP_X_REAL_HOST') ?: $this->server('HTTP_HOST'));

        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }

    /**
     * 获取session数据
     * @param  string $name 数据名称
     * @param  string $default 默认值
     * @return mixed
     */
    public function session(string $name = '', $default = null)
    {
        if ('' === $name) {
            return $this->session->all();
        }
        return $this->session->get($name, $default);
    }

    /**
     * 获取cookie参数
     * @param  string       $name 数据名称
     * @param  string       $default 默认值
     * @param  string|array $filter 过滤方法
     * @return mixed
     */
    public function cookie(string $name = '', $default = null, $filter = '')
    {
        if ('' === $name) {
            $data = $this->cookie;
        } else {
            $data = $this->getSpecifyData($this->cookie, $name, $default);
            $data = $this->filterData((array) $data, $filter, $name);
        }

        return $data;
    }

    /**
     * 过滤给定的值
     * @param  mixed  $value   键值
     * @param  string $key     键名
     * @param  array  $filters 过滤方法
     * @return mixed
     */
    public function filterValue(&$value, string $key, array $filters)
    {
        if (!isset($filters[$key]) || '' == $filters[$key]) {
            return $value;
        }

        $filter = $filters[$key];

        unset($filters);

        if (is_callable($filter)) {
            // 调用函数或者方法过滤
            $value = call_user_func($filter, $value);
        } elseif (is_scalar($value)) {
            $value = app()->validate->is($value, $filter) ? $value : false;
        }

        return $value;
    }

    /**
     * 获取server参数
     * @param  string $name 数据名称
     * @param  string $default 默认值
     * @return mixed
     */
    public function server(string $name = '', string $default = '')
    {
        if (empty($name)) {
            return $_SERVER;
        } else {
            $name = strtoupper($name);
        }

        return $_SERVER[$name] ?? $default;
    }

    /**
     * 设置请求类型
     * @param  string $method 请求类型
     * @return $this
     */
    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * 当前的请求类型
     * @param  bool $origin 是否获取原始请求类型
     * @return string
     */
    public function method(bool $origin = false): string
    {
        if ($origin) {
            // 获取原始请求类型
            return $this->server('REQUEST_METHOD') ?: 'GET';
        } elseif (!$this->method) {
            if ($this->server('HTTP_X_HTTP_METHOD_OVERRIDE')) {
                $this->method = strtoupper($this->server('HTTP_X_HTTP_METHOD_OVERRIDE'));
            } else {
                $this->method = $this->server('REQUEST_METHOD') ?: 'GET';
            }
        }

        return $this->method;
    }

    /**
     * 设置在中间件传递的数据
     * @param  array $middleware 数据
     * @return $this
     */
    public function withMiddleware(array $middleware)
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * 设置COOKIE数据
     * @param array $cookie 数据
     * @return $this
     */
    public function withCookie(array $cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置SESSION数据
     * @param Session $session 数据
     * @return $this
     */
    public function withSession(Session $session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * 当前是否ssl
     * @return bool
     */
    public function isSsl(): bool
    {
        if ($this->server('HTTPS') && ('1' == $this->server('HTTPS') || 'on' == strtolower($this->server('HTTPS')))) {
            return true;
        } elseif ('https' == $this->server('REQUEST_SCHEME')) {
            return true;
        } elseif ('443' == $this->server('SERVER_PORT')) {
            return true;
        } elseif ('https' == $this->server('HTTP_X_FORWARDED_PROTO')) {
            return true;
        }

        return false;
    }

    /**
     * 获取客户端IP地址
     * @return string
     */
    public function ip(): string
    {
        if (!empty($this->realIP)) {
            return $this->realIP;
        }

        $this->realIP = $this->server('REMOTE_ADDR', '');

        // 如果指定了前端代理服务器IP以及其会发送的IP头
        // 则尝试获取前端代理服务器发送过来的真实IP
        $proxyIp       = $this->proxyServerIp;
        $proxyIpHeader = $this->proxyServerIpHeader;

        if (count($proxyIp) > 0 && count($proxyIpHeader) > 0) {
            // 从指定的HTTP头中依次尝试获取IP地址
            // 直到获取到一个合法的IP地址
            foreach ($proxyIpHeader as $header) {
                $tempIP = $this->server($header);

                if (empty($tempIP)) {
                    continue;
                }

                $tempIP = trim(explode(',', $tempIP)[0]);

                if (!$this->isValidIP($tempIP)) {
                    $tempIP = null;
                } else {
                    break;
                }
            }

            // tempIP不为空，说明获取到了一个IP地址
            // 这时我们检查 REMOTE_ADDR 是不是指定的前端代理服务器之一
            // 如果是的话说明该 IP头 是由前端代理服务器设置的
            // 否则则是伪装的
            if (!empty($tempIP)) {
                $realIPBin = $this->ip2bin($this->realIP);

                foreach ($proxyIp as $ip) {
                    $serverIPElements = explode('/', $ip);
                    $serverIP         = $serverIPElements[0];
                    $serverIPPrefix   = $serverIPElements[1] ?? 128;
                    $serverIPBin      = $this->ip2bin($serverIP);

                    // IP类型不符
                    if (strlen($realIPBin) !== strlen($serverIPBin)) {
                        continue;
                    }

                    if (strncmp($realIPBin, $serverIPBin, (int) $serverIPPrefix) === 0) {
                        $this->realIP = $tempIP;
                        break;
                    }
                }
            }
        }

        if (!$this->isValidIP($this->realIP)) {
            $this->realIP = '0.0.0.0';
        }

        return $this->realIP;
    }

    /**
     * 检测是否是合法的IP地址
     *
     * @param string $ip   IP地址
     * @param string $type IP地址类型 (ipv4, ipv6)
     *
     * @return boolean
     */
    public function isValidIP(string $ip, string $type = ''): bool
    {
        switch (strtolower($type)) {
            case 'ipv4':
                $flag = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $flag = FILTER_FLAG_IPV6;
                break;
            default:
                $flag = null;
                break;
        }

        return boolval(filter_var($ip, FILTER_VALIDATE_IP, $flag));
    }

    /**
     * 将IP地址转换为二进制字符串
     *
     * @param string $ip
     *
     * @return string
     */
    public function ip2bin(string $ip): string
    {
        if ($this->isValidIP($ip, 'ipv6')) {
            $IPHex = str_split(bin2hex(inet_pton($ip)), 4);
            foreach ($IPHex as $key => $value) {
                $IPHex[$key] = intval($value, 16);
            }
            $IPBin = vsprintf('%016b%016b%016b%016b%016b%016b%016b%016b', $IPHex);
        } else {
            $IPHex = str_split(bin2hex(inet_pton($ip)), 2);
            foreach ($IPHex as $key => $value) {
                $IPHex[$key] = intval($value, 16);
            }
            $IPBin = vsprintf('%08b%08b%08b%08b', $IPHex);
        }

        return $IPBin;
    }

    /**
     * 获取当前请求的时间
     * @param  bool $float 是否使用浮点类型
     * @return integer|float
     */
    public function time(bool $float = false)
    {
        return $float ? $this->server('REQUEST_TIME_FLOAT') : $this->server('REQUEST_TIME');
    }

    /**
     * 获取当前请求的php://input
     * @return string
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * 设置中间传递数据
     * @param  string    $name  参数名
     * @param  mixed     $value 值
     */
    public function __set(string $name, $value)
    {
        $this->middleware[$name] = $value;
    }

    /**
     * 获取中间传递数据的值
     * @param  string $name 名称
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->middleware($name);
    }

    /**
     * 检测请求数据的值
     * @param  string $name 名称
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        return isset($this->param[$name]);
    }

    /**
     * 解析参数定义
     * @param array $name   一维数组
     * @param mixed $value  字符串|数组|闭包|bool
     * @return array
     */
    protected function getFillData(array $name, $value): array
    {
        $data = [];
        if (is_array($value)) {
            foreach ($name as $item) {
                if (isset($value[$item])) {
                    $data[$item] = $value[$item];
                }
            }
        } else {
            $data = array_fill_keys($name, $value);
        }
        return $data;
    }

    protected function filterData(array $data, $filter, $name)
    {
        if (is_string($name)) {
            $name = explode(',', $name);
        }

        // 解析参数定义
        $filter = $this->getFillData($name, $filter);

        array_walk_recursive($data, [$this, 'filterValue'], $filter);
        reset($data);

        array_map(function ($item, $value) {
            return $this->handleParam($item, $value);
        }, $name, $data);

        return $data;
    }

    /**
     * 比较数据
     * @param array      $name    变量
     * @param array      $data    数据
     * @param array|bool $default 默认值
     * @return array
     */
    protected function intersectKey(array $name, array $data, $default): array
    {
        $item = [];
        foreach ($name as $key => $val) {
            if (is_int($key)) {
                $key = $val;
            }

            $item[$key] = $data[$key] ?? $this->handleParam($key, is_array($default) ? $default[$key] : $default);
        }

        return $item;
    }

    /**
     * 参数处理
     * @param $name
     * @param $default
     * @return mixed
     */
    protected function handleParam($name, $default)
    {
        if (false === $default) {
            throw new ApiException(4003, 'invalid argument:' . $name);
        }

        return $default;
    }

    /**
     * 获取指定数据
     * @param array $data   数据源
     * @param string $name  字段名
     * @param null $default 默认值
     * @return array|mixed|null
     */
    protected function getSpecifyData(array $data, string $name, $default = null)
    {
        $array = explode('.', $name);
        foreach ($array as $val) {
            if (isset($data[$val])) {
                $data = $data[$val];
            } else {
                return $default;
            }
        }

        return $data;
    }

    protected function getInputData($content): array
    {
        if (false !== strpos($this->contentType(), 'json')) {
            return (array) json_decode($content, true);
        } elseif (strpos($content, '=')) {
            parse_str($content, $data);
            return $data;
        }

        return [];
    }
}
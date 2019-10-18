<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Cookie.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use DateTimeInterface;

/**
 * Cookie 管理类
 * Class Cookie
 * @package think
 */
class Cookie
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => false,
        // 是否使用 setcookie
        'setcookie' => true,
    ];

    /**
     * Cookie写入数据
     * @var array
     */
    protected $cookie = [];

    /**
     * 当前Request对象
     * @var Request
     */
    protected $request;

    /**
     * 构造方法
     */
    public function __construct(Request $request, array $config = [])
    {
        $this->request = $request;
        $this->config  = array_merge($this->config, array_change_key_case($config));
    }

    public static function __make(Request $request, App $app)
    {
        return new static($request, $app->getReadsConfig('cookie'));
    }

    /**
     * 读取配置
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function config(string $name = '', $default = null)
    {
        if ('' == $name) {
            return $this->config;
        }

        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        return $default;
    }

    /**
     * 获取cookie
     * @access public
     * @param  mixed  $name 数据名称
     * @param  string $default 默认值
     * @return mixed
     */
    public function get(string $name = '', $default = null)
    {
        return $this->request->cookie($name, $default);
    }

    /**
     * 是否存在Cookie参数
     * @access public
     * @param  string $name 变量名
     * @return bool
     */
    public function has(string $name): bool
    {
        $cookie = $this->request->cookie($name);
        return empty($cookie) ? false : true;
    }

    /**
     * Cookie 设置
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  mixed  $option 可选参数
     * @return void
     */
    public function set(string $name, string $value, $option = null): void
    {
        // 参数设置(会覆盖黙认设置)
        if (!is_null($option)) {
            if (is_numeric($option) || $option instanceof DateTimeInterface) {
                $option = ['expire' => $option];
            }

            $config = array_merge($this->config, array_change_key_case($option));
        } else {
            $config = $this->config;
        }

        if ($config['expire'] instanceof DateTimeInterface) {
            $expire = $config['expire']->getTimestamp();
        } else {
            $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
        }

        $this->setCookie($name, $value, $expire, $config);
    }

    /**
     * Cookie 保存
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  int    $expire 有效期
     * @param  array  $option 可选参数
     * @return void
     */
    protected function setCookie(string $name, string $value, int $expire, array $option = []): void
    {
        $this->cookie[$name] = [$value, $expire, $option];
    }

    /**
     * 永久保存Cookie数据
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  mixed  $option 可选参数 可能会是 null|integer|string
     * @return void
     */
    public function forever(string $name, string $value = '', $option = null): void
    {
        if (is_null($option) || is_numeric($option)) {
            $option = [];
        }

        $option['expire'] = 315360000;

        $this->set($name, $value, $option);
    }

    /**
     * Cookie删除
     * @access public
     * @param  string $name cookie名称
     * @return void
     */
    public function delete(string $name): void
    {
        $this->setCookie($name, '', time() - 3600, $this->config);
    }

    /**
     * 获取cookie保存数据
     * @access public
     * @return array
     */
    public function getCookie(): array
    {
        return $this->cookie;
    }

    /**
     * 保存Cookie
     * @access public
     * @return void
     */
    public function save(): void
    {
        foreach ($this->cookie as $name => $val) {
            list($value, $expire, $option) = $val;

            $this->saveCookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }
    }

    /**
     * 保存Cookie
     * @access public
     * @param  string $name cookie名称
     * @param  string $value cookie值
     * @param  int    $expire cookie过期时间
     * @param  string $path 有效的服务器路径
     * @param  string $domain 有效域名/子域名
     * @param  bool   $secure 是否仅仅通过HTTPS
     * @param  bool   $httponly 仅可通过HTTP访问
     * @return void
     */
    protected function saveCookie(string $name, string $value, int $expire, string $path, string $domain, bool $secure, bool $httponly): void
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}
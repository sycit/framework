<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Response.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

/**
 * 响应输出基础类
 * Class Response
 * @package think
 */
class Response
{
    /**
     * 输出数据
     * @var mixed
     */
    protected $data;

    /**
     * 当前contentType
     * @var string
     */
    protected $contentType = 'application/json';

    /**
     * 字符集
     * @var string
     */
    protected $charset = 'utf-8';

    /**
     * HTTP编码
     * @var integer
     */
    protected $code = 200;

    /**
     * Api编码
     * @var integer
     */
    protected $status = 0;

    /**
     * 提示信息
     * @var string
     */
    protected $message = '';

    /**
     * 输出参数
     * @var array
     */
    protected $options = ['json_encode_param' => JSON_UNESCAPED_UNICODE];

    /**
     * header参数
     * @var array
     */
    protected $header = [];

    /**
     * 调试数据
     * @var mixed
     */
    protected $debug;

    /**
     * Cookie对象
     * @var Cookie
     */
    protected $cookie;

    /**
     * Session对象
     * @var Session
     */
    protected $session;

    /**
     * 架构函数
     * @access public
     * @param  mixed $data    输出数据
     * @param  int   $code
     */
    public function __construct($data = '', int $code = 200)
    {
        $this->data($data);
        $this->code = $code;

        $this->contentType($this->contentType, $this->charset);
    }

    /**
     * 创建Response对象
     * @param  mixed  $data    输出数据
     * @param  string $type    输出类型
     * @param  int    $code
     * @return Response
     */
    public static function create($data = '', string $type = '', int $code = 200): Response
    {
        if (class_exists($type)) {
            return Container::getInstance()->invokeClass($type, [$data, $code]);
        }

        return new static($data, $code);
    }

    /**
     * 设置Cookie对象
     * @param  Cookie $cookie Cookie对象
     * @return $this
     */
    public function setCookie(Cookie $cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置Session对象
     * @param  Session $session Session对象
     * @return $this
     */
    public function setSession(Session $session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * 设置响应头
     * @param  array $header  参数
     * @return $this
     */
    public function header(array $header = [])
    {
        $this->header = array_merge($this->header, $header);
        return $this;
    }

    /**
     * 设置HTTP编码
     * @param  integer $code 编码
     * @return $this
     */
    public function code(int $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * 设置API编码
     * @param int $status
     * @return $this
     */
    public function status(int $status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * 提示信息
     * @param string $message
     * @return $this
     */
    public function message(string $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * 输出数据设置
     * @param  mixed $data 输出数据
     * @return $this
     */
    public function data($data)
    {
        if (!empty($data)) {
            $this->data = $data;
        }

        return $this;
    }

    /**
     * 设置调试信息
     * @access public
     * @param  mixed $debug
     * @return $this
     */
    public function debug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 页面输出类型
     * @param  string $contentType 输出类型
     * @param  string $charset     输出编码
     * @return $this
     */
    public function contentType(string $contentType, string $charset = 'utf-8')
    {
        $this->header['Content-Type'] = $contentType . '; charset=' . $charset;

        return $this;
    }

    /**
     * 发送数据到客户端
     * @return void
     * @throws \InvalidArgumentException
     */
    public function send(): void
    {
        // 处理输出数据
        $data = $this->getOutput();

        if (!headers_sent() && !empty($this->header)) {
            // 发送状态码
            http_response_code($this->code);
            // 发送头部信息
            foreach ($this->header as $name => $val) {
                header($name . (!is_null($val) ? ':' . $val : ''));
            }
        }

        $this->cookie->save();

        $this->sendData($data);

        if (function_exists('fastcgi_finish_request')) {
            // 提高页面响应
            fastcgi_finish_request();
        }
    }

    /**
     * 处理数据
     * @param  mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        $result['status']  = $this->status;

        $this->message && $result['message'] = $this->getMessageLang($this->message);
        if ($this->code === 200) {
            $result['data'] = is_array($data) && empty($data) ? (object)$data : $data;
        }

        if (!empty($this->debug)) {
            $result['debug'] = $this->debug;
        }

        return json_encode((object)$result, $this->options['json_encode_param']);
    }

    /**
     * 获取处理数据
     * @return string
     */
    protected function getOutput(): string
    {
        $data = $this->output($this->data);

        if (null !== $data && !is_string($data) && !is_numeric($data) && !is_callable([
                $data,
                '__toString',
            ])
        ) {
            throw new \InvalidArgumentException(sprintf('variable type error： %s', gettype($data)));
        }

        return (string) $data;
    }

    /**
     * 输出数据
     * @param string $data 要处理的数据
     * @return void
     */
    protected function sendData(string $data): void
    {
        echo $data;
    }

    /**
     * 错误信息多语言化
     * @param string $message 错误信息
     * @return string
     */
    protected function getMessageLang($message): string
    {
        $app = $this->getApp();

        $message = (string)$message;

        if ($app->runningInConsole()) {
            return $message;
        }

        if ($app->has('lang')) {
            $lang = $app->lang;

            if (strpos($message, ':')) {
                $name    = strstr($message, ':', true);
                $message = $lang->has($name) ? $lang->get($name) . strstr($message, ':') : $message;
            } elseif (strpos($message, ',')) {
                $name    = strstr($message, ',', true);
                $message = $lang->has($name) ? $lang->get($name) . ':' . substr(strstr($message, ','), 1) : $message;
            } elseif ($lang->has($message)) {
                $message = $lang->get($message);
            }
        }

        return $message;
    }

    /**
     * Get an instance of the app.
     *
     * @return App
     */
    protected function getApp()
    {
        return Container::getInstance()->make('app');
    }
}
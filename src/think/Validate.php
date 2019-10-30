<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Validate.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use think\helper\Str;

/**
 * 数据验证类
 * Class Validate
 * @package think
 */
class Validate
{
    /**
     * @var App
     */
    protected $app;

    /**
     * 自定义验证类型
     * @var array
     */
    protected $type = [];

    /**
     * 验证正则定义
     * @var array
     */
    protected $regex = [];

    /**
     * 内置正则验证规则
     * @var array
     */
    protected $defaultRegex = [
        'alpha'       => '/^[A-Za-z]+$/',
        'alphaNum'    => '/^[A-Za-z0-9]+$/',
        'alphaDash'   => '/^[A-Za-z0-9\-\_]+$/',
        'chs'         => '/^[\x{4e00}-\x{9fa5}]+$/u',
        'chsAlpha'    => '/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u',
        'chsAlphaNum' => '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u',
        'chsDash'     => '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9\_\-]+$/u',
        'mobile'      => '/^1[3-9][0-9]\d{8}$/',
        'idCard'      => '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{2}$)/',
        'zip'         => '/\d{6}/',
    ];

    /**
     * Filter_var 规则
     * @var array
     */
    protected $filter = [
        'email'   => FILTER_VALIDATE_EMAIL,
        'ip'      => [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6],
        'integer' => FILTER_VALIDATE_INT,
        'url'     => FILTER_VALIDATE_URL,
        'macAddr' => FILTER_VALIDATE_MAC,
        'float'   => FILTER_VALIDATE_FLOAT,
    ];

    /**
     * 构造方法
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 设置正则定义
     * @param string $key
     * @param string $regex
     * @return $this
     */
    public function setRegex(string $key, string $regex)
    {
        $this->regex[$key] = $regex;
        return $this;
    }

    /**
     * 注册验证（类型）规则
     * @access public
     * @param string   $type     验证规则类型
     * @param callable $callback callback方法(或闭包)
     * @return $this
     */
    public function extend(string $type, callable $callback = null)
    {
        $this->type[$type] = $callback;
        return $this;
    }

    /**
     * 验证字段值是否为有效格式
     * @param mixed  $value 字段值
     * @param string $rule  验证规则
     * @return bool
     */
    public function is($value, string $rule): bool
    {
        switch (Str::camel($rule)) {
            case 'require':
                // 必须
                $result = !empty($value) || '0' == $value;
                break;
            case 'accepted':
                // 接受
                $result = in_array($value, ['1', 'on', 'yes']);
                break;
            case 'date':
                // 是否是一个有效日期
                $result = false !== strtotime($value);
                break;
            case 'activeUrl':
                // 是否为有效的网址
                $result = checkdnsrr($value);
                break;
            case 'boolean':
            case 'bool':
                // 是否为布尔值
                $result = in_array($value, [true, false, 0, 1, '0', '1'], true);
                break;
            case 'number':
                $result = ctype_digit((string) $value);
                break;
            case 'alphaNum':
                $result = ctype_alnum($value);
                break;
            case 'array':
                // 是否为数组
                $result = is_array($value);
                break;
            default:
                if (isset($this->type[$rule])) {
                    // 注册的验证规则
                    $result = call_user_func_array($this->type[$rule], [$value]);
                } elseif (function_exists('ctype_' . $rule)) {
                    // ctype验证规则
                    $ctypeFun = 'ctype_' . $rule;
                    $result   = $ctypeFun($value);
                } elseif (isset($this->filter[$rule])) {
                    // Filter_var验证规则
                    $result = $this->filter($value, $this->filter[$rule]);
                } else {
                    // 正则验证
                    $result = $this->regex($value, $rule);
                }
        }

        return $result;
    }

    /**
     * 使用filter_var方式验证
     * @param mixed $value 字段值
     * @param mixed $rule  验证规则
     * @return bool
     */
    public function filter($value, $rule): bool
    {
        if (isset($this->filter[$rule])) {
            $rule = $this->filter[$rule];
        }

        if (is_string($rule) && strpos($rule, ',')) {
            list($rule, $param) = explode(',', $rule);
        } elseif (is_array($rule)) {
            $param = $rule[1] ?? null;
            $rule  = $rule[0];
        } else {
            $param = null;
        }

        return false !== filter_var($value, is_int($rule) ? $rule : filter_id($rule), $param);
    }

    /**
     * 使用正则验证数据
     * @param mixed $value 字段值
     * @param mixed $rule  验证规则 正则规则或者预定义正则名
     * @return bool
     */
    public function regex($value, $rule): bool
    {
        if (isset($this->regex[$rule])) {
            $rule = $this->regex[$rule];
        } elseif (isset($this->defaultRegex[$rule])) {
            $rule = $this->defaultRegex[$rule];
        }

        if (is_string($rule) && 0 !== strpos($rule, '/') && !preg_match('/\/[imsU]{0,4}$/', $rule)) {
            // 不是正则表达式则两端补上/
            $rule = '/^' . $rule . '$/';
        }

        return is_scalar($value) && 1 === preg_match($rule, (string) $value);
    }

    /**
     * 验证数据长度
     * @access public
     * @param mixed $value 字段值
     * @param mixed $rule  验证规则
     * @return bool
     */
    public function length($value, $rule): bool
    {
        $length = is_array($value) ? count($value) : mb_strlen((string) $value);

        // {n,}|{n,m}
        if (is_string($rule) && strpos($rule, ',')) {
            // 长度区间
            list($min, $max) = explode(',', $rule);
            return !empty($max) ? $length >= $min && $length <= $max : $length >= $min;
        }

        // {n}
        return $length == $rule;
    }

    /**
     * 动态方法 直接调用is方法进行验证
     * @param string $method 方法名
     * @param array  $args   调用参数
     * @return bool
     */
    public function __call($method, $args)
    {
        if ('is' == strtolower(substr($method, 0, 2))) {
            $method = substr($method, 2);
        }

        array_push($args, lcfirst($method));

        return call_user_func_array([$this, 'is'], $args);
    }
}
<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/24
// +----------------------------------------------------------------------
// | Title:  Annotation.php
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use think\exception\InvalidArgumentException;

/**
 * 注解类
 * Class Annotation
 * @package think
 */
class Annotation
{
    /**
     * 请求对象
     * @var App
     */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 路由调度注解
     * @param object|string $reflect 对象实例|完整控制器名
     * @param string        $method  指定方法名
     * @return array
     */
    public function dispatch($reflect, string $method)
    {
        $comment = $this->getDocComment($reflect, $method);

        $classDoc  = !empty($comment['class']) ? $comment['class'] : '';
        $methodDoc = !empty($comment[$method]) ? $comment[$method] : '';

        unset($comment);

        // 匹配注解
        $classDoc  = $this->matchingDocComment($classDoc);
        $methodDoc = $this->matchingDocComment($methodDoc);

        // 提取
        $class  = $this->extraction($classDoc);
        $method = $this->extraction($methodDoc);

        unset($classDoc);
        unset($methodDoc);

        return $this->merge($class, $method);
    }

    /**
     * 合并类和方法参数 (相同变量，方法变量会覆盖类变量)
     * @param array $class   类参数
     * @param array $method  方法参数(单个)
     * @param bool  $desc    是否返回方法描述
     * @return array
     */
    public function merge(array $class, array $method, bool $desc = false): array
    {
        if ($desc) {
            $result['desc'] = !empty($method['desc']) ? $method['desc'] : null;
        }

        $inputC = $class['input'] ?? [];
        $inputM = $method['input'] ?? [];

        $result['url']   = !empty($method['url']) ? $method['url'] : (!empty($class['url']) ? $class['url'] : 'GET');
        $result['input'] = array_merge($inputC, $inputM);

        if (!empty($class['middleware']) && !empty($method['middleware'])) {
            $middleware = array_merge($class['middleware'], $method['middleware']);
        } elseif (!empty($class['middleware'])) {
            $middleware = $class['middleware'];
        } elseif (!empty($method['middleware'])) {
            $middleware = $method['middleware'];
        }

        if (!empty($middleware) && is_array($middleware)) {
            $result['middleware'] = array_unique($middleware);
        }

        return $result;
    }

    /**
     * 获取文档注释
     * @param ReflectionClass|string $reflect   反射类|完整类名
     * @param string|null $method               指定方法|全部方法(大小写敏感)
     * @return array
     */
    public function getDocComment($reflect, string $method = null): array
    {
        try {
            if (!is_object($reflect)) {
                $reflect = new ReflectionClass($reflect);
            }

            $refMethods = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);

            $classDoc  = $reflect->isInstantiable() ? ($reflect->getDocComment() ?: []) : [];
            $methodDoc = [];
            foreach ($refMethods as $item) {
                $name = $item->getName();
                if (false === $item->isFinal() && false === $item->isStatic() && false === strpos($name,'_',0)) {
                    $item->getDocComment() && $methodDoc[$name] = $item->getDocComment();
                }
            }

            $classDoc = ['class' => $classDoc];
        } catch (ReflectionException $e) {
            $message = is_object($reflect) ? ($reflect->name ?? __CLASS__.'::'.__FUNCTION__) : $reflect;
            throw new InvalidArgumentException($message);
        }

        if (!is_null($method) && isset($methodDoc[$method])) {
            return array_merge($classDoc, [$method => $methodDoc[$method]]);
        }

        return array_merge($classDoc, $methodDoc);
    }

    /**
     * 提取定义的参数
     * @param array $comment
     * @return array
     */
    protected function extraction(array $comment): array
    {
        // 遍历匹配注解 ['Desc', 'Url', 'Input', 'Middleware']
        $result = [];
        foreach ($comment as $val) {
            // Desc
            if (0 === strpos($val, 'Desc', 0)) {
                $str = $this->matchingStr('Desc', $val);
                $result['desc'] = trim($str);
                continue;
            }
            // Url
            if (0 === strpos($val, 'Url', 0)) {
                $str = $this->matchingStr('Url', $val);
                $str && $result['url'] = strtoupper($str);
                continue;
            }
            // Input
            if (0 === strpos($val, 'Input', 0)) {
                $str = $this->matchingStr('Input', $val);
                list($name, $rally) = $this->parseInput($str);
                $name && $rally && $result['input'][$name] = $rally;
                continue;
            }
            // Middleware
            if (0 === strpos($val, 'Middleware', 0)) {
                $str = $this->matchingStr('Middleware', $val);
                $str = trim($str,"(\)");
                // 正则 ""
                preg_match_all('#"(.*?)"#i', $str, $str);
                // 去空
                $str = preg_grep("/\S+/i",$str[1]);
                $result['middleware'] = $str;
                continue;
            }
        }

        return $result;
    }

    /**
     * 解释Input参数
     * name=数据名称,type=参数类型,desc=说明,length=长度,default=默认值,filter=过滤规则
     * @param string $str
     * @return array
     */
    protected function parseInput(string $str): array
    {
        // 分割去重去空
        $input = array_filter(array_unique(explode(' ', $str)));

        $name  = ''; // 数据名称
        $rally = []; // 定义集合
        foreach ($input as $item) {
            $item = strtolower($item);

            if (preg_match('/^[A-Za-z0-9\-_]+$/', (string) $item)) {
                $rally['type'] = $item;
                continue;
            }

            if ('$' == substr($item, 0, 1)) {
                $name = substr($item, 1);
                continue;
            }

            if ('{' == substr($item, 0, 1) && '}' == substr($item, -1, 1)) {
                $rally['length'] = trim($item,"\{\}");
                continue;
            }

            if (false !== strpos($item, '=',1)) {
                list($key, $val) = explode('=', $item, 2);
                preg_match_all('#"(.*)"#i', $val, $vals);
                !empty($vals[1][0]) && $rally[$key] = trim($vals[1][0]);
            }
        }

        if (!empty($name) && !empty($rally)) {
            return [$name, $rally];
        }

        return [null, null];
    }

    /**
     * 匹配定义方法
     * @param string $name
     * @param string $value
     * @return string
     */
    protected function matchingStr(string $name, string $value): string
    {
        // 严格大小写匹配
        if (false !== strstr($value, $name)) {
            // 截取已匹配的字符串后字符
            $str = substr_replace($value, '', 0, strlen($name));
            // 首尾去空等
            $result = trim($str);
        }

        return $result ?? '';
    }

    /**
     * 匹配注解返回数组
     * @param string $comment 注解内容
     * @param string $context 匹配
     * @return array
     */
    protected function matchingDocComment(string $comment, string $context = '* @'): array
    {
        // 正则匹配
        $context = '#^\s*\\'.$context.'(.*)#m';
        preg_match('#^/\*\*(.*)\*/#s', $comment, $comment);
        $comment = isset($comment[1]) ? trim($comment[1]) : '';
        // 全局正则表达式
        preg_match_all($context, $comment, $lines, PREG_PATTERN_ORDER);

        return $lines[1] ?? [];
    }
}
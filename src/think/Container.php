<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Container.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use think\exception\ServerException;
use think\helper\Str;

/**
 * 容器管理类 支持PSR-11
 * Class Container
 * @package think
 */
class Container implements ContainerInterface, ArrayAccess, IteratorAggregate, Countable
{
    /**
     * 容器对象实例
     * @var Container|Closure
     */
    protected static $instance;

    /**
     * 容器中的对象实例
     * @var array
     */
    protected $instances = [];

    /**
     * 容器绑定标识
     * @var array
     */
    protected $bind = [];

    /**
     * 容器回调
     * @var array
     */
    protected $invokeCallback = [];

    /**
     * 获取当前容器的实例（单例）
     * @access public
     * @return Closure|mixed|Container
     */
    public static function getInstance() {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        if (static::$instance instanceof Closure) {
            return (static::$instance)();
        }

        return static::$instance;
    }

    /**
     * 设置当前容器的实例
     * @access public
     * @param object|Closure $instance
     * @return void
     */
    public static function setInstance($instance): void
    {
        static::$instance = $instance;
    }

    /**
     * 注册一个容器对象回调
     *
     * @param string|Closure $abstract
     * @param Closure|null   $callback
     * @return void
     */
    public function resolving($abstract, Closure $callback = null): void
    {
        if ($abstract instanceof Closure) {
            $this->invokeCallback['*'][] = $abstract;
            return;
        }

        $abstract = $this->getAlias($abstract);

        $this->invokeCallback[$abstract][] = $callback;
    }

    /**
     * 根据别名获取真实类名
     * @param  string $abstract
     * @return string
     */
    public function getAlias(string $abstract): string
    {
        if (isset($this->bind[$abstract])) {
            $bind = $this->bind[$abstract];

            if (is_string($bind)) {
                return $this->getAlias($bind);
            }
        }

        return $abstract;
    }

    /**
     * 获取容器中的对象实例 不存在则创建
     * @access public
     * @param string $abstract 类名或者标识
     * @param array|true $vars 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return object
     */
    public static function pull(string $abstract, array $vars = [], bool $newInstance = false)
    {
        return static::getInstance()->make($abstract, $vars, $newInstance);
    }

    /**
     * 创建类的实例 已经存在则直接获取
     * @access public
     * @param string $abstract 类名或者标识
     * @param array $vars 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return mixed
     */
    public function make(string $abstract, array $vars = [], bool $newInstance = false)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract]) && !$newInstance) {
            return $this->instances[$abstract];
        }

        if (isset($this->bind[$abstract]) && $this->bind[$abstract] instanceof Closure) {
            $object = $this->invokeFunction($this->bind[$abstract], $vars);
        } else {
            $object = $this->invokeClass($abstract, $vars);
        }

        if (!$newInstance) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed        $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function bind($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            foreach ($abstract as $key => $val) {
                $this->bind($key, $val);
            }
        } elseif ($concrete instanceof Closure) {
            $this->bind[$abstract] = $concrete;
        } elseif (is_object($concrete)) {
            $this->instance($abstract, $concrete);
        } else {
            $abstract = $this->getAlias($abstract);

            $this->bind[$abstract] = $concrete;
        }

        return $this;
    }

    /**
     * 绑定一个类实例到容器
     * @access public
     * @param string $abstract 类名或者标识
     * @param object $instance 类的实例
     * @return $this
     */
    public function instance(string $abstract, $instance)
    {
        $abstract = $this->getAlias($abstract);

        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete($name)
    {
        $name = $this->getAlias($name);

        if (isset($this->instances[$name])) {
            unset($this->instances[$name]);
        }
    }

    /**
     * 绑定参数
     * @access protected
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array $vars 参数
     * @return array
     * @throws ReflectionException
     */
    protected function bindParams($reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $name      = $param->getName();
            $lowerName = Str::snake($name);
            $class     = $param->getClass();

            if ($class) {
                $args[] = $this->getObjectParam($class->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && isset($vars[$name])) {
                $args[] = $vars[$name];
            } elseif (0 == $type && isset($vars[$lowerName])) {
                $args[] = $vars[$lowerName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new ServerException(5006,'method param miss:' . $name);
            }
        }

        return $args;
    }

    /**
     * 获取对象类型的参数值
     * @access protected
     * @param string $className 类名
     * @param array $vars 参数
     * @return mixed
     */
    protected function getObjectParam(string $className, array &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $this->make($className);
        }

        return $result;
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|array|Closure $function 函数或者闭包
     * @param array $vars 参数
     * @return mixed
     */
    public function invokeFunction($function, array $vars = [])
    {
        try {
            $reflect = new ReflectionFunction($function);

            $args = $this->bindParams($reflect, $vars);

            if ($reflect->isClosure()) {
                // 解决在`php7.1`调用时会产生`$this`上下文不存在的错误 (https://bugs.php.net/bug.php?id=66430)
                return $function->__invoke(...$args);
            } else {
                return $reflect->invokeArgs($args);
            }
        } catch (ReflectionException $e) {
            // 如果是调用闭包时发生错误则尝试获取闭包的真实位置
            if (isset($reflect) && $reflect->isClosure() && $function instanceof Closure) {
                $function = "{Closure}@{$reflect->getFileName()}#L{$reflect->getStartLine()}-{$reflect->getEndLine()}";
            } else {
                $function .= '()';
            }
            throw new ServerException(5001,'function not exists: ' . $function, $e);
        }
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param mixed $method 方法
     * @param array $vars 参数
     * @param bool $accessible 设置是否可访问
     * @return mixed
     */
    public function invokeMethod($method, array $vars = [], bool $accessible = false)
    {
        try {
            if (is_array($method)) {
                $class   = is_object($method[0]) ? $method[0] : $this->invokeClass($method[0]);
                $reflect = new ReflectionMethod($class, $method[1]);
            } else {
                // 静态方法
                $reflect = new ReflectionMethod($method);
            }

            $args = $this->bindParams($reflect, $vars);

            if ($accessible) {
                $reflect->setAccessible($accessible);
            }

            return $reflect->invokeArgs($class ?? null, $args);
        } catch (ReflectionException $e) {
            if (is_array($method)) {
                $class    = is_object($method[0]) ? get_class($method[0]) : $method[0];
                $callback = $class . '::' . $method[1];
            } else {
                $callback = $method;
            }

            throw new ServerException(5002,'method not exists: ' . $callback . '()', $e);
        }
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param object $instance 对象实例
     * @param mixed $reflect 反射类
     * @param array $vars 参数
     * @return mixed
     * @throws ReflectionException
     */
    public function invokeReflectMethod($instance, $reflect, array $vars = [])
    {
        $args = $this->bindParams($reflect, $vars);

        return $reflect->invokeArgs($instance, $args);
    }

    /**
     * 调用反射执行callable 支持参数绑定
     * @access public
     * @param mixed $callable
     * @param array $vars 参数
     * @param bool $accessible 设置是否可访问
     * @return mixed
     */
    public function invoke($callable, array $vars = [], bool $accessible = false)
    {
        if ($callable instanceof Closure) {
            return $this->invokeFunction($callable, $vars);
        }

        return $this->invokeMethod($callable, $vars, $accessible);
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array $vars 参数
     * @return mixed
     */
    public function invokeClass(string $class, array $vars = [])
    {
        try {
            $reflect = new ReflectionClass($class);

            if ($reflect->hasMethod('__make')) {
                $method = new ReflectionMethod($class, '__make');

                if ($method->isPublic() && $method->isStatic()) {
                    $args = $this->bindParams($method, $vars);
                    return $method->invokeArgs(null, $args);
                }
            }

            $constructor = $reflect->getConstructor();

            $args = $constructor ? $this->bindParams($constructor, $vars) : [];

            $object = $reflect->newInstanceArgs($args);

            $this->invokeAfter($class, $object);

            return $object;
        } catch (ReflectionException $e) {
            throw new ServerException(5003,'class not exists: ' . $class, $e);
        }
    }

    /**
     * 执行invokeClass回调
     * @access protected
     * @param string $class  对象类名
     * @param object $object 容器对象实例
     * @return void
     */
    protected function invokeAfter(string $class, $object): void
    {
        if (isset($this->invokeCallback['*'])) {
            foreach ($this->invokeCallback['*'] as $callback) {
                $callback($object, $this);
            }
        }

        if (isset($this->invokeCallback[$class])) {
            foreach ($this->invokeCallback[$class] as $callback) {
                $callback($object, $this);
            }
        }
    }

    /**
     * 创建工厂对象实例
     * @param string $name      工厂类名
     * @param string $namespace 默认命名空间
     * @param array  $args
     * @return mixed
     * @deprecated
     * @access public
     */
    public static function factory(string $name, string $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        }

        throw new ServerException(5004,'class not exists:' . $class);
    }

    /**
     * 判断容器中是否存在对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function exists(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->instances[$abstract]);
    }

    /**
     * 根据容器的标识符找到该容器
     * @param string $abstract 类名或者标识
     * @return mixed|void
     */
    public function get($abstract)
    {
        if ($this->has($abstract)) {
            return $this->make($abstract);
        }

        throw new ServerException(5005,'class not exists: ' . $abstract);
    }

    /**
     * 判断容器中是否有标识
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function has($abstract): bool
    {
        return isset($this->bind[$abstract]) || isset($this->instances[$abstract]);
    }

    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return $this->exists($name);
    }

    public function __unset($name)
    {
        $this->delete($name);
    }

    /**
     * Whether a offset exists
     * @param mixed $key
     * @return bool|void
     */
    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    /**
     * Offset to retrieve
     * @param mixed $key
     * @return mixed|void
     */
    public function offsetGet($key)
    {
        return $this->make($key);
    }

    /**
     * Offset to set
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        $this->bind($key, $value);
    }

    /**
     * Offset to unset
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        $this->delete($key);
    }

    /**
     * Retrieve an external iterator
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->instances);
    }

    /**
     * Count elements of an object
     * @return int|void
     */
    public function count()
    {
        return count($this->instances);
    }
}
<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\exception;

use Exception;
use think\App;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Response;
use think\response\Json as ResponseJson;
use Throwable;

/**
 * 系统异常处理类
 */
class Handle
{
    /** @var App */
    protected $app;

    /**
     * HTTP状态码描述
     * @var array
     * @author Peter.Zhang
     */
    protected $httpStatusCode = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        412 => 'Precondition Failed',
        500 => 'Server Internal Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    protected $ignoreReport = [
        ApiException::class,
        ResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * isDebug
     * @var bool
     * @author Peter.Zhang
     */
    protected $isDebug = false;

    /**
     * isTrace
     * @var bool
     * @author Peter.Zhang
     */
    protected $isTrace = false;

    protected $errorMsg  = null;

    protected $errorCode = 0;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->isDebug = $app->isDebug();
        $this->isTrace = $app->env->get('APP_TRACE');
    }

    /**
     * 异常写入日志
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            $data = [
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'message' => $this->getErrorMsg($exception),
                'code'    => $this->getErrorCode($exception),
            ];
            $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";

            if ($this->app->config->get('log.record_trace')) {
                $log .= PHP_EOL . $exception->getTraceAsString();
            }

            $this->app->log->record($log, 'error');
        }
    }

    /**
     * 鉴别不记录列表
     * @param Throwable $exception
     * @return bool
     * @author Peter.Zhang
     */
    protected function isIgnoreReport(Throwable $exception): bool
    {
        foreach ($this->ignoreReport as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * 异常响应输出
     * @access public
     * @param Throwable $exception
     * @return Response
     */
    public function render(Throwable $exception): Response
    {
        $data = $this->convertExceptionToArray($exception);

        if ($this->isDebug && $this->isTrace) {
            //保留一层
            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            $data['echo'] = ob_get_clean();

            ob_start();
            extract($data);
            include $this->app->config->get('app.exception_tmpl') ?: __DIR__ . '/../../tpl/think_exception.tpl';

            // 获取并清空缓存
            $data     = ob_get_clean();
            $response = new Response($data);
        } else {
            $response = new ResponseJson($data);
        }

        if ($exception instanceof ApiException) {
            $apiCode = $exception->getCode();
            $response->header($exception->getHeaders());
        }

        return $response->code($apiCode ?? 500);
    }

    /**
     * 控制台输出
     * @access public
     * @param  Output    $output
     * @param  Throwable $e
     */
    public function renderForConsole(Output $output, Throwable $e): void
    {
        if ($this->isDebug) {
            $output->setVerbosity(Output::VERBOSITY_DEBUG);
        }

        $output->renderException($e);
    }

    /**
     * 收集异常数据
     * @param Throwable $exception
     * @return array
     */
    protected function convertExceptionToArray(Throwable $exception): array
    {
        if ($this->isDebug) {
            // 详细错误信息
            $data['error_code']  = $this->getErrorCode($exception);
            $data['error_msg']   = $this->getErrorMsg($exception);
            $data['error_debug'] = [
                'name'    => get_class($exception),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTrace(),
                'source'  => $this->getSourceCode($exception),
                'datas'   => $this->getExtendData($exception),
                'tables'  => [
                    'GET Data'              => $_GET,
                    'POST Data'             => $_POST,
                    'Files'                 => $_FILES,
                    'Cookies'               => $_COOKIE,
                    'Session'               => $_SESSION ?? [],
                    'Server/Request Data'   => $_SERVER,
                    'Environment Variables' => $_ENV,
                    'ThinkPMS Constants'    => $this->getConst(),
                ],
            ];
        } else {
            // 部署模式仅显示 error_code 和 error_msg
            $httpCode  = $exception->getCode();
            $errorCode = $this->getErrorCode($exception);

            $data['error_code'] = $this->getErrorCode($exception);
            $data['error_msg']  = isset($this->httpStatusCode[$httpCode]) ? $this->httpStatusCode[$httpCode] : 'Access Errors :  ' . $errorCode;
        }

        return $data;
    }

    /**
     * 获取错误编码
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  Throwable $exception
     * @return integer                错误编码
     */
    protected function getErrorCode(Throwable $exception)
    {
        if ($this->errorCode !== 0) {
            return $this->errorCode;
        }

        switch (true) {
            case $exception instanceof ErrorException:
                $code = $exception->getSeverity();
                break;
            case $exception instanceof ServerException:
                $code = $exception->getErrorCode();
                break;
            case $exception instanceof ApiException:
                $code = $exception->getStatusCode();
                break;
            default:
                $code = $exception->getCode();
                break;
        }

        $this->errorCode = $code;

        return $code;
    }

    /**
     * 获取错误信息
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  Throwable $exception
     * @return string                错误信息
     */
    protected function getErrorMsg(Throwable $exception): string
    {
        if ($this->errorMsg !== null) {
            return $this->errorMsg;
        }

        $message = $exception->getMessage();

        if (!$this->isDebug && $exception instanceof ErrorException) {
            $message = 'System Exception';
        }

        if ($this->app->runningInConsole()) {
            $this->errorMsg = $message;
            return $message;
        }

        if ($this->app->has('lang')) {
            $lang = $this->app->lang;

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
        $this->errorMsg = $message;

        return $message;
    }

    /**
     * 获取出错文件内容
     * 获取错误的前9行和后9行
     * @access protected
     * @param  Throwable $exception
     * @return array                 错误文件内容
     */
    protected function getSourceCode(Throwable $exception): array
    {
        // 读取前9行和后9行
        $line  = $exception->getLine();
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($exception->getFile()) ?: [];
            $source   = [
                'first'  => $first,
                'source' => array_slice($contents, $first - 1, 19),
            ];
        } catch (Exception $e) {
            $source = [];
        }

        return $source;
    }

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     * @access protected
     * @param  Throwable $exception
     * @return array                 异常类定义的扩展数据
     */
    protected function getExtendData(Throwable $exception): array
    {
        $data = [];

        if ($exception instanceof \think\Exception) {
            $data = $exception->getData();
        }

        return $data;
    }

    /**
     * 获取常量列表
     * @access private
     * @return array 常量列表
     */
    private static function getConst(): array
    {
        $const = get_defined_constants(true);

        return $const['user'] ?? [];
    }
}

<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  Handle.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\exception;

use think\App;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Response;
use Throwable;

/**
 * 系统异常处理类
 * Class Handle
 * @package think\exception
 */
class Handle
{
    /** @var App */
    protected $app;

    protected $ignoreReport = [
        ApiException::class,
        ResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        InvalidArgumentException::class,
    ];

    /**
     * 处理过的异常信息
     * @var array
     */
    protected $info = [];

    /**
     * isDebug
     * @var bool
     */
    protected $isDebug = false;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->isDebug = $app->isDebug();
    }

    /**
     * 异常日志写入
     * @param Throwable $exception
     */
    public function report(Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            $data = $this->getExceptionInfo($exception, 'report');

            // 收集异常数据
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();

            $log = "[{$data['status']}]{$data['message']}[{$data['file']}:{$data['line']}]";

            if ($this->app->config->get('log.record_trace')) {
                $log .= PHP_EOL . $exception->getTraceAsString();
            }

            $this->app->log->record($log, 'error');
        }
    }

    /**
     * 异常客户端输出
     * @param $request
     * @param Throwable $exception
     * @return Response|\Exception
     */
    public function render($request, Throwable $exception): Response
    {
        // 响应异常输出
        if ($exception instanceof ResponseException) {
            return $exception->getResponse();
        }

        // 收集异常数据
        $data = $this->convertExceptionToArray($exception);
        $code = $data['code'];

        $response = Response::create('', $this->app->response());

        if ($this->isDebug) {
            $response->debug($data['debug']);
        }
        $response->status((int)$data['status'])->message((string)$data['message']);

        unset($data);

        if ($exception instanceof ApiException) {
            $response->header($exception->getHeaders());
        }

        return $response->code($code);
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
     * 判断不需要记录信息（日志）的异常类列表
     * @access protected
     * @param Throwable $exception
     * @return bool
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
     * 收集异常数据
     * @param Throwable $exception
     * @return array
     */
    protected function convertExceptionToArray(Throwable $exception): array
    {
        $data = $this->getExceptionInfo($exception, 'render');

        if ($this->isDebug) {
            // 详细错误信息
            $data['debug']   = [
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
            $data['debug'] = '';
        }

        return $data;
    }

    /**
     * 获取错误编码
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  Throwable $exception
     * @param  string    $name
     * @return array
     */
    protected function getExceptionInfo(Throwable $exception, $name = 'render'): array
    {
        if (isset($this->info[$name])) {
            return $this->info[$name];
        }

        $message = !empty($exception->getMessage()) ? $exception->getMessage() : 'System Exception';

        switch (true) {
            case $exception instanceof ErrorException:
                $code      = $exception->getCode();
                $status    = $exception->getSeverity();
                $rendermsg = $this->isDebug ? $message : 'System Exception';
                break;
            case $exception instanceof ServerException:
                $code      = $exception->getCode();
                $status    = $exception->getApiCode();
                $rendermsg = $this->isDebug ? $message : 'System Exception';
                break;
            case $exception instanceof ApiException:
                $code      = $exception->getCode();
                $status    = $exception->getApiCode();
                $rendermsg = $this->isDebug ? $message : (0 === $status || empty($message) ? '' : $message);
                break;
            case $exception instanceof InvalidArgumentException:
                $code      = $exception->getCode();
                $status    = $exception->getApiCode();
                $rendermsg = $this->isDebug ? $message : 'Invalid Argument';
                break;
            default:
                $code      = 500;
                $status    = 50000;
                $rendermsg = $this->isDebug ? $message : 'System Exception';
                break;
        }

        // 日志
        $this->info['report'] = ['code' => $code, 'status' => $status, 'message' => $message];

        // 输出
        $this->info['render'] = ['code' => $code, 'status' => $status, 'message' => $rendermsg];

        return $this->info[$name];
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
        } catch (\Exception $e) {
            $source = [];
        }

        return $source;
    }

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     * @access protected
     * @param Throwable $exception
     * @return array                 异常类定义的扩展数据
     */
    protected function getExtendData(Throwable $exception)
    {
        $data = [];

        if ($exception instanceof Exception) {
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
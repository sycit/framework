<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/19
// +----------------------------------------------------------------------
// | Title:  SessionInit.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use think\Session;

/**
 * Session 初始化
 * Class SessionInit
 * @package think\middleware
 */
class SessionInit
{
    /** @var App */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->init();
    }

    /**
     * Session初始化
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        // Session初始化
        $varSessionId = $this->app->session->config('var_session_id');
        $cookieName   = $this->app->session->getName();

        if ($varSessionId && $request->request($varSessionId)) {
            $sessionId = $request->request($varSessionId);
        } else {
            $sessionId = $request->cookie($cookieName);
        }

        $this->app->session->setId($sessionId);
        $this->app->session->init();

        $request->withSession($this->app->session);

        /** @var Response $response */
        $response = $next($request);

        $response->setSession($this->app->session);

        $this->app->cookie->set($cookieName, $this->app->session->getId());

        return $response;
    }

    public function end(Response $response)
    {
        $this->app->session->save();
    }

    protected function init()
    {
        // 绑定 session 标识
        $this->app->bind('session',Session::class);
    }
}
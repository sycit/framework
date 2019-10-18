<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/22
// +----------------------------------------------------------------------
// | Title:  LoadLangPack.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\Lang;

/**
 * 多语言中间件
 * Class LoadLangPack
 * @package think\middleware
 */
class LoadLangPack
{
    protected $app;

    protected $lang;

    public function __construct(App $app, Lang $lang)
    {
        $this->app  = $app;
        $this->lang = $lang;

        $this->init();
    }

    /**
     * 自动识别语言，加载语言包
     * @param $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 自动侦测当前语言
        $langset = $this->lang->detect($request);

        if ($this->lang->defaultLangSet() != $langset) {
            $this->app->LoadLangPack($langset);
        }

        // 加载全局语言包
        if ($defaultPath = $this->lang->config('default_lang_path')) {
            // 格式：zh-cn.php/en.php
            $this->lang->load([
                $this->app->getRootPath() . $defaultPath . DIRECTORY_SEPARATOR . $langset . '.php',
            ]);
        }

        $this->lang->saveToCookie($this->app->cookie);

        return $next($request);
    }

    protected function init()
    {
        // 绑定 lang 标识
        $this->app->bind('lang',Lang::class);

        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }
}
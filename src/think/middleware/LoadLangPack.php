<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\Lang;
use think\Request;
use think\Response;

/**
 * 多语言加载
 */
class LoadLangPack
{

    /**
     * 多语言初始化
     * @access public
     * @param Request $request
     * @param Closure $next
     * @param Lang    $lang
     * @param App     $app
     * @return Response
     */
    public function handle($request, Closure $next, Lang $lang, App $app)
    {
        // 绑定 Lang 到容器
        $app->bind('lang',Lang::class);

        // 自动侦测当前语言
        $langset = $lang->detect();

        if (!empty($langset)) {
            // 加载系统语言包
            $lang->load([
                $app->getThinkPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
            ]);

            $this->LoadLangPack($langset, $lang, $app);
        }

        $lang->saveToCookie($app->cookie);

        return $next($request);
    }

    /**
     * 加载语言包
     * @param string $langset 语言
     * @param Lang $lang
     * @param App $app
     * @author Peter.Zhang
     */
    protected function loadLangPack($langset, Lang $lang, App $app)
    {
        // 加载应用语言包
        $files = glob($app->getAppPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.*');
        $lang->load($files);

        // 加载扩展（自定义）语言包
        $list = $app->config->get('lang.extend_list', []);

        if (isset($list[$langset])) {
            $lang->load($list[$langset]);
        }
    }
}

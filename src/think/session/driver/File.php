<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2019  http://www.sycit.cn
// +----------------------------------------------------------------------
// | Author: Peter.Zhang  <hyzwd@outlook.com>
// +----------------------------------------------------------------------
// | Date:   2019/9/18
// +----------------------------------------------------------------------
// | Title:  File.php
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace think\session\driver;

use Closure;
use Exception;
use FilesystemIterator;
use Generator;
use SplFileInfo;
use think\App;
use think\contract\SessionHandlerInterface;

class File implements SessionHandlerInterface
{
    protected $config = [
        'path'           => '',
        'expire'         => 1440,
        'prefix'         => '',
        'data_compress'  => false,
        'gc_probability' => 1,
        'gc_divisor'     => 100,
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->config = array_merge($this->config, $config);

        if (empty($this->config['path'])) {
            $this->config['path'] = $app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . 'session' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }

        $this->init();
    }

    /**
     * 打开Session
     * @access protected
     * @throws Exception
     */
    protected function init(): void
    {
        try {
            // 打开Session
            !is_dir($this->config['path']) && mkdir($this->config['path'], 0755, true);

            // 垃圾回收
            if (random_int(1, $this->config['gc_divisor']) <= $this->config['gc_probability']) {
                $this->gc();
            }
        } catch (Exception $e) {
            // 写入失败
        }
    }

    /**
     * Session 垃圾回收
     * @access public
     * @return void
     */
    public function gc(): void
    {
        $lifetime = $this->config['expire'];
        $now      = time();

        $files = $this->findFiles($this->config['path'], function (SplFileInfo $item) use ($lifetime, $now) {
            return $now > $item->getMTime() + $lifetime;
        });

        foreach ($files as $file) {
            $this->unlink($file->getPathname());
        }
    }

    /**
     * 查找文件
     * @param string  $root
     * @param Closure $filter
     * @return Generator
     */
    protected function findFiles(string $root, Closure $filter)
    {
        $items = new FilesystemIterator($root);

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                yield from $this->findFiles($item->getPathname(), $filter);
            } else {
                if ($filter($item)) {
                    yield $item;
                }
            }
        }
    }

    /**
     * 取得变量的存储文件名
     * @access protected
     * @param string $name 缓存变量名
     * @param bool   $auto 是否自动创建目录
     * @return string
     */
    protected function getFileName(string $name, bool $auto = false): string
    {
        if ($this->config['prefix']) {
            // 使用子目录
            $name = $this->config['prefix'] . DIRECTORY_SEPARATOR . 'sess_' . $name;
        } else {
            $name = 'sess_' . $name;
        }

        $filename = $this->config['path'] . $name;
        $dir      = dirname($filename);

        if ($auto && !is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (\Exception $e) {
                // 创建失败
            }
        }

        return $filename;
    }

    /**
     * 写文件（加锁）
     * @param $path
     * @param $content
     * @return bool
     */
    protected function writeFile($path, $content): bool
    {
        return (bool) file_put_contents($path, $content, LOCK_EX);
    }

    /**
     * 读取文件内容(加锁)
     * @param $path
     * @return string
     */
    protected function readFile($path): string
    {
        $contents = '';

        $handle = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, filesize($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * 判断文件是否存在后，删除
     * @access private
     * @param string $file
     * @return bool
     */
    private function unlink(string $file): bool
    {
        return is_file($file) && unlink($file);
    }

    /**
     * 读取
     * @param string $sessionId
     * @return string
     */
    public function read(string $sessionId): string
    {
        $filename = $this->getFileName($sessionId);

        if (is_file($filename) && filemtime($filename) >= time() - $this->config['expire']) {
            $content = $this->readFile($filename);

            if ($this->config['data_compress'] && function_exists('gzcompress')) {
                //启用数据压缩
                $content = gzuncompress($content);
            }

            return $content;
        }

        return '';
    }

    /**
     * 删除
     * @param string $sessionId
     * @return bool
     */
    public function delete(string $sessionId): bool
    {
        try {
            return $this->unlink($this->getFileName($sessionId));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 写入
     * @param string $sessionId
     * @param string $sessData
     * @return bool
     */
    public function write(string $sessionId, string $sessData): bool
    {
        $filename = $this->getFileName($sessionId, true);

        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $sessData = gzcompress($sessData, 3);
        }

        return $this->writeFile($filename, $sessData);
    }
}
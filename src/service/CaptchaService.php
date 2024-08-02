<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2024 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Library;
use think\admin\Service;

/**
 * 图形验证码服务
 * @class CaptchaService
 * @package think\admin\service
 */
class CaptchaService extends Service
{
    private $code; // 验证码
    private $uniqid; // 唯一序号
    private $charset = 'ABCDEFGHKMNPRSTUVWXYZ23456789'; // 随机因子
    private $width = 130; // 图片宽度
    private $height = 50; // 图片高度
    private $length = 4; // 验证码长度
    private $fontsize = 20; // 指定字体大小

    /**
     * 验证码服务初始化
     * @param array $config
     * @return static
     */
    public function initialize(array $config = []): CaptchaService
    {
        // 动态配置属性
        foreach ($config as $k => $v) if (isset($this->$k)) $this->$k = $v;
        // 生成验证码序号
        $this->uniqid = uniqid('captcha') . mt_rand(1000, 9999);
        // 生成验证码字符串
        [$this->code, $length] = ['', strlen($this->charset) - 1];
        for ($i = 0; $i < $this->length; $i++) {
            $this->code .= $this->charset[mt_rand(0, $length)];
        }
        // 缓存验证码字符串
        $this->app->cache->set($this->uniqid, $this->code, 360);
        // 返回当前对象
        return $this;
    }

    /**
     * 动态切换配置
     * @param array $config
     * @return $this
     */
    public function config(array $config = []): CaptchaService
    {
        return $this->initialize($config);
    }

    /**
     * 获取验证码值
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * 获取图片的内容
     * @return string
     */
    public function getData(): string
    {
        return "data:image/png;base64,{$this->_image()}";
    }

    /**
     * 获取验证码编号
     * @return string
     */
    public function getUniqid(): string
    {
        return $this->uniqid;
    }

    /**
     * 获取验证码数据
     * @return array
     */
    public function getAttrs(): array
    {
        return [
            'code'   => $this->getCode(),
            'data'   => $this->getData(),
            'uniqid' => $this->getUniqid(),
        ];
    }

    /**
     * 输出图形验证码
     * @return string
     */
    public function __toString()
    {
        return $this->getData();
    }

    /**
     * 创建验证码图片
     * @return string
     */
    private function _image(): string
    {
        // 生成背景
        $img = imagecreatetruecolor($this->width, $this->height);
        $color = imagecolorallocate($img, mt_rand(220, 255), mt_rand(220, 255), mt_rand(220, 255));
        imagefilledrectangle($img, 0, $this->height, $this->width, 0, $color);
        // 生成线条
        for ($i = 0; $i < 6; $i++) {
            $color = imagecolorallocate($img, mt_rand(0, 50), mt_rand(0, 50), mt_rand(0, 50));
            imageline($img, mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(0, $this->width), mt_rand(0, $this->height), $color);
        }
        // 生成雪花
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($img, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
            imagestring($img, mt_rand(1, 5), mt_rand(0, $this->width), mt_rand(0, $this->height), '*', $color);
        }
        // 生成文字
        $_x = $this->width / $this->length;
        $fontfile = self::font();
        for ($i = 0; $i < $this->length; $i++) {
            $fontcolor = imagecolorallocate($img, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            if (function_exists('imagettftext')) {
                imagettftext($img, $this->fontsize, mt_rand(-30, 30), intval($_x * $i + mt_rand(1, 5)), intval($this->height / 1.4), $fontcolor, $fontfile, $this->code[$i]);
            } else {
                imagestring($img, 15, intval($_x * $i + mt_rand(10, 15)), mt_rand(10, 30), $this->code[$i], $fontcolor);
            }
        }
        ob_start();
        imagepng($img);
        $data = ob_get_contents();
        ob_end_clean();
        imagedestroy($img);
        return base64_encode($data);
    }

    /**
     * 获取字体文件
     * @return string
     */
    public static function font(): string
    {
        return __DIR__ . '/bin/captcha.ttf';
    }

    /**
     * 检查验证码是否正确
     * @param string $code 需要验证的值
     * @param null|string $uniqid 验证码编号
     * @return boolean
     */
    public static function check(string $code, ?string $uniqid = null): bool
    {
        $_uni = is_string($uniqid) ? $uniqid : input('uniqid', '-');
        $_val = Library::$sapp->cache->get($_uni, '');
        if (is_string($_val) && strtolower($_val) === strtolower($code)) {
            Library::$sapp->cache->delete($_uni);
            return true;
        } else {
            return false;
        }
    }
}
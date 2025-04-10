<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
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

namespace think\admin\extend;

use think\admin\Library;
use think\admin\Storage;
use think\admin\storage\LocalStorage;

/**
 * 拼图拖拽验证器
 * @class ImageVerify
 * @package think\admin\extend
 */
class ImageVerify
{
    // 浮层圆半径
    private $r = 10;

    // 图片路径
    private $srcImage;

    // 浮层图宽高
    private $picWidth = 100;
    private $picHeight = 100;

    // 目标图宽高
    private $dstWidth = 600;
    private $dstHeight = 300;

    /**
     * 验证器构造方法
     * @param string $image 原始图片
     * @param array $options 配置参数
     */
    public function __construct(string $image, array $options = [])
    {
        if (!empty($options)) {
            foreach ($options as $k => $v) {
                if (isset($this->$k)) $this->$k = $v;
            }
        }
        $this->srcImage = $image;
    }

    /**
     * 生成图片拼图
     * @param string $image 原始图片
     * @param integer $time 缓存时间
     * @param integer $diff 容错数值
     * @param integer $retry 容错次数
     * @return array [code, bgimg, water]
     */
    public static function render(string $image, int $time = 1800, int $diff = 10, int $retry = 3): array
    {
        $data = (new static($image))->create();
        $range = [$data['point'] - $diff, $data['point'] + $diff];
        $result = ['retry' => $retry, 'error' => 0, 'expire' => time() + $time, 'range' => $range];
        Library::$sapp->cache->set($code = CodeExtend::uniqidNumber(16, 'V'), $result, $time);
        return ['code' => $code, 'bgimg' => $data['bgimg'], 'water' => $data['water']];
    }

    /**
     * 在线验证是否通过
     * @param string $code 验证码编码
     * @param string $value 待验证数值
     * @param boolean $clear 验证成功清理
     * @return integer [ -1:需要刷新, 0:验证失败, 1:验证成功 ]
     */
    public static function verify(string $code, string $value, bool $clear = false): int
    {
        $cache = Library::$sapp->cache->get($code);
        if (empty($cache['range']) || empty($cache['retry'])) return -1;
        if ($cache['range'][0] <= $value && $value <= $cache['range'][1]) {
            $clear && Library::$sapp->cache->delete($code);
            return 1;
        }
        // 验证失败记录次数
        if (++$cache['error'] < $cache['retry']) {
            if (($tll = $cache['expire'] - time()) > 0) {
                Library::$sapp->cache->set($code, $cache, $tll);
                return 0;
            }
        }
        // 其他异常直接清空
        Library::$sapp->cache->delete($code);
        return -1;
    }

    /**
     * 剧中裁剪图片
     * @param string $image 图片资源
     * @param integer $width 目标宽度
     * @param integer $height 目标高度
     * @return \GdImage|resource
     */
    public static function cover(string $image, int $width, int $height)
    {
        // 读取缓存返回图片资源
        $local = LocalStorage::instance();
        $name = Storage::name(join('#', func_get_args()), 'png', 'cache');
        if ($local->has($name, true)) return imageCreateFromString($local->get($name, true));
        // 计算图片尺寸裁剪坐标
        [$w, $h] = getimagesize($image);
        if ($w > $h) {
            [$_sw, $_sh, $_sx, $_sy] = [$h, $h, intval(($w - $h) / 2), 0];
        } elseif ($w < $h) {
            [$_sw, $_sh, $_sx, $_sy] = [$w, $w, 0, intval(($h - $w) / 2)];
        } else {
            [$_sw, $_sh, $_sx, $_sy] = [$w, $h, 0, 0];
        }
        $newim = imageCreateTrueColor($width, $height);
        $srcim = imageCreateFromString(file_get_contents($image));
        imagecopyresampled($newim, $srcim, 0, 0, $_sx, $_sy, $width, $height, $_sw, $_sh);
        imagedestroy($srcim);
        // 缓存图片内容
        $file = $local->path($name, true);
        is_dir($path = dirname($file)) || mkdir($path, 0755, true);
        imagepng($newim, $file);
        // 返回新图片资源
        return $newim;
    }

    /**
     * 创建背景图和浮层图、浮层图X坐标
     * @return array [point, bgimg, water]
     */
    public function create(): array
    {
        // 创建目标背景图画布
        $dstim = $this->cover($this->srcImage, $this->dstWidth, $this->dstHeight);

        // 生成透明底浮层图画布
        $watim = imageCreateTrueColor($this->picWidth, $this->dstHeight);
        imageSaveAlpha($watim, true) && imageAlphaBlending($watim, false);
        imageFill($watim, 0, 0, imageColorAllocateAlpha($watim, 255, 255, 255, 127));

        // 随机位置
        $srcX1 = mt_rand(150, $this->dstWidth - $this->picWidth); // 水印位于大图X坐标
        $srcY1 = mt_rand(0, $this->dstHeight - $this->picHeight); // 水印位于大图Y坐标

        // 去除第二个干扰水印
        // do { // 干扰位置
        //     $srcX2 = mt_rand(100, $this->dstWidth - $this->picWidth);
        //     $srcY2 = mt_rand(0, $this->dstHeight - $this->picHeight);
        // } while (abs($srcX1 - $srcX2) < $this->picWidth);

        // 水印边框颜色
        $broders = [
            imageColorAllocateAlpha($dstim, 250, 100, 0, 50),
            imageColorAllocateAlpha($dstim, 250, 0, 100, 50),
            imageColorAllocateAlpha($dstim, 100, 0, 250, 50),
            imageColorAllocateAlpha($dstim, 100, 250, 0, 50),
            imageColorAllocateAlpha($dstim, 0, 250, 100, 50),
        ];
        shuffle($broders);
        $c1 = array_pop($broders);
        $c2 = array_pop($broders);
        $gray = imageColorAllocateAlpha($dstim, 0, 0, 0, 80);
        $blue = imageColorAllocateAlpha($watim, 0, 100, 250, 50);

        // 取原图像素颜色，生成浮层图
        $waters = $this->withWaterPoint();
        for ($i = 0; $i < $this->picHeight; $i++) {
            for ($j = 0; $j < $this->picWidth; $j++) {
                if ($waters[$i][$j] === 1) {
                    if (
                        empty($waters[$i - 1][$j - 1]) || empty($waters[$i - 2][$j - 2]) ||
                        empty($waters[$i + 1][$j + 1]) || empty($waters[$i + 2][$j + 2])
                    ) {
                        imagesetpixel($watim, $j, $srcY1 + $i, $blue);
                    } else {
                        imagesetpixel($watim, $j, $srcY1 + $i, ImageColorAt($dstim, $srcX1 + $j, $srcY1 + $i));
                    }
                }
            }
        }

        // 在原图挖坑，打上灰色水印
        for ($i = 0; $i < $this->picHeight; $i++) {
            for ($j = 0; $j < $this->picWidth; $j++) {
                if ($waters[$i][$j] === 1) {
                    if (
                        empty($waters[$i - 1][$j - 1]) ||
                        empty($waters[$i - 2][$j - 2]) ||
                        empty($waters[$i + 1][$j + 1]) ||
                        empty($waters[$i + 2][$j + 2])
                    ) {
                        imagesetpixel($dstim, $srcX1 + $j, $srcY1 + $i, $c1);
                        // 去除第二个干扰水印
                        // imagesetpixel($dstim, $srcX2 + $j, $srcY2 + $i, $c2);
                    } else {
                        imagesetpixel($dstim, $srcX1 + $j, $srcY1 + $i, $gray);
                        // 去除第二个干扰水印
                        // imagesetpixel($dstim, $srcX2 + $j, $srcY2 + $i, $gray);
                    }
                }
            }
        }

        // 获取背景图及浮层图内容
        [, , $bgimg] = [ob_start(), imagepng($dstim), ob_get_contents(), ob_end_clean(), imagedestroy($dstim)];
        [, , $water] = [ob_start(), imagepng($watim), ob_get_contents(), ob_end_clean(), imagedestroy($watim)];

        return [
            'point' => $srcX1,
            'bgimg' => 'data:image/png;base64,' . base64_encode($bgimg),
            'water' => 'data:image/png;base64,' . base64_encode($water)
        ];
    }

    /**
     * 计算水印矩阵坐标
     * @return void
     */
    private function withWaterPoint(): array
    {
        $waters = [];
        // 半径平方
        $dr = $this->r * $this->r;
        $lw = $this->r * 2 - 5;

        // 第一个圆中心点
        $c_1_x = $lw + ($this->picWidth - $lw * 2) / 2;
        $c_1_y = $this->r;

        // 第二个圆中心点
        $c_2_x = $this->picHeight - $this->r;
        $c_2_y = $lw + ($this->picHeight - ($lw) * 2) / 2;

        for ($i = 0; $i < $this->picHeight; $i++) {
            for ($j = 0; $j < $this->picWidth; $j++) {
                // 根据公式（x-a)² + (y-b)² = r² 算出像素是否在圆内
                $d1 = pow($j - $c_1_x, 2) + pow($i - $c_1_y, 2);
                $d2 = pow($j - $c_2_x, 2) + pow($i - $c_2_y, 2);
                if (($i >= $lw && $j >= $lw && $i <= $this->picHeight - $lw && $j <= $this->picWidth - $lw) || $d1 <= $dr || $d2 <= $dr) {
                    $waters[$i][$j] = 1;
                } else {
                    $waters[$i][$j] = 0;
                }
            }
        }

        return $waters;
    }
}
<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace think\admin\extend;

/**
 * Image 压缩工具
 * Class ImageExtend
 * @package think\admin\extend
 */
class ImageExtend
{
    private $src;
    private $image;
    private $imageinfo;
    private $percent = 0.5;

    /**
     * 图片压缩
     * @param string $src 源图
     * @param float $percent 压缩比例
     */
    public function __construct(string $src, float $percent = 1)
    {
        $this->src = $src;
        $this->percent = $percent;
    }

    /**
     * 高清压缩图片
     * @param string $saveName 提供图片名（可不带扩展名，用源图扩展名）用于保存。或不提供文件名直接显示
     * @return bool
     */
    public function compressImg(string $saveName = ''): bool
    {
        $this->_openImage();
        return empty($saveName) ? $this->_showImage() : $this->_saveImage($saveName);
    }

    /**
     * 内部：打开图片
     */
    private function _openImage()
    {
        [$width, $height, $type, $attr] = getimagesize($this->src);
        $this->imageinfo = [
            'width'  => $width,
            'height' => $height,
            'attr'   => $attr,
            'type'   => image_type_to_extension($type, false),
        ];
        $fun = "imagecreatefrom" . $this->imageinfo['type'];
        $this->image = $fun($this->src);
        $this->_thumpImage();
    }

    /**
     * 内部：操作图片
     */
    private function _thumpImage()
    {
        $newWidth = $this->imageinfo['width'] * $this->percent;
        $newHeight = $this->imageinfo['height'] * $this->percent;
        $imgThumps = imagecreatetruecolor($newWidth, $newHeight);
        // 将原图复制带图片载体上面，并且按照一定比例压缩，极大的保持了清晰度
        imagecopyresampled($imgThumps, $this->image, 0, 0, 0, 0, $newWidth, $newHeight, $this->imageinfo['width'], $this->imageinfo['height']);
        imagedestroy($this->image);
        $this->image = $imgThumps;
    }

    /**
     * 输出图片:保存图片则用 saveImage()
     * @return bool
     */
    private function _showImage(): bool
    {
        header('Content-Type: image/' . $this->imageinfo['type']);
        $funcs = "image" . $this->imageinfo['type'];
        $funcs($this->image);
        return true;
    }

    /**
     * 保存图片到硬盘：
     * @param string $dstImgName
     * @return bool
     */
    private function _saveImage(string $dstImgName): bool
    {
        if (empty($dstImgName)) return false;

        // 如果目标图片名有后缀就用目标图片扩展名 后缀，如果没有，则用源图的扩展名
        $allowImgs = ['.jpg', '.jpeg', '.png', '.bmp', '.wbmp', '.gif'];
        $dstExt = strrchr($dstImgName, ".");
        $sourseExt = strrchr($this->src, ".");
        if (!empty($dstExt)) $dstExt = strtolower($dstExt);
        if (!empty($sourseExt)) $sourseExt = strtolower($sourseExt);

        // 有指定目标名扩展名
        if (!empty($dstExt) && in_array($dstExt, $allowImgs)) {
            $dstName = $dstImgName;
        } elseif (!empty($sourseExt) && in_array($sourseExt, $allowImgs)) {
            $dstName = $dstImgName . $sourseExt;
        } else {
            $dstName = $dstImgName . $this->imageinfo['type'];
        }
        $funcs = "image" . $this->imageinfo['type'];
        return $funcs($this->image, $dstName);
    }

    /**
     * 销毁图片
     */
    public function __destruct()
    {
        imagedestroy($this->image);
    }
}
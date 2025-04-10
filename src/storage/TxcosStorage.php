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

namespace think\admin\storage;

use think\admin\contract\StorageInterface;
use think\admin\contract\StorageUsageTrait;
use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * 腾讯云COS存储支持
 * @class TxcosStorage
 * @package think\admin\storage
 */
class TxcosStorage implements StorageInterface
{

    use StorageUsageTrait;

    /**
     * 数据中心
     * @var string
     */
    private $point;

    /**
     * 存储空间名称
     * @var string
     */
    private $bucket;

    /**
     * $secretId
     * @var string
     */
    private $secretId;

    /**
     * secretKey
     * @var string
     */
    private $secretKey;

    /**
     * 初始化入口
     * @throws \think\admin\Exception
     */
    protected function init()
    {
        // 读取配置文件
        $this->point = sysconf('storage.txcos_point|raw');
        $this->bucket = sysconf('storage.txcos_bucket|raw');
        $this->secretId = sysconf('storage.txcos_access_key|raw');
        $this->secretKey = sysconf('storage.txcos_secret_key|raw');
        // 计算链接前缀
        $host = strtolower(sysconf('storage.txcos_http_domain|raw'));
        $type = strtolower(sysconf('storage.txcos_http_protocol|raw'));
        if ($type === 'auto') {
            $this->domain = "//{$host}";
        } elseif (in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$host}";
        } else {
            throw new Exception(lang('未配置腾讯云域名'));
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $data = $this->token($name) + ['key' => $name];
        if (is_string($attname) && strlen($attname) > 0) {
            $data['Content-Disposition'] = urlencode($attname);
        }
        $data['success_action_status'] = '200';
        $file = ['field' => 'file', 'name' => $name, 'content' => $file];
        if (is_numeric(stripos(HttpExtend::submit($this->upload(), $data, $file), '200 OK'))) {
            return ['file' => $this->path($name, $safe), 'url' => $this->url($name, $safe, $attname), 'key' => $name];
        } else {
            return [];
        }
    }

    /**
     * 读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        return Storage::curlGet($this->url($name, $safe));
    }

    /**
     * 删除存储文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        [$file] = explode('?', $name);
        $result = HttpExtend::request('DELETE', "https://{$this->bucket}.{$this->point}/{$file}", [
            'returnHeader' => true, 'headers' => $this->_sign('DELETE', $file),
        ]);
        return is_numeric(stripos($result, '204 No Content'));
    }

    /**
     * 判断是否存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        $file = $this->delSuffix($name);
        $result = HttpExtend::request('HEAD', "https://{$this->bucket}.{$this->point}/{$file}", [
            'returnHeader' => true, 'headers' => $this->_sign('HEAD', $name),
        ]);
        return is_numeric(stripos($result, 'HTTP/1.1 200 OK'));
    }

    /**
     * 获取访问地址
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return "{$this->domain}/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
    }

    /**
     * 获取存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->url($name, $safe);
    }

    /**
     * 获取文件信息
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param ?string $attname 下载名称
     * @return array
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        return $this->has($name, $safe) ? [
            'url' => $this->url($name, $safe, $attname),
            'key' => $name, 'file' => $this->path($name, $safe),
        ] : [];
    }

    /**
     * 获取上传地址
     * @return string
     */
    public function upload(): string
    {
        $proc = $this->app->request->isSsl() ? 'https' : 'http';
        return "{$proc}://{$this->bucket}.{$this->point}";
    }

    /**
     * 生成上传令牌
     * @param string $name 文件名称
     * @param integer $expires 有效时间
     * @param ?string $attname 下载名称
     * @return array
     */
    public function token(string $name, int $expires = 3600, ?string $attname = null): array
    {
        $startTimestamp = time();
        $endTimestamp = $startTimestamp + $expires;
        $keyTime = "{$startTimestamp};{$endTimestamp}";
        $siteurl = $this->url($name, false, $attname);
        $policy = json_encode([
            'expiration' => date('Y-m-d\TH:i:s.000\Z', $endTimestamp),
            'conditions' => [['q-ak' => $this->secretId], ['q-sign-time' => $keyTime], ['q-sign-algorithm' => 'sha1']],
        ]);
        return [
            'policy'      => base64_encode($policy), 'q-ak' => $this->secretId,
            'siteurl'     => $siteurl, 'q-key-time' => $keyTime, 'q-sign-algorithm' => 'sha1',
            'q-signature' => hash_hmac('sha1', sha1($policy), hash_hmac('sha1', $keyTime, $this->secretKey)),
        ];
    }

    /**
     * 生成请求签名
     * @param string $method 请求方式
     * @param string $soruce 资源名称
     * @return array
     */
    private function _sign(string $method, string $soruce): array
    {
        $header = [];
        // 1.生成 KeyTime
        $startTimestamp = time();
        $endTimestamp = $startTimestamp + 3600;
        $keyTime = "{$startTimestamp};{$endTimestamp}";
        // 2.生成 SignKey
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey);
        // 3.生成 UrlParamList, HttpParameters
        [$parse_url, $urlParamList, $httpParameters] = [parse_url($soruce), '', ''];
        if (!empty($parse_url['query'])) {
            parse_str($parse_url['query'], $params);
            uksort($params, 'strnatcasecmp');
            $urlParamList = join(';', array_keys($params));
            $httpParameters = http_build_query($params);
        }
        // 4.生成 HeaderList, HttpHeaders
        [$headerList, $httpHeaders] = ['', ''];
        if (!empty($header)) {
            uksort($header, 'strnatcasecmp');
            $headerList = join(';', array_keys($header));
            $httpHeaders = http_build_query($header);
        }
        // 5.生成 HttpString
        $httpString = strtolower($method) . "\n/{$parse_url['path']}\n{$httpParameters}\n{$httpHeaders}\n";
        // 6.生成 StringToSign
        $httpStringSha1 = sha1($httpString);
        $stringToSign = "sha1\n{$keyTime}\n{$httpStringSha1}\n";
        // 7.生成 Signature
        $signature = hash_hmac('sha1', $stringToSign, $signKey);
        // 8.生成签名
        $signArray = [
            'q-sign-algorithm' => 'sha1',
            'q-ak'             => $this->secretId,
            'q-sign-time'      => $keyTime,
            'q-key-time'       => $keyTime,
            'q-header-list'    => $headerList,
            'q-url-param-list' => $urlParamList,
            'q-signature'      => $signature,
        ];
        $header['Authorization'] = urldecode(http_build_query($signArray));
        foreach ($header as $key => $value) $header[$key] = ucfirst($key) . ": {$value}";
        return array_values($header);
    }

    /**
     * 获取存储区域
     * @return array
     */
    public static function region(): array
    {
        return [
            'cos.ap-beijing-1.myqcloud.com'     => lang('中国大陆 公有云地域 北京一区'),
            'cos.ap-beijing.myqcloud.com'       => lang('中国大陆 公有云地域 北京'),
            'cos.ap-nanjing.myqcloud.com'       => lang('中国大陆 公有云地域 南京'),
            'cos.ap-shanghai.myqcloud.com'      => lang('中国大陆 公有云地域 上海'),
            'cos.ap-guangzhou.myqcloud.com'     => lang('中国大陆 公有云地域 广州'),
            'cos.ap-chengdu.myqcloud.com'       => lang('中国大陆 公有云地域 成都'),
            'cos.ap-chongqing.myqcloud.com'     => lang('中国大陆 公有云地域 重庆'),
            'cos.ap-shenzhen-fsi.myqcloud.com'  => lang('中国大陆 金融云地域 深圳金融'),
            'cos.ap-shanghai-fsi.myqcloud.com'  => lang('中国大陆 金融云地域 上海金融'),
            'cos.ap-beijing-fsi.myqcloud.com'   => lang('中国大陆 金融云地域 北京金融'),
            'cos.ap-hongkong.myqcloud.com'      => lang('亚太地区 公有云地域 中国香港'),
            'cos.ap-singapore.myqcloud.com'     => lang('亚太地区 公有云地域 新加坡'),
            'cos.ap-mumbai.myqcloud.com'        => lang('亚太地区 公有云地域 孟买'),
            'cos.ap-jakarta.myqcloud.com'       => lang('亚太地区 公有云地域 雅加达'),
            'cos.ap-seoul.myqcloud.com'         => lang('亚太地区 公有云地域 首尔'),
            'cos.ap-bangkok.myqcloud.com'       => lang('亚太地区 公有云地域 曼谷'),
            'cos.ap-tokyo.myqcloud.com'         => lang('亚太地区 公有云地域 东京'),
            'cos.na-siliconvalley.myqcloud.com' => lang('北美地区 公有云地域 硅谷'),
            'cos.na-ashburn.myqcloud.com'       => lang('北美地区 公有云地域 弗吉尼亚'),
            'cos.na-toronto.myqcloud.com'       => lang('北美地区 公有云地域 多伦多'),
            'cos.sa-saopaulo.myqcloud.com'      => lang('北美地区 公有云地域 圣保罗'),
            'cos.eu-frankfurt.myqcloud.com'     => lang('欧洲地区 公有云地域 法兰克福'),
            'cos.eu-moscow.myqcloud.com'        => lang('欧洲地区 公有云地域 莫斯科'),
        ];
    }
}
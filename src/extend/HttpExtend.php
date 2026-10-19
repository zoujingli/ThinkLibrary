<?php

declare(strict_types=1);
/**
 * +----------------------------------------------------------------------
 * | ThinkAdmin Plugin for ThinkAdmin
 * +----------------------------------------------------------------------
 * | 版权所有 2014~2026 ThinkAdmin [ thinkadmin.top ]
 * +----------------------------------------------------------------------
 * | 官方网站: https://thinkadmin.top
 * +----------------------------------------------------------------------
 * | 开源协议 ( https://mit-license.org )
 * | 免责声明 ( https://thinkadmin.top/disclaimer )
 * | 会员特权 ( https://thinkadmin.top/vip-introduce )
 * +----------------------------------------------------------------------
 * | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
 * | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
 * +----------------------------------------------------------------------
 */

namespace think\admin\extend;

/**
 * CURL模拟请求扩展.
 * @class HttpExtend
 */
class HttpExtend
{
    /**
     * 以 GET 模拟网络请求
     * @param string $location HTTP请求地址
     * @param array|string $data GET请求参数
     * @param array $options CURL请求参数
     * @return bool|string
     */
    public static function get(string $location, $data = [], array $options = [])
    {
        $options['query'] = $data;
        return static::request('get', $location, $options);
    }

    /**
     * 以 POST 模拟网络请求
     * @param string $location HTTP请求地址
     * @param array|string $data POST请求数据
     * @param array $options CURL请求参数
     * @return bool|string
     */
    public static function post(string $location, $data = [], array $options = [])
    {
        $options['data'] = $data;
        return static::request('post', $location, $options);
    }

    /**
     * 以 FormData 模拟网络请求
     * @param string $url 模拟请求地址
     * @param array $data 模拟请求参数数据
     * @param array $file 提交文件 [field,name,type,content]
     * @param array $header 请求头部信息，默认带 Content-type
     * @param string $method 模拟请求的方式 [GET,POST,PUT]
     * @param bool $returnHeader 是否返回头部信息
     * @return bool|string
     */
    public static function submit(string $url, array $data = [], array $file = [], array $header = [], string $method = 'POST', bool $returnHeader = true)
    {
        [$line, $boundary] = [[], CodeExtend::random(18)];
        foreach ($data as $key => $value) {
            $line[] = "--{$boundary}";
            $line[] = "Content-Disposition: form-data; name=\"{$key}\"";
            $line[] = '';
            $line[] = $value;
        }
        if (is_array($file) && isset($file['field'], $file['name'])) {
            $line[] = "--{$boundary}";
            $line[] = "Content-Disposition: form-data; name=\"{$file['field']}\"; filename=\"{$file['name']}\"";
            if (isset($file['type'])) {
                $line[] = "Content-Type: \"{$file['type']}\"";
            }
            $line[] = '';
            $line[] = $file['content'];
        }
        $line[] = "--{$boundary}--";
        $header[] = "Content-type:multipart/form-data;boundary={$boundary}";
        return static::request($method, $url, ['data' => join("\r\n", $line), 'returnHeader' => $returnHeader, 'headers' => $header]);
    }

    /**
     * 以 CURL 模拟网络请求；传输失败返回 false，配置或回调异常释放句柄后原样传递.
     * @param string $method 模拟请求方式
     * @param string $location 模拟请求地址
     * @param array $options 请求参数[headers,query,data,cookie,cookie_file,timeout,returnHeader]
     * @return bool|string
     */
    public static function request(string $method, string $location, array $options = [])
    {
        $curl = curl_init();
        try {
            static::configureCurl($curl, $method, $location, $options);
            return curl_exec($curl);
        } finally {
            // PHP 8 起句柄是自动释放的对象；8.5 已弃用无作用的 curl_close。
            if (PHP_VERSION_ID < 80000) {
                curl_close($curl);
            }
        }
    }

    /**
     * 并发请求，保留输入键和顺序；传输失败为 false，HTTP 错误仍返回响应内容.
     * @param array $requests 请求列表，每项包含 url、可选 method（默认 GET）和 options
     * @param int $concurrency 同时执行上限，单项超时从开始执行时计算
     * @return array 每项返回 string|false，空响应保留为空字符串
     * @throws \InvalidArgumentException 请求格式或并发上限无效
     * @throws \RuntimeException 多请求调度失败；配置或回调异常原样传递，始终释放句柄
     */
    public static function multiRequest(array $requests, int $concurrency = 10): array
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('HTTP 并发上限必须大于零');
        }
        foreach ($requests as $request) {
            if (!is_array($request) || !isset($request['url']) || !is_string($request['url'])
                || (isset($request['method']) && !is_string($request['method']))
                || (isset($request['options']) && !is_array($request['options']))) {
                throw new \InvalidArgumentException('HTTP 并发请求格式无效');
            }
        }
        $results = array_fill_keys(array_keys($requests), false);
        if (!$requests) {
            return $results;
        }
        $multi = curl_multi_init();
        $handles = [];
        $keys = array_keys($requests);
        $offset = 0;
        try {
            do {
                while ($offset < count($keys) && count($handles) < $concurrency) {
                    $key = $keys[$offset++];
                    $request = $requests[$key];
                    $handles[$key] = curl_init();
                    static::configureCurl($handles[$key], $request['method'] ?? 'GET', $request['url'], $request['options'] ?? []);
                    if (curl_multi_add_handle($multi, $handles[$key]) !== CURLM_OK) {
                        throw new \RuntimeException('无法加入 HTTP 并发请求');
                    }
                }
                do {
                    $status = curl_multi_exec($multi, $running);
                } while ($status === CURLM_CALL_MULTI_PERFORM);
                if ($status !== CURLM_OK) {
                    throw new \RuntimeException('HTTP 并发调度失败：' . curl_multi_strerror($status));
                }
                while ($info = curl_multi_info_read($multi)) {
                    foreach ($handles as $key => $curl) {
                        if ($curl === $info['handle']) {
                            $results[$key] = $info['result'] === CURLE_OK ? curl_multi_getcontent($curl) : false;
                            curl_multi_remove_handle($multi, $curl);
                            if (PHP_VERSION_ID < 80000) {
                                curl_close($curl);
                            }
                            unset($handles[$key]);
                            break;
                        }
                    }
                }
                unset($info, $curl);
                if ($handles && (count($handles) >= $concurrency || $offset >= count($keys))) {
                    if (curl_multi_select($multi, 1.0) === -1) {
                        usleep(1000);
                    }
                }
            } while ($handles || $offset < count($keys));
            return $results;
        } finally {
            foreach ($handles as $curl) {
                curl_multi_remove_handle($multi, $curl);
                if (PHP_VERSION_ID < 80000) {
                    curl_close($curl);
                }
            }
            curl_multi_close($multi);
        }
    }

    /**
     * 复用单请求配置，保持查询、表单、文件、Cookie 与自定义 cURL 参数行为.
     * @param \CurlHandle|resource $curl 请求句柄
     * @param string $method 请求方式
     * @param string $location 请求地址
     * @param array $options 请求配置，配置异常由调用方释放句柄后继续传递
     */
    private static function configureCurl($curl, string $method, string $location, array $options): void
    {
        // GET 参数设置
        if (!empty($options['query'])) {
            $location .= strpos($location, '?') !== false ? '&' : '?';
            if (is_array($options['query'])) {
                $location .= http_build_query($options['query']);
            } elseif (is_string($options['query'])) {
                $location .= $options['query'];
            }
        }
        // Agent 代理设置
        curl_setopt($curl, CURLOPT_USERAGENT, $options['agent'] ?? static::getUserAgent());
        // Cookie 信息设置
        if (!empty($options['cookie'])) {
            curl_setopt($curl, CURLOPT_COOKIE, $options['cookie']);
        }
        // Header 头信息设置
        if (!empty($options['headers'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['headers']);
        }
        if (!empty($options['cookie_file'])) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $options['cookie_file']);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $options['cookie_file']);
        }
        // 设置请求方式
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if (strtolower($method) === 'head') {
            curl_setopt($curl, CURLOPT_NOBODY, 1);
        } elseif (isset($options['data'])) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['data']);
        }
        // 请求超时设置
        if (isset($options['timeout']) && is_numeric($options['timeout'])) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $options['timeout']);
        } else {
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        }
        // 是否返回前部内容
        if (empty($options['returnHeader'])) {
            curl_setopt($curl, CURLOPT_HEADER, false);
        } else {
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        }
        // 自定义扩展参数配置，二维数组内每个单元为一个设置语句，格式如下：
        // $setopt = [ [CURLOPT_URL, $location], [CURLOPT_AUTOREFERER, true], ...];
        if (isset($options['setopt']) && is_array($options['setopt'])) {
            foreach ($options['setopt'] as $value) {
                if (is_array($value)) {
                    curl_setopt($curl, ...$value);
                }
            }
        }
        curl_setopt($curl, CURLOPT_URL, $location);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }

    /**
     * 获取浏览器代理信息.
     */
    private static function getUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11',
            'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
            'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729; InfoPath.3; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11',
        ];
        return $agents[array_rand($agents)];
    }
}

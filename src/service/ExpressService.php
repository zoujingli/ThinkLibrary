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

use think\admin\extend\CodeExtend;
use think\admin\extend\HttpExtend;
use think\admin\Service;

/**
 * 百度快递100物流查询
 * @class ExpressService
 * @deprecated 独立封装为插件
 * @package think\admin\service
 */
class ExpressService extends Service
{

    /**
     * 网络请求参数
     * @var array
     */
    protected $options = [];

    /**
     * 公司编码别名
     * @var array
     */
    protected $codes = [
        'YD'   => 'yunda',
        'SF'   => 'shunfeng',
        'UC'   => 'youshuwuliu',
        'YTO'  => 'yuantong',
        'STO'  => 'shentong',
        'ZTO'  => 'zhongtong',
        'ZJS'  => 'zhaijisong',
        'DBL'  => 'debangwuliu',
        'HHTT' => 'tiantian',
        'HTKY' => 'huitongkuaidi',
        'YZPY' => 'youzhengguonei',
    ];

    /**
     * 快递服务初始化
     * @return $this
     */
    protected function initialize(): ExpressService
    {
        // 获取当前请求 IP 地址
        $clentip = $this->app->request->ip();
        if (empty($clentip) || $clentip === '0.0.0.0') {
            $clentip = join('.', [rand(1, 254), rand(1, 254), rand(1, 254), rand(1, 254)]);
        }
        // 创建 CURL 请求模拟参数
        $this->options['cookie_file'] = syspath('runtime/.cok');
        $this->options['headers'] = ['Host:express.baidu.com', "CLIENT-IP:{$clentip}", "X-FORWARDED-FOR:{$clentip}"];
        return $this;
    }

    /**
     * 通过百度快递100应用查询物流信息
     * @param string $code 快递公司编辑
     * @param string $number 快递物流编号
     * @param array $list 快递路径列表
     * @return array
     */
    public function express(string $code, string $number, array $list = []): array
    {
        // 新状态：1-新订单,2-在途中,3-签收,4-问题件
        // 原状态：0-在途，1-揽收，2-疑难，3-签收，4-退签，5-派件，6-退回，7-转投，8-清关，14-拒签
        $ckey = md5("{$code}{$number}");
        $cache = $this->app->cache->get($ckey, []);
        $message = [1 => '新订单', 2 => '在途中', 3 => '签收', 4 => '问题件'];
        if (!empty($cache)) return $cache;
        for ($i = 0; $i < 6; $i++) if (is_array($result = $this->doExpress($code, $number))) {
            if (isset($result['data']['info']['context']) && isset($result['data']['info']['state'])) {
                $state = intval($result['data']['info']['state']);
                $status = in_array($state, [0, 1, 5, 7, 8]) ? 2 : ($state === 3 ? 3 : 4);
                foreach ($result['data']['info']['context'] as $vo) $list[] = ['time' => date('Y-m-d H:i:s', intval($vo['time'])), 'context' => $vo['desc']];
                $result = ['message' => lang($message[$status] ?? $result['msg']), 'status' => $status, 'express' => $code, 'number' => $number, 'data' => $list];
                $this->app->cache->set($ckey, $result, 30);
                return $result;
            }
        }
        return ['message' => lang('暂无轨迹信息~'), 'status' => 1, 'express' => $code, 'number' => $number, 'data' => $list];
    }

    /**
     * 获取快递公司列表
     * @return array
     */
    public function getExpressList(): array
    {
        return $this->getQueryData(2);
    }

    /**
     * 执行百度快递100应用查询请求
     * @param string $code 快递公司编号
     * @param string $number 快递单单号
     * @return mixed
     */
    private function doExpress(string $code, string $number)
    {
        [$code, $qqid] = [$this->codes[$code] ?? $code, CodeExtend::uniqidNumber(19, '7740')];
        $url = "{$this->getQueryData(1)}&appid=4001&nu={$number}&com={$code}&qid={$qqid}&new_need_di=1&source_xcx=0&vcode=&token=&sourceId=4155";
        $result = json_decode(trim(HttpExtend::get($url, [], $this->options)), true);
        if (!empty($result['status']) || !empty($result['error_code'])) {
            @unlink($this->options['cookie_file']);
            $this->app->cache->delete('express_kuaidi_uri');
            $this->app->cache->delete('express_kuaidi_com');
        }
        return $result;
    }

    /**
     * 获取快递查询接口
     * @param integer $type 类型数据
     * @return string|array
     */
    private function getQueryData(int $type)
    {
        $times = 0;
        $expressUri = $this->app->cache->get('express_kuaidi_uri', '');
        if ($type == 1 && !empty($expressUri)) return $expressUri;
        $expressCom = $this->app->cache->get('express_kuaidi_com', []);
        if ($type === 2 && !empty($expressCom)) return $expressCom;
        while (true) {
            if ($times++ >= 10) {
                $times = 0;
                @unlink($this->options['cookie_file']);
            }
            [$ts, $input] = [mt_rand(2000000, 2900000), CodeExtend::random(5)];
            $content = HttpExtend::get("https://m.baidu.com/s?word=快递查询&ts={$ts}&t_kt=0&ie=utf-8&rsv_iqid=&rsv_t=&sa=&rsv_pq=&rsv_sug4=&tj=1&inputT={$input}&sugid=&ss=", [], $this->options);
            if (preg_match('#"(expSearchApi|checkExpUrl)":"(.*?)"#i', $content, $matches)) {
                $this->app->cache->set('express_kuaidi_uri', $expressUri = $matches[2], 3600);
                if (preg_match('#"text":"快递查询","option":.*?(\[.*?]).*?#i', $content, $items)) {
                    $attr = json_decode($items[1], true);
                    $expressCom = array_combine(array_column($attr, 'value'), array_column($attr, 'text'));
                    $this->app->cache->set('express_kuaidi_com', $expressCom, 3600);
                    if ($type === 2) return $expressCom;
                }
                if ($type === 1) return $expressUri;
            } else usleep(100000);
        }
    }
}
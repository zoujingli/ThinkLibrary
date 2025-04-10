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

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\FaviconExtend;
use think\admin\Helper;
use think\admin\Library;
use think\admin\model\SystemConfig;
use think\admin\model\SystemData;
use think\admin\model\SystemOplog;
use think\admin\Service;
use think\admin\Storage;
use think\admin\storage\LocalStorage;
use think\App;
use think\db\Query;
use think\Model;

/**
 * 系统参数管理服务
 * @class SystemService
 * @package think\admin\service
 *
 * @method static bool isDebug() 调式模式运行
 * @method static bool isOnline() 产品模式运行
 *
 * 运行环境配置
 * @method static array getRuntime(?string $name = null, array $default = []) 获取动态配置
 * @method static bool setRuntime(?string $mode = null, ?array $appmap = [], ?array $domain = []) 设置动态配置
 * @method static bool bindRuntime(array $data = []) 绑定动态配置
 *
 * 运行缓存管理
 * @method static bool pushRuntime() 压缩发布项目
 * @method static bool clearRuntime() 清理运行缓存
 * @method static bool checkRunMode(string $type = 'dev') 判断运行环境
 *
 * 初始化启动系统
 * @method static mixed doInit(?App $app = null) 初始化主程序
 * @method static mixed doConsoleInit(?App $app = null) 初始化命令行
 */
class SystemService extends Service
{
    /**
     * 生成静态路径链接
     * @param string $path 后缀路径
     * @param ?string $type 路径类型
     * @param mixed $default 默认数据
     * @return string|array
     */
    public static function uri(string $path = '', ?string $type = '__ROOT__', $default = '')
    {
        $plugin = Library::$sapp->http->getName();
        if (strlen($path)) $path = '/' . ltrim($path, '/');
        $prefix = rtrim(dirname(Library::$sapp->request->basefile()), '\\/');
        $data = [
            '__APP__'  => rtrim(url('@')->build(), '\\/') . $path,
            '__ROOT__' => $prefix . $path,
            '__PLUG__' => "{$prefix}/static/extra/{$plugin}{$path}",
            '__FULL__' => Library::$sapp->request->domain() . $prefix . $path
        ];
        return is_null($type) ? $data : ($data[$type] ?? $default);
    }

    /**
     * 生成全部静态路径
     * @param string $path
     * @return string[]
     */
    public static function uris(string $path = ''): array
    {
        return static::uri($path, null);
    }

    /**
     * 设置配置数据
     * @param string $name 配置名称
     * @param mixed $value 配置内容
     * @return integer|string
     * @throws \think\admin\Exception
     */
    public static function set(string $name, $value = '')
    {
        [$type, $field] = static::_parse($name);
        if (is_array($value)) {
            $count = 0;
            foreach ($value as $kk => $vv) {
                $count += static::set("{$field}.{$kk}", $vv);
            }
            return $count;
        } else try {
            $map = ['type' => $type, 'name' => $field];
            SystemConfig::mk()->master()->where($map)->findOrEmpty()->save(array_merge($map, ['value' => $value]));
            sysvar('think.admin.config', []);
            Library::$sapp->cache->delete('SystemConfig');
            return 1;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 读取配置数据
     * @param string $name
     * @param string $default
     * @return array|mixed|string
     * @throws \think\admin\Exception
     */
    public static function get(string $name = '', string $default = '')
    {
        try {
            if (empty($config = sysvar($keys = 'think.admin.config') ?: [])) {
                SystemConfig::mk()->cache('SystemConfig')->select()->map(function ($item) use (&$config) {
                    $config[$item['type']][$item['name']] = $item['value'];
                });
                sysvar($keys, $config);
            }
            [$type, $field, $outer] = static::_parse($name);
            if (empty($name)) {
                return $config;
            } elseif (isset($config[$type])) {
                $group = $config[$type];
                if ($outer !== 'raw') foreach ($group as $kk => $vo) {
                    $group[$kk] = htmlspecialchars(strval($vo));
                }
                return $field ? ($group[$field] ?? $default) : $group;
            } else {
                return $default;
            }
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 数据增量保存
     * @param Model|Query|string $query 数据查询对象
     * @param array $data 需要保存的数据，成功返回对应模型
     * @param string $key 更新条件查询主键
     * @param mixed $map 额外更新查询条件
     * @return boolean|integer 失败返回 false, 成功返回主键值或 true
     * @throws \think\admin\Exception
     */
    public static function save($query, array &$data, string $key = 'id', $map = [])
    {
        try {
            $query = Helper::buildQuery($query)->master()->strict(false);
            if (empty($map[$key])) $query->where([$key => $data[$key] ?? null]);
            $model = $query->where($map)->findOrEmpty();
            // 当前操作方法描述
            $action = $model->isExists() ? 'onAdminUpdate' : 'onAdminInsert';
            // 写入或更新模型数据
            if ($model->save($data) === false) return false;
            // 模型自定义事件回调
            if ($model instanceof \think\admin\Model) {
                $model->$action(strval($model->getAttr($key)));
            }
            $data = $model->toArray();
            return $model[$key] ?? true;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 批量更新保存数据
     * @param Model|Query|string $query 数据查询对象
     * @param array $data 需要保存的数据，成功返回对应模型
     * @param string $key 更新条件查询主键
     * @param mixed $map 额外更新查询条件
     * @return boolean|integer 失败返回 false, 成功返回主键值或 true
     * @throws \think\admin\Exception
     */
    public static function update($query, array $data, string $key = 'id', $map = [])
    {
        try {
            $query = Helper::buildQuery($query)->master()->where($map);
            if (empty($map[$key])) $query->where([$key => $data[$key] ?? null]);
            return (clone $query)->count() > 1 ? $query->strict(false)->update($data) : $query->findOrEmpty()->save($data);
        } catch (\Exception|\Throwable $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 解析缓存名称
     * @param string $rule 配置名称
     * @return array
     */
    private static function _parse(string $rule): array
    {
        $type = 'base';
        if (stripos($rule, '.') !== false) {
            [$type, $rule] = explode('.', $rule, 2);
        }
        [$field, $outer] = explode('|', "{$rule}|");
        return [$type, $field, strtolower($outer)];
    }

    /**
     * 获取数据库所有数据表
     * @return array [table, total, count]
     */
    public static function getTables(): array
    {
        $tables = Library::$sapp->db->getTables();
        return [$tables, count($tables), 0];
    }

    /**
     * 复制并创建表结构
     * @param string $from 来源表名
     * @param string $create 创建表名
     * @param array $tables 现有表集合
     * @param boolean $copy 是否复制
     * @param mixed $where 复制条件
     * @throws \think\admin\Exception
     */
    public static function copyTableStruct(string $from, string $create, array $tables = [], bool $copy = false, $where = [])
    {
        try {
            if (empty($tables)) [$tables] = static::getTables();
            if (!in_array($from, $tables)) {
                throw new Exception("待复制的数据表 {$from} 不存在！");
            }
            if (!in_array($create, $tables)) {
                Library::$sapp->db->connect()->query("CREATE TABLE IF NOT EXISTS {$create} (LIKE {$from})");
                if ($copy) {
                    $sql1 = Library::$sapp->db->name($from)->where($where)->buildSql(false);
                    Library::$sapp->db->connect()->query("INSERT INTO {$create} {$sql1}");
                }
            }
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 保存数据内容
     * @param string $name 数据名称
     * @param mixed $value 数据内容
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function setData(string $name, $value): bool
    {
        try {
            $data = ['name' => $name, 'value' => json_encode([$value], 64 | 256)];
            return SystemData::mk()->where(['name' => $name])->findOrEmpty()->save($data);
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 读取数据内容
     * @param string $name 数据名称
     * @param mixed $default 默认内容
     * @return mixed
     */
    public static function getData(string $name, $default = [])
    {
        try {
            // 读取原始序列化或JSON数据
            $value = SystemData::mk()->where(['name' => $name])->value('value');
            if (is_null($value)) return $default;
            if (is_string($value) && strpos($value, '[') === 0) {
                return json_decode($value, true)[0];
            }
        } catch (\Exception $exception) {
            trace_file($exception);
            return $default;
        }
        try {
            // 尝试正常反序列解析
            return unserialize($value);
        } catch (\Exception $exception) {
            trace_file($exception);
        }
        try {
            // 尝试修复反序列解析
            $unit = 'i:\d+;|b:[01];|s:\d+:".*?";|O:\d+:".*?":\d+:\{';
            $preg = '/(?=^|' . $unit . ')s:(\d+):"(.*?)";(?=' . $unit . '|}+$)/';
            return unserialize(preg_replace_callback($preg, static function ($attr) {
                return sprintf('s:%d:"%s";', strlen($attr[2]), $attr[2]);
            }, $value));
        } catch (\Exception $exception) {
            trace_file($exception);
            return $default;
        }
    }

    /**
     * 写入系统日志内容
     * @param string $action
     * @param string $content
     * @return boolean
     */
    public static function setOplog(string $action, string $content): bool
    {
        return SystemOplog::mk()->save(static::getOplog($action, $content)) !== false;
    }

    /**
     * 获取系统日志内容
     * @param string $action
     * @param string $content
     * @return array
     */
    public static function getOplog(string $action, string $content): array
    {
        return [
            'node'      => NodeService::getCurrent(),
            'action'    => lang($action), 'content' => lang($content),
            'geoip'     => Library::$sapp->request->ip() ?: '127.0.0.1',
            'username'  => AdminService::getUserName() ?: '-',
            'create_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string|null $file 文件名称
     * @return false|int
     */
    public static function putDebug($data, bool $new = false, ?string $file = null)
    {
        ob_start();
        var_dump($data);
        $output = preg_replace('/]=>\n(\s+)/m', '] => ', ob_get_clean());
        if (is_null($file)) $file = syspath('runtime/' . date('Ymd') . '.log');
        else if (!preg_match('#[/\\\\]+#', $file)) $file = syspath("runtime/{$file}.log");
        is_dir($dir = dirname($file)) or mkdir($dir, 0777, true);
        return $new ? file_put_contents($file, $output) : file_put_contents($file, $output, FILE_APPEND);
    }

    /**
     * 设置网页标签图标
     * @param ?string $icon 网页标签图标
     * @return boolean
     * @throws \think\admin\Exception
     */
    public static function setFavicon(?string $icon = null): bool
    {
        try {
            $icon = $icon ?: sysconf('base.site_icon|raw');
            if (!preg_match('#^https?://#i', $icon)) {
                throw new Exception(lang('无效的原文件地址！'));
            }
            if (preg_match('#/upload/(\w{2}/\w{30}.\w+)$#i', $icon, $vars)) {
                $info = LocalStorage::instance()->info($vars[1]);
            }
            if (empty($info) || empty($info['file'])) {
                $name = Storage::name($icon, 'tmp', 'icon');
                $info = LocalStorage::instance()->set($name, Storage::curlGet($icon), true);
            }
            if (empty($info) || empty($info['file'])) return false;
            $favicon = new FaviconExtend($info['file'], [48, 48]);
            return $favicon->saveIco(syspath('public/favicon.ico'));
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 魔术方法调用(临时)
     * @param string $method 方法名称
     * @param array $arguments 调用参数
     * @return mixed
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        return static::__callStatic($method, $arguments);
    }

    /**
     * 静态方法兼容(临时)
     * @param string $method 方法名称
     * @param array $arguments 调用参数
     * @return mixed
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $map = [
            'setRuntime'    => 'set',
            'getRuntime'    => 'get',
            'bindRuntime'   => 'apply',
            'isDebug'       => 'isDebug',
            'isOnline'      => 'isOnline',
            'doInit'        => 'doWebsiteInit',
            'doConsoleInit' => 'doConsoleInit',
            'pushRuntime'   => 'push',
            'clearRuntime'  => 'clear',
            'checkRunMode'  => 'check',
        ];
        switch (strtolower($method)) {
            case 'setconfig':
                return self::setData(...$arguments);
            case 'getconfig':
                return self::getData(...$arguments);
        }
        if (isset($map[$method])) {
            return RuntimeService::{$map[$method]}(...$arguments);
        } else {
            throw new Exception("method not exists: RuntimeService::{$method}()");
        }
    }
}


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

use Phinx\Db\Adapter\MysqlAdapter;

/**
 * 旧版 Phinx 的 MySQL 列定义兼容层.
 * 由 PhinxSchema 为缺少 Literal 或列排序规则接口的版本创建，普通列沿用父适配器.
 * @class PhinxMysqlColumns
 * @internal
 */
class PhinxMysqlColumns extends MysqlAdapter
{
    use PhinxLegacyColumns;
}

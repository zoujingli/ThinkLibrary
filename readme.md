# ThinkLibrary for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-library/v/stable)](https://packagist.org/packages/zoujingli/think-library)
[![Latest Unstable Version](https://poser.pugx.org/zoujingli/think-library/v/unstable)](https://packagist.org/packages/zoujingli/think-library)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-library/downloads)](https://packagist.org/packages/zoujingli/think-library)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-library/d/monthly)](https://packagist.org/packages/zoujingli/think-library)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-library/d/daily)](https://packagist.org/packages/zoujingli/think-library)
[![PHP Version](https://thinkadmin.top/static/icon/php-7.1.svg)](https://thinkadmin.top)
[![License](https://thinkadmin.top/static/icon/license-mit.svg)](https://mit-license.org)

**ThinkLibrary** 是 **ThinkAdmin** 面向 **ThinkPHP 6 & 8** 的核心封装库，提供控制器、模型、服务、队列、存储、配置、HTTP、JWT 和常用数据处理能力。

## 业务功能特性

**核心架构功能：**
- **标准控制器基类**: 提供完整的 CRUD 操作封装，包括分页、表单、验证、队列等通用功能
- **基础模型类**: 实现魔术方法和静态助手调用，支持操作日志记录和数据一致性保障
- **自定义服务基类**: 提供依赖注入和实例化机制，支持服务的统一管理和扩展
- **全局函数库**: 包含数据处理、系统配置、HTTP 请求、JWT 认证等实用函数
- **快捷查询逻辑器**: 提供链式查询构建，简化数据库操作
- **表单验证机制**: 支持规则别名和自定义验证，确保数据输入安全
- **异步任务队列**: 支持延时执行和循环任务，提升系统响应性能
- **CSRF 令牌验证**: 内置表单安全验证，防止跨站请求伪造攻击
- **高精度计算支持**: 集成 BC Math 高精度数学函数，确保金融计算的准确性

**技术特性：**
- **PSR-12 标准**: 严格遵循 PHP-FIG 编码规范，确保代码质量和可维护性
- **模块化设计**: 各功能模块独立封装，便于扩展和维护
- **异常处理完善**: 完善的异常捕获和日志记录机制，便于问题排查
- **向后兼容**: 保持 API 稳定性，确保平滑升级

## 功能说明

1. **数据列表展示组件**

* 功能：展示数据列表，支持分页、排序和高级搜索。
* 优化点：提供友好的用户界面和交互体验，确保数据展示的准确性和实时性。
* 高级特性：支持多种排序方式、自定义搜索字段和条件。

2. **表单处理模块**

* 功能：用于创建、展示和提交表单数据。
* 优化点：表单验证和错误处理机制，确保数据的有效性和完整性。
* 高级特性：支持多种表单元素、表单验证规则和动态表单生成。

3. **数据状态快速处理模块**

* 功能：根据业务需求快速更新数据状态。
* 优化点：提供简洁的接口和操作方式，支持多字段同时更新。
* 高级特性：支持条件判断和事务处理，确保数据一致性和完整性。

4. **数据安全删除模块**

* 功能：根据业务需求安全地删除数据。
* 优化点：提供软删除和硬删除两种方式，确保数据彻底消失或标记为已删除。
* 高级特性：支持根据条件自动软删除、可配置的软删除标记字段。

5. **文件存储通用组件**

* 功能：支持多种文件存储方式，包括本地服务存储、云存储等。
* 优化点：提供统一的接口和配置方式，方便开发者快速集成和使用。
* 高级特性：支持文件上传、下载、删除和版本控制等功能。

6. **通用数据保存更新模块**

* 功能：根据 key 值及 where 条件判断数据是否存在，进行更新或新增操作。
* 优化点：提供简洁的接口和操作方式，减少冗余代码和重复工作量。
* 高级特性：支持乐观锁和悲观锁机制，确保并发控制和数据一致性。

7. **通用网络请求模块**

* 功能：支持 GET、POST 和 PUT 请求，可配置请求参数、证书等。
* 优化点：提供统一的接口和配置方式，方便开发者快速发起网络请求。
* 高级特性：支持请求重试、超时设置、自动捕获异常等功能。

8. **系统参数通用 g-k-v 配置模块**

* 功能：快速配置系统参数，支持长久化保存。
* 优化点：提供简洁的接口和操作方式，方便开发者管理和维护系统参数。
* 高级特性：支持参数加密存储、权限控制和日志记录等功能。

9. **UTF8 加密算法支持模块**

* 功能：提供 UTF8 字符串的加密和解密功能。
* 优化点：确保加密过程的安全性和数据的机密性。
* 高级特性：支持多种加密算法、密钥管理等功能。

10. **接口 CORS 跨域默认支持模块**

* 功能：默认支持跨域请求，输出标准化 JSON 数据。
* 优化点：减少开发者的工作量，自动处理跨域问题。
* 高级特性：支持定制化响应头、跨域请求限制等功能。

11. **表单 CSRF 安全验证模块**

* 功能：自动为表单添加 CSRF 安全验证字段，防止恶意提交。
* 优化点：简化开发者的工作流程，提高表单提交的安全性。
* 高级特性：支持自定义验证规则、多种验证方式等功能。

## 参考项目

#### ThinkAdmin - V6

* Gitee 仓库 https://gitee.com/zoujingli/ThinkAdmin
* Github 仓库 https://github.com/zoujingli/ThinkAdmin
* Gitcode 仓库 https://gitcode.com/ThinkAdmin/ThinkAdmin
* 体验地址 ( 账号密码都是 admin ) https://v6.thinkadmin.top

## 代码仓库

**ThinkLibrary** 遵循 **MIT** 开源协议发布，并免费提供使用。

部分代码来自互联网，若有异议可以联系作者进行删除。

* 在线体验地址：https://v6.thinkadmin.top ( 账号和密码都是 `admin` )
* **Gitee** 仓库地址：https://gitee.com/zoujingli/ThinkLibrary
* **Github** 仓库地址：https://github.com/zoujingli/ThinkLibrary
* **Gitcode** 仓库地址： https://gitcode.com/ThinkAdmin/ThinkLibrary

版权所有 Copyright © 2014-2026 by ThinkAdmin (https://thinkadmin.top) All rights reserved。

## 数据库安装包兼容性

`php think xadmin:package` 可从 MySQL 或 SQLite 生成结构及数据安装包。字段优先使用 `integer`、`string`、`text`、`binary`、`float`、`decimal`，日期时间及 JSON 按实际用途保留。MySQL 的整数宽度、长文本、二进制等原始定义通过选项保存，在 MySQL 上恢复时保持原有类型；SQLite 使用对应通用类型，不依赖不同版本 Phinx 的长度常量。

MySQL 的 `ENUM` / `SET`、`BIT`、`YEAR`、`ZEROFILL`、`ON UPDATE` 及全文/前缀等专有索引不能直接转换到 SQLite，迁移会在操作对应表之前报错。SQLite 的部分索引、表达式索引、生成列及 `CHECK`、外键、`ON CONFLICT`、`STRICT`、`WITHOUT ROWID` 等尚不支持导出的结构也会明确报错。`--force` 会更新字段和同名索引，保留其他字段及索引；已有表的主键不同或 SQLite 表被外键引用时停止，需要另写专用迁移。SQLite 升级失败会回滚，重建表保留原有触发器和自增序号。

安装包始终使用迁移配置的连接和表前缀。新版逐行备份支持二进制、空值和大整数，并兼容读取旧 JSONL 数据文件。恢复到 SQLite 整数列时，超出 64 位有符号整数范围的记录会明确报错并回滚本次恢复，避免自动转为浮点数丢失精度。SQLite 的时间默认表达式与同名字符串、主键字段的可空属性分别保留。更新后请重新生成原来的安装包；运行新生成的脚本也需要同步更新 ThinkLibrary。生成或写入失败时保留同类旧脚本及备份，成功发布后才替换。

打包忽略名单同时接受带前缀的物理表名和不带前缀的逻辑表名。迁移记录表始终排除；未配置忽略名单时，数据备份默认排除任务队列与操作日志，带表前缀时同样生效。

SQLite 数据备份使用 v2 记录每个值的实际存储类型，保留 TEXT 列中的 BLOB、BLOB 列中的文本与数值以及浮点精度；读取时仍兼容 v1 和原 JSONL。`INT`、`BIGINT` 等主键不会被改成有自动编号语义的 `INTEGER PRIMARY KEY`。MySQL 备份在读取日期时间字段时保留小数秒，兼容 PHP 7.2 的旧驱动。MySQL 8 的文本常量默认值和 `CURRENT_TIMESTAMP` 表达式可再次导出，其他未支持的默认表达式仍会明确报错。

SQLite 的原字段声明通过 `sqlite_type` 保留，避免 `DATETIME`、`JSON` 等字段恢复后改变存储类型与排序行为；浮点字段迁移到 MySQL 时使用 `DOUBLE` 保留双精度。旧版 Phinx 的普通列继续使用原适配器编译，仅对原始类型、精度、默认表达式及非空约束等缺失能力补充兼容处理。

SQLite 自增表的数据文件首行另存历史序号，即使记录已全部删除也会打包；恢复时不会降低目标表已有序号。无序号头的旧备份仍可读取，含序号头的新备份需要同步更新 ThinkLibrary。

SQLite 回归测试直接使用内存数据库。可通过 `PHINX_TEST_MYSQL_PORT=端口 vendor/bin/phpunit -c phpunit.xml.dist` 额外运行 MySQL 往返验证（在 ThinkLibrary 目录执行）。该测试只用于可丢弃的本机 MySQL 实例：数据库名为 `phinx_fixture`，账号 `root`、空密码，会创建并清理结构、默认值和备份测试表及空的 `system_menu` 表。测试覆盖真实打包入口和带前缀恢复，不应指向业务数据库。

## 使用说明

1. **依赖管理**：ThinkLibrary 需要 Composer 支持进行安装和依赖管理。
2. **安装指南**：您可以使用以下命令通过 Composer 安装 ThinkLibrary：`composer require zoujingli/think-library`。
3. **使用示例**：在使用 ThinkLibrary 时，确保您的控制器类继承自 `think\admin\Controller`。一旦继承完成，您就可以通过 `$this` 对象访问并使用全部功能。

```php
// 定义 MyController 控制器
class MyController extend \think\admin\Controller {

    // 指定当前数据表名
    protected $dbQuery = '数据表名';
    
    // 显示数据列表
    public function index(){
        $this->_page($this->dbQuery);
    }
    
    // 当前列表数据处理
    protected function _index_page_filter(&$data){
         foreach($data as &$vo){
            // @todo 修改原列表
         }
    }
    
}
```

* 必要数据库表SQL（sysdata 函数需要用这个表）

```sql
CREATE TABLE `system_data`
(
    `id`    bigint(11) unsigned NOT NULL AUTO_INCREMENT,
    `name`  varchar(100) DEFAULT NULL COMMENT '配置名',
    `value` longtext COMMENT '配置值',
    PRIMARY KEY (`id`) USING BTREE,
    KEY     `idx_system_data_name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统-数据';
```

* 必要数据库表SQl（sysoplog 函数需要用的这个表）

```sql
CREATE TABLE `system_oplog`
(
    `id`        bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `node`      varchar(200)  NOT NULL DEFAULT '' COMMENT '当前操作节点',
    `geoip`     varchar(15)   NOT NULL DEFAULT '' COMMENT '操作者IP地址',
    `action`    varchar(200)  NOT NULL DEFAULT '' COMMENT '操作行为名称',
    `content`   varchar(1024) NOT NULL DEFAULT '' COMMENT '操作内容描述',
    `username`  varchar(50)   NOT NULL DEFAULT '' COMMENT '操作人用户名',
    `create_at` timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统-日志';
```

* 必要数据库表SQL（`sysconf`函数需要用到这个表）

```sql
CREATE TABLE `system_config`
(
    `type`  varchar(20)  DEFAULT '' COMMENT '分类',
    `name`  varchar(100) DEFAULT '' COMMENT '配置名',
    `value` varchar(500) DEFAULT '' COMMENT '配置值',
    KEY     `idx_system_config_type` (`type`),
    KEY     `idx_system_config_name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统-配置';
```

* 系统任务列队支持需要的数据表

```sql
CREATE TABLE `system_queue`
(
    `id`         bigint(20) NOT NULL AUTO_INCREMENT,
    `code`       varchar(20)          DEFAULT '' COMMENT '任务编号',
    `title`      varchar(50) NOT NULL DEFAULT '' COMMENT '任务名称',
    `command`    varchar(500)         DEFAULT '' COMMENT '执行指令',
    `exec_data`  longtext COMMENT '执行参数',
    `exec_time`  bigint(20) unsigned DEFAULT '0' COMMENT '执行时间',
    `exec_desc`  varchar(500)         DEFAULT '' COMMENT '状态描述',
    `enter_time` bigint(20) DEFAULT '0' COMMENT '开始时间',
    `outer_time` bigint(20) DEFAULT '0' COMMENT '结束时间',
    `attempts`   bigint(20) DEFAULT '0' COMMENT '执行次数',
    `rscript`    tinyint(1) DEFAULT '1' COMMENT '单例模式',
    `status`     tinyint(1) DEFAULT '1' COMMENT '任务状态(1新任务,2处理中,3成功,4失败)',
    `create_at`  timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE,
    KEY          `idx_system_queue_code` (`code`),
    KEY          `idx_system_queue_title` (`title`) USING BTREE,
    KEY          `idx_system_queue_status` (`status`) USING BTREE,
    KEY          `idx_system_queue_rscript` (`rscript`) USING BTREE,
    KEY          `idx_system_queue_create_at` (`create_at`) USING BTREE,
    KEY          `idx_system_queue_exec_time` (`exec_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统-任务';
```

#### 列表处理

```php
// 列表展示
$this->_page($dbQuery, $isPage, $isDisplay, $total);

// 列表展示搜索器（按 name、title 模糊搜索；按 status 精确搜索）
$this->_query($dbQuery)->like('name,title')->equal('status')->page();

// 对列表查询器进行二次处理
$query = $this->_query($dbQuery)->like('name, title')->equal('status');
$db = $query->db(); // @todo 这里可以对db进行操作
$this->_page($db); // 显示列表分页
```

#### 表单处理

```php
// 表单显示及数据更新
$this->_form($dbQuery, $tplFile, $pkField , $where, $data);
```

#### 删除处理

```php
// 数据删除处理
$this->_deleted($dbQuery);
```

#### 禁用启用处理

```php
// 数据禁用处理
$this->_save($dbQuery, ['status'=>'0']);

// 数据启用处理
$this->_save($dbQuery, ['status'=>'1']);
```

#### 文件存储组件（ oss 及 qiniu 需要配置参数）

```php

// 配置默认存储方式    
sysconf('storage.type','文件存储类型');

// 七牛云存储配置
sysconf('storage.qiniu_region', '文件存储节点');
sysconf('storage.qiniu_domain', '文件访问域名');
sysconf('storage.qiniu_bucket', '文件存储空间名称');
sysconf('storage.qiniu_is_https', '文件HTTP访问协议');
sysconf('storage.qiniu_access_key', '接口授权AccessKey');
sysconf('storage.qiniu_secret_key', '接口授权SecretKey');


// 生成文件名称(链接url或文件md5)
$filename = \think\admin\Storage::name($url, $ext, $prv, $fun);

// 获取文件内容（自动存储方式）
$result = \think\admin\Storage::get($filename);

// 保存内容到文件（自动存储方式）
$result = \think\admin\Storage::save($filename, $content);

// 判断文件是否存在
boolean \think\admin\Storage::has($filename);

// 获取文件信息
$result = \think\admin\Storage::info($filename);

//指定存储类型（调用方法）
$result = \think\admin\Storage::instance('local')->save($filename, $content);
$result = \think\admin\Storage::instance('qiniu')->save($filename, $content);
$result = \think\admin\Storage::instance('txcos')->save($filename, $content);
$result = \think\admin\Storage::instance('upyun')->save($filename, $content);
$result = \think\admin\Storage::instance('alioss')->save($filename, $content);

// 读取文件内容
$result = \think\admin\Storage::instance('local')->get($filename);
$result = \think\admin\Storage::instance('qiniu')->get($filename);
$result = \think\admin\Storage::instance('txcos')->get($filename);
$result = \think\admin\Storage::instance('upyun')->get($filename);
$result = \think\admin\Storage::instance('alioss')->get($filename);

// 生成 URL 访问地址
$result = \think\admin\Storage::instance('local')->url($filename);
$result = \think\admin\Storage::instance('qiniu')->url($filename);
$result = \think\admin\Storage::instance('txcos')->url($filename);
$result = \think\admin\Storage::instance('upyun')->url($filename);
$result = \think\admin\Storage::instance('alioss')->url($filename);

// 检查文件是否存在
boolean \think\admin\Storage::instance('local')->has($filename);
boolean \think\admin\Storage::instance('qiniu')->has($filename);
boolean \think\admin\Storage::instance('txcos')->has($filename);
boolean \think\admin\Storage::instance('upyun')->has($filename);
boolean \think\admin\Storage::instance('alioss')->has($filename);

// 生成文件信息
$resutl = \think\admin\Storage::instance('local')->info($filename);
$resutl = \think\admin\Storage::instance('qiniu')->info($filename);
$resutl = \think\admin\Storage::instance('txcos')->info($filename);
$resutl = \think\admin\Storage::instance('upyun')->info($filename);
$resutl = \think\admin\Storage::instance('alioss')->info($filename);
```

#### 通用数据保存

```php
// 指定关键列更新（$where 为扩展条件）
boolean data_save($dbQuery, $data, 'pkname', $where);
```

#### 通用网络请求

```php
// 发起get请求
$result = http_get($url, $query, $options);

// 发起post请求
$result = http_post($url, $data, $options);
```

#### 系统参数配置（基于 system_config 数据表）

```php
// 设置参数
sysconf($keyname, $keyvalue);

// 获取参数
$keyvalue = sysconf($kename);
```

### 数据加密

**自研 UTF8 加密**

```php
// 自研 UTF8 字符串加密操作
$string = encode($content);

// 自研 UTF8 加密字符串解密
$content = decode($string);
```

**数据解密**

```php
use think\admin\extend\CodeExtend;

// 数据 AES-256-CBC 对称加密
$encrypt = CodeExtend::encrypt($content, $enckey);

// 数据 AES-256-CBC 对称解密
$content = CodeExtend::decrypt($encrypt, $enckey);
```

**文本转 UTF8 编码**

```php
use think\admin\extend\CodeExtend;

// 文本转 UTF8 编码
$content = CodeExtend::text2utf8($content)
```

**文本 Base64 URL 编码**

```php
use think\admin\extend\CodeExtend;

// 文本 Base64 URL 编码
$safe64 = CodeExtend::enSafe64($content)

// 文本 Base64 URL 解码
$content = CodeExtend::deSafe64($safe64)
```

### 数据压缩处理

```php
use think\admin\extend\CodeExtend;

// 数据压缩 ( 内容越大效果越好 )
$enzip = CodeExtend::enzip($content)

// 数据解压 ( 内容越大效果越好 )
$content = CodeExtend::dezip($enzip)
```

### 数组结构处理

```php
use think\admin\extend\CodeExtend;

// 二维数组 转为 立体数据结构，需要存在 id 及 pid 关系
$tree = CodeExtend::arr2tree($list);

// 二维数组 转为 扁平数据结构，需要存在 id 及 pid 关系
$tree = CodeExtend::arr2table($list);

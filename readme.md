[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-library/v/stable)](https://packagist.org/packages/zoujingli/think-library) 
[![Latest Unstable Version](https://poser.pugx.org/zoujingli/think-library/v/unstable)](https://packagist.org/packages/zoujingli/think-library) 
[![Total Downloads](https://poser.pugx.org/zoujingli/think-library/downloads)](https://packagist.org/packages/zoujingli/think-library) 
[![License](https://poser.pugx.org/zoujingli/think-library/license)](https://packagist.org/packages/zoujingli/think-library)

# ThinkLibrary for ThinkPHP5.1
ThinkLibrary 是针对ThinkPHP5.1版本封装的一套工具类库，方便快速构建WEB应用。

## 主要包含内容
* 数据列表展示（可带高级搜索器）
* FORM表单处理器
* 数据快速删除处理
* 数据状态快速处理
* 数据安全删除处理（硬删除+软删除）
* 文件存储通用组件（本地服务存储 + 阿里云OSS存储 + 七牛云存储）
* 通用数据保存更新（通过key值及where判定是否存在，存在则更新，不存在则新增）
* 通用网络请求 （支持get及post，可配置请求证书等）
* emoji 表情转义处理（部分数据库不支持保存Emoji表情，可用这个方法哦）
* 系统参数通用k-v配置
* UTF8加密算法支持

## 参考项目
* Gitee 仓库：https://gitee.com/zoujingli/framework
* Github 仓库：https://gitee.com/zoujingli/framework

## 使用说明
* ThinkLibrary 需要Composer支持
* 安装命令 ` composer require zoujingli/think-library `
* 案例代码：
控制器需要继续 `library\Controller`，然后`$this`就可能使用全部功能。
```php
// 定义 MyController 控制器
class MyController extend \library\Controller{

    // 指定当前数据表名
    protected $dbQuery = '数据表名';
    
    // 显示数据列表
    public function index(){
        return $this->_page($dbQuery);
    }
    
    // 当前列表数据处理
    protected function _index_page_filter(&$data){
         foreach($data as &$vo){
            // @todo 修改原列表
         }
    }
    
}
```
* 必要数据库表SQL（sysconf函数需要用到这个表）
```sql
CREATE TABLE `system_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL COMMENT '配置名',
  `value` varchar(500) DEFAULT NULL COMMENT '配置值',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `index_system_config_name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='系统配置';
```

## 列表处理
```php
// 列表展示
return $this->_page($dbQuery, $isPage, $isDisplay, $total);

// 列表展示搜索器（按name、title模糊搜索；按status精确搜索）
$db = $this->_query($dbQuery)->like('name,title')->equal('status');
return $this->_page($db, $isPage, $isDisplay, $total);
```

## 表单处理
```php
// 表单显示及数据更新
return $this->_form($dbQuery, $tplFile, $pkField , $where, $data);
```

## 删除处理
```php
// 数据删除处理
return $this->_deleted($dbQuery);
```

## 禁用启用处理
```php
// 数据禁用处理
return $this->_save($dbQuery,['status'=>'0']);
// 数据启用处理
return $this->_save($dbQuery,['status'=>'1']);
```

## 文件存储组件（oss及qiniu需要配置参数）
```php
// 获取文件内容（自动存储方式）
\library\File::get($filename)

// 保存内容到文件（自动存储方式）
\library\File::save($filename,$content);

// 生成文件名称（自动存储方式）
\library\File::name($url,$ext,$prv,$fun);

// 判断文件是否存在
\library\File::has($filename);

// 获取文件信息
\library\File::info($filename);

//指定存储类型（调用方法）
\library\File::instance('oss')->save($filename,$content);
\library\File::instance('local')->save($filename,$content);
\library\File::instance('qiuniu')->save($filename,$content);

\library\File::instance('oss')->get($filename);
\library\File::instance('local')->get($filename);
\library\File::instance('qiuniu')->get($filename);

\library\File::instance('oss')->has($filename);
\library\File::instance('local')->has($filename);
\library\File::instance('qiuniu')->has($filename);
```

## 通用数据保存
```php
// 指定关键列更新（$where 为扩展条件）
boolean data_save($dbQuery,$data,'pkname',$where);
```

## 通用网络请求
```php
// 发起get请求
$result = http_get($url,$query,$options);
\library\tools\Http::get($url,$query,$options);

// 发起post请求
$result = http_post($url,$data,$options);
\library\tools\Http::post($url,$data,$options);
```

## emoji 表情转义（部分数据库不支持可以用这个）
```php
// 输入数据库前转义
emoji_encode($content);

// 输出数据库后转义
emoji_decode($content); 
```

## 系统参数配置（基于 system_config 数据表）
```php
// 设置参数
sysconf($keyname,$keyvalue);

// 获取参数
sysconf($kename);
```

## UTF8加密算法
```php
// 字符串加密操作
$string = encode($content);

// 加密解密操作
$content = decode($string);
```
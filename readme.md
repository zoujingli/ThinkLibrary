[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-library/v/stable)](https://packagist.org/packages/zoujingli/think-library) 
[![Latest Unstable Version](https://poser.pugx.org/zoujingli/think-library/v/unstable)](https://packagist.org/packages/zoujingli/think-library) 
[![Total Downloads](https://poser.pugx.org/zoujingli/think-library/downloads)](https://packagist.org/packages/zoujingli/think-library) 
[![License](https://poser.pugx.org/zoujingli/think-library/license)](https://packagist.org/packages/zoujingli/think-library)

# ThinkLibrary for ThinkPHP5.1
ThinkLibrary 是针对ThinkPHP5.1版本封装的一套工具类库，方便快速构建WEB应用。

## 参考项目
* Gitee 仓库：https://gitee.com/zoujingli/framework
* Github 仓库：https://gitee.com/zoujingli/framework

## ThinkLibrary 使用
控制器需要继续 `library\Controller`，然后`$this`就可能使用全部功能。
```php
// 定义 MyController 控制器
class MyController extend \library\Controller{
    
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
// 指定关键列更新（$where为扩展条件）
boolean data_save($dbQuery,$data,'pkname',$where);
```
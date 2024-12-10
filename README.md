# yii2-solr

### Install

```
composer require tinymeng/yii2-solr v1.0 -vvv
```

> 类库使用的命名空间为`\\tinymeng\\solr`

## 注意
* 不必安装PHP的Solr扩展(由于solr扩展很久没有更新过了,仅支持PHP5.6左右的版本,PHP7.0以上版本安装不上)
* 暂时只实现了AR查询的使用
* 未实现AR的增删改,可以使用 \yii::$app->solr 进行操作

引用小部件 main.php

```php
    'components' => [
        'solr'=>[
            'class'=> 'tinymeng\solr\Client',
            'options' => [
                'endpoint'=>[
                    [
                        'scheme' => 'http',
                        'host' => 'majiameng.com',
                        'port' => 8080,
                        'path' => '/solr/',
                        'core' => 'collection1',
                    ],
                    [
                        'scheme' => 'http',
                        'host' => 'majiameng.com',
                        'port' => 8080,
                        'path' => '/solr/',
                        'core' => 'collection2',
                    ],
                ],
            ]
        ],
    ]

```



### yii AR 查询模式

create Model
```php
<?php
namespace models\solr;
use \tinymeng\solr\ActiveRecord;
class Collection extends ActiveRecord
{
    /** solr core name */
    public static function tableName()
    {
        return 'collection1';
    }

    /** solr core attr */
    public function attributes()
    {
        return [
            'id',
            'title',
            'name',
        ];
    }

}

```

查询方法
```php
    
$where = [
    'id'=>1,
    'type'=>28,
    ['between','type',1,100]
];
$select = ['id,title'];
$page = 1;
$page_size = 20;
$list = Collection::find()
    ->select($select)
    ->where($where)
    ->offset(($page-1)*$page_size)
    ->limit($page_size)
    ->orderBy('id asc,type asc')
    ->asArray()
    ->all();

```

查询高亮方法

```php
$keywords = '汽车';
Collection::find()
    ->select($select)
    ->where(['keywords'=>$keywords])
    ->highlight([
        "pre_tags"=>'<font color="#ff0000">',
        "post_tags"=>'</font>',
        "fields"=>['title','content']
    ])
    ->asArray()
    ->all();

```
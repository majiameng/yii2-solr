# yii2-solr

### 安装

```
composer require tinymeng/yii2-solr dev-master
```

> 类库使用的命名空间为`\\tinymeng\\solr`



引用小部件 main.php

```php
    'components' => [
        'solr'=>[
            'class'=> 'tinymeng\solr\Client',
            'options' => [
                'endpoint'=>[
                    [
                        'scheme' => 'http',
                        'host' => '47.93.233.186',
                        'port' => 9080,
                        'path' => '/solr/',
                        'core' => 'flow',
                        'timeout' => 5,
                        'wt'=>'json',
                    ],
                ],
            ]
        ],
    ]

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
$list = FlowSolr::find()
        ->select($select)
        ->where($where)
        ->offset(($page-1)*$page_size)
        ->limit($page_size)
        ->orderBy('id asc,type asc')
        ->asArray()
->all();

```
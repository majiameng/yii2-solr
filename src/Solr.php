<?php
/**
 * Name: solr.php.
 * @author: JiaMeng <666@majiameng.com>
 * Date: 2018/12/20 16:47
 * Description: solr.php.
 */
namespace tinymeng\solr;

class Solr
{
    public $endpoint;

    /**
    'endpoint'=>[
        [
            'scheme' => 'http',
            'host' => '47.93.233.186',
            'port' => 9080,
            'path' => '/solr/',
            'core' => null,
            'timeout' => 5,
            'wt'=>'json',
        ],
    ],
     */

    /**
     * Description:  find
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     */
    public function find()
    {
        $data = $this->endpoint;
        var_dump($data);
    }

}
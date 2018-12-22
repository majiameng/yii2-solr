<?php
/**
 * Name: solr.php.
 * @author: JiaMeng <666@majiameng.com>
 * Date: 2018/12/20 16:47
 * Description: solr.php.
 */
namespace tinymeng\solr;
use yii\base\Component;
use Solarium\Client as SolrClient;

class Client extends Component
{
    public $options = [];
    public $solr;

    public $defaultProtocol = 'http';
    public $defaultScheme = '8080';

    public function init(){
        $this->solr = new SolrClient($this->options);
    }

    public function __call($name, $params)
    {
        if(method_exists($this->solr, $name)){
            return call_user_func_array([$this->solr, $name], $params);
        }
        parent::call($name, $params);
    }

    /**
     * Creates new query builder instance
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return new QueryBuilder($this);
    }
}
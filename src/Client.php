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
    /**
     * @author: JiaMeng <666@majiameng.com>
     * @var array
     * public $options = [
     *    'endpoint'=>[
     *        [
     *              'scheme' => 'http',
     *              'host' => '47.93.233.186',
     *              'port' => 9080,
     *              'path' => '/solr/',
     *              'core' => 'flow',
     *        ],
     *    ],
     *];
     */
    public $options;
    public $solr;
    public $defaultProtocol = '8080';
    public $defaultScheme = 'http';
    public $defaultPath = '/solr/';
    public $defaultTimeout = 5;
    public $defaultWt = 'json';

    /**
     * Description:  init
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     */
    public function init(){
        $this->getOptions();
        $this->solr = new SolrClient($this->options);
    }

    /**
     * Description:  __call
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function __call($name, $params)
    {
        if(method_exists($this->solr, $name)){
            return call_user_func_array([$this->solr, $name], $params);
        }
        parent::call($name, $params);
    }

    /**
     * Description:  getOptions
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return array
     * @throws \Exception
     */
    public function getOptions(){
        foreach ($this->options as $key=>$val) {
            foreach ($val as $k=>$v){
                if (!isset($v['host'])) throw new \Exception('solr options needs at least a host configured.');
                if (!isset($v['core'])) throw new \Exception('solr options needs at least a core configured.');
                if (!isset($v['scheme'])) $v['scheme'] = $this->defaultScheme;
                if (!in_array($v['scheme'], ['http', 'https'])) throw new \Exception('Valid options scheme settings are "http" and "https".');
                if (!isset($v['port'])) $v['port'] = $this->defaultProtocol;
                if (!isset($v['path'])) $v['path'] = $this->defaultPath;
                if (!isset($v['timeout'])) $v['timeout'] = $this->defaultTimeout;
                if (!isset($v['wt'])) $v['wt'] = $this->defaultWt;
                $this->options[$key][$k]['key'] = $v['core'];
            }
        }
        return $this->options;
    }

    /**
     * Creates new query builder instance
     * Description:  getQueryBuilder
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return new QueryBuilder($this);
    }
}
<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tinymeng\solr;

use yii\base\Component;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveQueryTrait;
use yii\db\ActiveRelationTrait;
use yii\db\QueryTrait;

/**
 * @author: JiaMeng <666@majiameng.com>
 * ActiveQuery represents a query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by [[ActiveRecord::find()]].
 * Relational queries are created by [[ActiveRecord::hasOne()]] and [[ActiveRecord::hasMany()]].
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - [[one()]]: returns a single record populated with the first row of data.
 * - [[all()]]: returns all records based on the query results.
 *
 * You can use query methods, such as [[where()]], [[limit()]] , [[orderBy()]] and [[highlight()]] to customize the query options.
 *
 *  ActiveQuery::find()->highlight([
 *       "pre_tags"=>'<font color="#ff0000">',
 *       "post_tags"=>'</font>',
 *       "fields"=>['title','brandword']
 *   ])->asArray()->all();
 *
 */
class ActiveQuery extends Component implements ActiveQueryInterface
{
    use QueryTrait;
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    public $select;
    public $highlight;

    /**
     * @event Event an event that is triggered when the query is initialized via [[init()]].
     */
    const EVENT_INIT = 'init';

    /**
     * Constructor.
     * @param array $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /** 注销这些父类方法 */
    public function andWhere($condition){self::cancelFunction(__FUNCTION__);}
    public function orWhere($condition){self::cancelFunction(__FUNCTION__);}
    public function orFilterWhere(array $condition){self::cancelFunction(__FUNCTION__);}
    public function filterWhere(array $condition){self::cancelFunction(__FUNCTION__);}
    public function exists($db = null){self::cancelFunction(__FUNCTION__);}
    public function count($q = '*', $db = null){self::cancelFunction(__FUNCTION__);}

    public function andFilterWhere(array $condition){
        if(count($condition) == 1){
            if(!empty(array_values($condition)[0])){
                $this->where[] = $condition;
            }
        }elseif(count($condition) == 3){
            if (isset($condition[0], $condition[1], $condition[2])) {
                $this->where[] = $condition;
            }
        }else{
            throw new \Exception('andFilterWhere value error.');
        }
        return $this;
    }

    /**
     * Description:  select
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $condition
     * @return $this
     */
    public function select($condition){
        $this->select = $condition;
        return $this;
    }

    public function highlight($condition){
        $this->highlight = $condition;
        return $this;
    }

    /**
     * Description:  debug
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return mixed
     */
    public function debug(){
        $modelClass = $this->modelClass;
        $db = $modelClass::getDb();
        $where = $db->getQueryBuilder()->build($this);
        var_dump($where);
        exit();
    }

    /**
     * Description:  all
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param null $db
     * @return array|mixed
     */
    public function all($db = null)
    {
        $modelClass = $this->modelClass;
        if ($db === null) {
            /** Getting DB singletons */
            $db = $modelClass::getDb();
        }

        /** Setting query conditions */
        $query = $this->setQuery($db);

        /** Select */
        $result = $db->select($query,$modelClass::tableName());

        /** Get response */
        $response = $this->getResponse($result);
        return $response;
    }

    /**
     * Description:  one
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     *
     * @param null $db
     * @return array|mixed
     */
    public function one($db = null)
    {
        $this->limit = 1;
        $result = $this->all($db);

        if ($this->asArray !== null) {
            return count($result['list']) === 0 ? array() : (array)$result['list'][0];
        }
        return $result;
    }

    /**
     * Description:  Setting query conditions
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return array|string
     */
    public function setQuery($db)
    {
        /** Create Select */
        $query = $db->createSelect();
        /** Get where condition */
        $where = $db->getQueryBuilder()->build($this);

        /** Setting query and fquery */
        $queryWhere = "*:*";
        if(!empty($where) && is_string($where)){
            $queryWhere = $where;
        }elseif(!empty($where) && is_array($where)){
            $k = key($where[0]);
            $v = $where[0][key($where[0])] === '' ? '""' : $where[0][key($where[0])];
            $queryWhere = $k.':'.$v;
            if(count($where)>1){
                unset($where[0]);
                foreach ($where as $key=>$value){
                    $k = key($value);
                    $v = $value[key($value)] === '' ? '""' : $value[key($value)];
                    $query->createFilterQuery($k.$key)->setQuery($k.":".$v);
                }
            }
        }
        $query->setQuery($queryWhere);


        /** set fields to fetch (this overrides the default setting 'all fields') */
        if($this->select !== null){
            $query->setFields($this->select);
        }

        /** set start and rows param (comparable to SQL limit) using fluent interface */
        $query->setStart($this->offset)->setRows($this->limit);

        /** sort the results by price ascending */
        if($this->orderBy !== null){
            foreach ($this->orderBy as $key=>$value) {
                $query->addSort($key, $value == SORT_ASC ? 'ASC' :'DESC');
            }
        }

        /** Document highlight */
        if($this->highlight !== null && !empty($this->highlight['fields'])){
            $Highlighting = $query->getHighlighting();
            $pre_tags = empty($this->highlight['pre_tags']) ? '<b>' : $this->highlight['pre_tags'];
            $post_tags = empty($this->highlight['post_tags']) ? '</b>' : $this->highlight['post_tags'];
            $fields = is_string($this->highlight['fields']) ? explode(',',$this->highlight['fields']) : $this->highlight['fields'];
            foreach ($fields as $value){
                $Highlighting->getField($value)->setSimplePrefix($pre_tags)->setSimplePostfix($post_tags);
            }
        }
        return $query;
    }

    /**
     * Description:  Get response
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function getResponse($result){
        $response = $result->getResponse();
        if($response->getStatusCode() !== 200){
            throw new \Exception('Solr Response error:'.$response->getStatusCode().' , body ;'.$response->getBody());
        }else{
            $highlighting = $result->getHighlighting();
            $result = json_decode($response->getBody());
            if($this->asArray !== null){
                /** get primaryKey */
                if($highlighting !== null){
                    $modelClass = $this->modelClass;
                    $primaryKey = is_array($modelClass::PrimaryKey()) ? ( empty($primaryKey) ? 'id' : $primaryKey[0] ) : $modelClass::PrimaryKey();
                }

                /** object docs to array */
                $list = (array)$result->response->docs;
                foreach ($list as $key=>$value) {
                    $list[$key] = (array)$value;

                    /** List highlighted document replacement  */
                    if($highlighting !== null) {
                        $highlightedDoc = $highlighting->getResult($value->$primaryKey);
                        foreach ($highlightedDoc as $k=>$v){
                            $list[$key][$k] = count($v)>1 ? $v : $v[0];
                        }
                    }
                }
                $result = [
                    'count'=>$result->response->numFound,
                    'start'=>$result->response->start,
                    'list'=>$list
                ];
                if(!empty($result->response->maxScore))$result['max_score']=$result->response->maxScore;
            }
            return $result;
        }
    }

    /**
     * Description:  cancelFunction
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $condition
     * @throws \Exception
     */
    static public function cancelFunction($condition){
        throw new \Exception('Solr ActiveQuery Cancellation function :'.$condition);
    }

}

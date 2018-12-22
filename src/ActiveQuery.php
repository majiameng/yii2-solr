<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tinymeng\solr;

use yii\base\Component;
use yii\base\InvalidParamException;
use yii\base\NotSupportedException;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveQueryTrait;
use yii\db\ActiveRelationTrait;
use yii\db\QueryTrait;

/**
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
 * - [[count()]]: returns the number of records.
 * - [[sum()]]: returns the sum over the specified column.
 * - [[average()]]: returns the average over the specified column.
 * - [[min()]]: returns the min over the specified column.
 * - [[max()]]: returns the max over the specified column.
 * - [[scalar()]]: returns the value of the first column in the first row of the query result.
 * - [[exists()]]: returns a value indicating whether the query result has data or not.
 *
 * You can use query methods, such as [[where()]], [[limit()]] and [[orderBy()]] to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - [[with()]]: list of relations that this query should be performed with.
 * - [[indexBy()]]: the name of the column by which the query result should be indexed.
 * - [[asArray()]]: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling [[ActiveRecord::hasOne()]] and
 * [[ActiveRecord::hasMany()]]. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by [[link]] which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by [[multiple]].
 *
 * If a relation involves a junction table, it may be specified by [[via()]].
 * This methods may only be called in a relational context. Same is true for [[inverseOf()]], which
 * marks a relation as inverse of another relation.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ActiveQuery extends Component implements ActiveQueryInterface
{

    use QueryTrait;
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    public $select;

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
    public function andWhere($condition){}
    public function orWhere($condition){}
    public function andFilterWhere(array $condition){}
    public function orFilterWhere(array $condition){}
    public function filterWhere(array $condition){}
    public function exists($db = null){}
    public function count($q = '*', $db = null){}

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

    public function all($db = null)
    {
        /** 获取db单例 */
        $modelClass = $this->modelClass;
        if ($db === null) {
            $db = $modelClass::getDb();
        }

        /** 设置query条件 */
        $query = $this->setQuery($db);

        // set fields to fetch (this overrides the default setting 'all fields')
        if($this->select !== null){
            $query->setFields($this->select);
        }

        // set start and rows param (comparable to SQL limit) using fluent interface
        $query->setStart($this->offset)->setRows($this->limit);

        // sort the results by price ascending
        foreach ($this->orderBy as $key=>$value) {
            $query->addSort($key, $value == SORT_ASC ? 'ASC' :'DESC');
        }

        /** 查询 */
        $result = $db->select($query);

        /** 获取响应 */
        $response = $this->getResponse($result);
        return $response;
    }

    /**
     * Description:  one
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param null $db
     * @return array|mixed
     */
    public function one($db = null)
    {
        $this->offset = 0;
        $this->limit = 1;
        $result = $this->all($db);

        if ($this->asArray !== null) {
            if(count($result['list']) === 0){
                return array();
            }
            return (array)$result['list'][0];
        }
        return $result;
    }

    /**
     * Description:  获取响应
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function getResponse($result){
        $res = $result->getResponse();
        if($res->getStatusCode() !== 200){
            throw new \Exception('Solr Response error:'.$res->getStatusCode().' , body ;'.$res->getBody());
        }else{
            $result = json_decode($res->getBody());
            if($this->asArray !== null){
                $list = (array)$result->response->docs;
                foreach ($list as $value) {
                    $list[] = (array)$value;
                }
                $result = [
                    'count'=>$result->response->numFound,
                    'start'=>$result->response->start,
                    'max_score'=>$result->response->maxScore,
                    'list'=>$list
                ];
            }
            return $result;
        }
    }

    /**
     * Description:  设置query条件
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return array|string
     */
    public function setQuery($db)
    {
        /** 创建查询 */
        $query = $db->createSelect();
        /** 转义where条件 */
        $where = $db->getQueryBuilder()->build($this);

        /** 设置query和fquery */
        $queryWhere = "*:*";
        if(!empty($where) && is_string($where)){
            $queryWhere = $where;
        }elseif(!empty($where) && is_array($where)){
            $queryWhere = key($where[0]).':'.$where[0][key($where[0])];
            if(count($where)>1){
                unset($where[0]);
                foreach ($where as $key=>$value){
                    $query->createFilterQuery(key($value).$key)->setQuery(key($value).":".$value[key($value)]);
                }
            }
        }

        $query->setQuery($queryWhere);
        return $query;
    }
}

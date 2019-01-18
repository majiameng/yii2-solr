<?php
namespace tinymeng\solr;

use yii\base\BaseObject;
use yii\base\InvalidParamException;
use yii\base\NotSupportedException;

class QueryBuilder extends BaseObject
{
    /**
     * @var Connection the database connection.
     */
    public $db;

    /**
     * Constructor.
     * @param Connection $connection the database connection.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($connection, $config = [])
    {
        $this->db = $connection;
        parent::__construct($config);
    }

    /**
     * @author: JiaMeng <666@majiameng.com>
     * Generates query from a [[Query]] object.
     * @param Query $query the [[Query]] object from which the query will be generated
     * @return array the generated SQL statement (the first array element) and the corresponding
     * parameters to be bound to the SQL statement (the second array element).
     */
    public function build($query)
    {
        if(is_string($query->where)){
            return $query->where;
        }

        $parts = [];
        if($query->where != null){
            foreach ($query->where as $key=>$value){
                if(count($value) == 1){
                    if(in_array($value,['and','or']))continue;
                    $parts[] = [$key=>$value];
                }else{
                    $val = $this->buildCondition($value);
                    if(!empty($val)) $parts[] = $val;
                }
            }
        }
        return $parts;
    }

    /**
     * Parses the condition specification and generates the corresponding SQL expression.
     * @author: JiaMeng <666@majiameng.com>
     * @param string|array $condition the condition specification. Please refer to [[Query::where()]] on how to specify a condition.
     * @throws \yii\base\InvalidParamException if unknown operator is used in query
     * @throws \yii\base\NotSupportedException if string conditions are used in where
     * @return array|boolean
     */
    public function buildCondition($condition)
    {
        static $builders = [
            'not' => 'buildNotCondition',
            'between' => 'buildBetweenCondition',
            'not between' => 'buildBetweenCondition',
            'in' => 'buildInCondition',
            'not in' => 'buildInCondition',
            'like' => 'buildLikeCondition',
            'not like' => 'buildLikeCondition',
            'or like' => 'buildLikeCondition',
            'or not like' => 'buildLikeCondition',
            '!=' => 'buildHalfBoundedRangeCondition',
            '<>' => 'buildHalfBoundedRangeCondition',
            '=' => 'buildHalfBoundedRangeCondition',
            'lt' => 'buildHalfBoundedRangeCondition',
            '<' => 'buildHalfBoundedRangeCondition',
            'lte' => 'buildHalfBoundedRangeCondition',
            '<=' => 'buildHalfBoundedRangeCondition',
            'gt' => 'buildHalfBoundedRangeCondition',
            '>' => 'buildHalfBoundedRangeCondition',
            'gte' => 'buildHalfBoundedRangeCondition',
            '>=' => 'buildHalfBoundedRangeCondition',
        ];
        if (empty($condition)) {
            return [];
        }
        if (!is_array($condition)) {
            throw new NotSupportedException('String conditions in where() are not supported by solr.');
        }

        if (isset($condition[0])) { // operator format: operator, operand 1, operand 2, ...
            $operator = trim(strtolower($condition[0]));
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
                array_shift($condition);

                return $this->$method($operator, $condition);
            } else {
                throw new InvalidParamException('Found unknown operator in query: ' . $operator);
            }
        }
        return false;
    }

    /**
     * Description:  buildNotCondition
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $operator
     * @param $operands
     * @return array|boolean
     */
    private function buildNotCondition($operator, $operands)
    {
        if (!isset($operands[0])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }elseif(!isset($operands[1])){
            return false;
        }

        list($column, $value) = $operands;


        if($operator == 'not'){
            $column = 'NOT '.$column;
        }else{
            throw new InvalidParamException("Operator '$operator' is not implemented.");
        }
        return [$column=>$value];
    }

    /**
     * Description:  buildBetweenCondition
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $operator
     * @param $operands
     * @return array
     */
    private function buildBetweenCondition($operator, $operands)
    {

        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidParamException("Operator '$operator' requires three operands.");
        }

        list($column, $value1, $value2) = $operands;
        $filter = "[ $value1 TO $value2 ]";
        if ($operator == 'not between') {
            $column = 'NOT '.$column;
        }
        return [$column=>$filter];
    }

    /**
     * Description:  buildInCondition
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $operator
     * @param $operands
     * @return array|boolean
     */
    private function buildInCondition($operator, $operands)
    {
        if (!isset($operands[0])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }elseif(!isset($operands[1])){
            return false;
        }
        list($column, $value) = $operands;

        if($value === null){
            return false;
        }else if(is_string($value) || is_integer($value)){
            $value = "( $value )";
        }elseif(is_array($value)){
            $value = "( ".implode(' OR ',$value)." )";
        }else{
            throw new InvalidParamException("Value formatted incorrectly ,Must be String or Array");
        }

        if ($operator == 'not in') {
            $column = 'NOT '.$column;
        }
        return [$column=>$value];
    }

    /**
     * Description:  buildLikeCondition
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $operator
     * @param $operands
     * @return array|boolean
     */
    private function buildLikeCondition($operator, $operands)
    {
        if (!isset($operands[0])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }elseif(!isset($operands[1])){
            return false;
        }
        list($column, $value) = $operands;

        $range_operator = null;
        if ($operator === 'like') {
            $range_operator = "*$value*";
        } elseif ($operator === 'not like') {
            $column = "NOT ".$column;
            $range_operator = "*$value*";
        } elseif ($operator === 'or like') {
            if(is_string($value)  || is_integer($value)){
                $range_operator = "( $value )";
            }elseif(is_array($value)){
                $range_operator = "( ".implode(' OR ',$value)." )";
            }else{
                throw new InvalidParamException("Value formatted incorrectly ,Must be String or Array");
            }
        } elseif ($operator === 'or not like') {
            $column = "NOT ".$column;
            if(is_string($value)  || is_integer($value)){
                $range_operator = "( $value )";
            }elseif(is_array($value)){
                $range_operator = "( ".implode(' OR ',$value)." )";
            }else{
                throw new InvalidParamException("Value formatted incorrectly ,Must be String or Array");
            }
        }
        if ($range_operator === null) {
            throw new InvalidParamException("Operator '$operator' is not implemented.");
        }

        return [$column=>$range_operator];
    }

    /**
     * Builds a half-bounded range condition
     * @author: JiaMeng <666@majiameng.com>
     * (for "=", "gt", ">", "gte", ">=", "lt", "<", "lte", "<=" operators)
     * @param string $operator
     * @param array $operands
     * @return array|boolean
     */
    private function buildHalfBoundedRangeCondition($operator, $operands)
    {
        if (!isset($operands[0])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }elseif(!isset($operands[1])){
            return false;
        }
        list($column, $value) = $operands;

        $range_operator = null;
        if ($operator === '=') {
            $range_operator = $value;
        }elseif (in_array($operator, ['gte', '>='])) {
            $range_operator = "[ $value TO * ]";
        } elseif (in_array($operator, ['lte', '<='])) {
            $range_operator = "[ * TO $value ]";
        } elseif (in_array($operator, ['gt', '>'])) {
            $range_operator = "{ $value TO * }";
        } elseif (in_array($operator, ['lt', '<'])) {
            $range_operator = "{ * TO $value }";
        } elseif (in_array($operator, ['<>', '!='])) {
            $column = "NOT $column";
            if(is_array($value)){
                $range_operator = "( ".implode(' OR ',$value)." )";
            }else{
                $range_operator = $value;
            }
        }
        if ($range_operator === null) {
            throw new InvalidParamException("Operator '$operator' is not implemented.");
        }

        return [$column=>$range_operator];
    }

}

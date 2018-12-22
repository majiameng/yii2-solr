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
                    $parts[] = [$key=>$value];
                }else{
                    $parts[] = $this->buildCondition($value);
                }
            }
        }
        return $parts;
    }

    /**
     * Parses the condition specification and generates the corresponding SQL expression.
     *
     * @param string|array $condition the condition specification. Please refer to [[Query::where()]] on how to specify a condition.
     * @throws \yii\base\InvalidParamException if unknown operator is used in query
     * @throws \yii\base\NotSupportedException if string conditions are used in where
     * @return string the generated SQL expression
     */
    public function buildCondition($condition)
    {
        static $builders = [
//            'not' => 'buildNotCondition',
//            'and' => 'buildAndCondition',
//            'or' => 'buildAndCondition',
            'between' => 'buildBetweenCondition',
//            'not between' => 'buildBetweenCondition',
//            'in' => 'buildInCondition',
//            'not in' => 'buildInCondition',
//            'like' => 'buildLikeCondition',
//            'not like' => 'buildLikeCondition',
//            'or like' => 'buildLikeCondition',
//            'or not like' => 'buildLikeCondition',
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
            $operator = strtolower($condition[0]);
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
                array_shift($condition);

                return $this->$method($operator, $condition);
            } else {
                throw new InvalidParamException('Found unknown operator in query: ' . $operator);
            }
        }
        return [];
    }

    private function buildNotCondition($operator, $operands)
    {
        if (count($operands) != 1) {
            throw new InvalidParamException("Operator '$operator' requires exactly one operand.");
        }

        $operand = reset($operands);
        if (is_array($operand)) {
            $operand = $this->buildCondition($operand);
        }

        return [$operator => $operand];
    }

    private function buildAndCondition($operator, $operands)
    {
        $parts = [];
        foreach ($operands as $operand) {
            if (is_array($operand)) {
                $operand = $this->buildCondition($operand);
            }
            if (!empty($operand)) {
                $parts[] = $operand;
            }
        }
        if (!empty($parts)) {
            return [$operator => $parts];
        } else {
            return [];
        }
    }

    private function buildBetweenCondition($operator, $operands)
    {

        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidParamException("Operator '$operator' requires three operands.");
        }

        list($column, $value1, $value2) = $operands;
        $filter = "[ $value1 To $value2 ]";
//        if ($operator == 'not between') {
//            $filter = ['not' => $filter];
//        }
        return [$column=>$filter];
    }

    private function buildInCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($column, $values) = $operands;

        $values = (array)$values;

        if (empty($values) || $column === []) {
            return $operator === 'in' ? ['terms' => ['_uid' => []]] : []; // this condition is equal to WHERE false
        }

        if (count($column) > 1) {
            return $this->buildCompositeInCondition($operator, $column, $values);
        } elseif (is_array($column)) {
            $column = reset($column);
        }
        $canBeNull = false;
        foreach ($values as $i => $value) {
            if (is_array($value)) {
                $values[$i] = $value = isset($value[$column]) ? $value[$column] : null;
            }
            if ($value === null) {
                $canBeNull = true;
                unset($values[$i]);
            }
        }
        if ($column == '_id') {
            if (empty($values) && $canBeNull) { // there is no null pk
                $filter = ['terms' => ['_uid' => []]]; // this condition is equal to WHERE false
            } else {
                $filter = ['ids' => ['values' => array_values($values)]];
                if ($canBeNull) {
                    $filter = [
                        'or' => [
                            $filter,
                            ['missing' => ['field' => $column, 'existence' => true, 'null_value' => true]]
                        ]
                    ];
                }
            }
        } else {
            if (empty($values) && $canBeNull) {
                $filter = ['missing' => ['field' => $column, 'existence' => true, 'null_value' => true]];
            } else {
                $filter = ['in' => [$column => array_values($values)]];
                if ($canBeNull) {
                    $filter = [
                        'or' => [
                            $filter,
                            ['missing' => ['field' => $column, 'existence' => true, 'null_value' => true]]
                        ]
                    ];
                }
            }
        }
        if ($operator == 'not in') {
            $filter = ['not' => $filter];
        }

        return $filter;
    }

    /**
     * Builds a half-bounded range condition
     * (for "gt", ">", "gte", ">=", "lt", "<", "lte", "<=" operators)
     * @param string $operator
     * @param array $operands
     * @return array Filter expression
     */
    private function buildHalfBoundedRangeCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;

        $range_operator = null;
        if (in_array($operator, ['gte', '>='])) {
            $range_operator = "[ $value To * ]";
        } elseif (in_array($operator, ['lte', '<='])) {
            $range_operator = "[ * To $value ]";
        } elseif (in_array($operator, ['gt', '>'])) {
            $range_operator = "{ $value TO * }";
        } elseif (in_array($operator, ['lt', '<'])) {
            $range_operator = "{ * To $value }";
        }
        if ($range_operator === null) {
            throw new InvalidParamException("Operator '$operator' is not implemented.");
        }

        return [$column=>$range_operator];
    }

    protected function buildCompositeInCondition($operator, $columns, $values)
    {
        throw new NotSupportedException('composite in is not supported by elasticsearch.');
    }

    private function buildLikeCondition($operator, $operands)
    {
        throw new NotSupportedException('like conditions are not supported by elasticsearch.');
    }
}

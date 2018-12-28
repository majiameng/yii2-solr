<?php
namespace tinymeng\solr;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * This class implements the ActiveRecord pattern for the [solr](http://lucene.apache.org/solr/) key-value store.
 *
 * For defining a record a subclass should at least implement the [[attributes()]] method to define
 * attributes.
 *
 * The following is an example model called `Customer`:
 *
 * ```php
 * class Customer extends \tinymeng\solr\ActiveRecord
 * {
 *     public static function tableName()
 *     {
 *         return 'customer';
 *     }
 *
 *     public function attributes()
 *     {
 *         return ['id', 'name', 'address', 'registration_date'];
 *     }
 * }
 * ```
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ActiveRecord extends BaseActiveRecord
{
    /**
     * Returns the database connection used by this AR class.
     * By default, the "solr" application component is used as the database connection.
     * You may override this method if you want to use a different database connection.
     * @return Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->solr;
    }

    /**
     * @inheritdoc
     * @return ActiveQuery the newly created [[ActiveQuery]] instance.
     */
    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }

    /**
     * Returns the primary key name(s) for this AR class.
     * This method should be overridden by child classes to define the primary key.
     *
     * Note that an array should be returned even when it is a single primary key.
     *
     * @return string[] the primary keys of this record.
     */
    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * Returns the list of all attribute names of the model.
     * This method must be overridden by child classes to define available attributes.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        throw new InvalidConfigException('The attributes() method of solr ActiveRecord has to be implemented by child classes.');
    }


    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributes = null)
    {
    }

    /**
     * Updates the whole table using the provided attribute values and conditions.
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ~~~
     * Customer::updateAll(['status' => 1], ['id' => 2]);
     * ~~~
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table
     * @param array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to [[ActiveQuery::where()]] on how to specify this parameter.
     * @return int the number of rows updated
     */
    public static function updateAll($attributes, $condition = null)
    {
    }

    /**
     * Updates the whole table using the provided counter changes and conditions.
     * For example, to increment all customers' age by 1,
     *
     * ~~~
     * Customer::updateAllCounters(['age' => 1]);
     * ~~~
     *
     * @param array $counters the counters to be updated (attribute name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to [[ActiveQuery::where()]] on how to specify this parameter.
     * @return int the number of rows updated
     */
    public static function updateAllCounters($counters, $condition = null)
    {
    }

    /**
     * Deletes rows in the table using the provided conditions.
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ~~~
     * Customer::deleteAll(['status' => 3]);
     * ~~~
     *
     * @param array $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     * Please refer to [[ActiveQuery::where()]] on how to specify this parameter.
     * @return int the number of rows deleted
     */
    public static function deleteAll($condition = null)
    {

    }

}

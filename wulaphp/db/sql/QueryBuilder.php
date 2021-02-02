<?php

namespace wulaphp\db\sql;

use wulaphp\db\dialect\DatabaseDialect;
use wulaphp\db\DialectException;

/**
 * 查询基类
 */
abstract class QueryBuilder {
    const LEFT  = 'LEFT';
    const RIGHT = 'RIGHT';
    const INNER = '';

    private static $sqlCount    = 0;
    protected      $sql         = null;
    protected      $alias;
    protected      $options     = [];
    protected      $from        = [];
    protected      $joins       = [];
    protected      $where       = null;
    protected      $having      = [];
    protected      $limit       = null;
    protected      $group       = [];
    protected      $order       = [];
    protected      $error       = false;
    protected      $errorSQL    = '';
    protected      $errorValues = null;
    protected      $dumpSQL     = null;
    protected      $exception   = null;
    protected      $performed   = false;
    protected      $whereData   = [];
    protected      $whereSet    = false;
    /**
     * @var \PDOStatement
     */
    protected $statement = null;
    /**
     * @var DatabaseDialect
     */
    protected $dialect;
    /**
     * @var BindValues
     */
    protected $values     = null;
    protected $valueFixed = false;//子查询时需锁定

    public function __destruct() {
        $this->close();
    }

    /**
     * 关闭
     */
    public function close() {
        $this->alias   = null;
        $this->dialect = null;
        $this->values  = null;
        $this->options = null;
        $this->from    = null;
        $this->joins   = null;
        $this->where   = null;
        $this->having  = null;
        $this->limit   = null;
        $this->group   = null;
        $this->order   = null;
        if ($this->statement) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    /**
     * @param DatabaseDialect|null $dialect
     *
     * @return $this
     */
    public function setDialect(?DatabaseDialect $dialect): QueryBuilder {
        $this->dialect = $dialect;

        return $this;
    }

    /**
     * @param string $table
     * @param string $on
     * @param string $type
     *
     * @return $this
     */
    public function join(string $table, string $on, string $type = QueryBuilder::LEFT): QueryBuilder {
        $table          = self::parseAs($table);
        $join           = [$table [0], $on, $type . ' JOIN ', $table [1]];
        $this->joins [] = $join;

        return $this;
    }

    /**
     * left join.
     *
     * @param string   $table
     * @param string[] $on
     *
     * @return $this
     */
    public function left(string $table, string ...$on): QueryBuilder {
        $this->join($table, Condition::cleanField($on[0]) . '=' . Condition::cleanField($on[1]), self::LEFT);

        return $this;
    }

    /**
     * right join.
     *
     * @param string   $table
     * @param string[] $on
     *
     * @return $this
     */
    public function right(string $table, string ...$on): QueryBuilder {
        $this->join($table, Condition::cleanField($on[0]) . '=' . Condition::cleanField($on[1]), self::RIGHT);

        return $this;
    }

    /**
     * inner join.
     *
     * @param string   $table
     * @param string[] $on
     *
     * @return $this
     */
    public function inner(string $table, string ...$on): QueryBuilder {
        $this->join($table, Condition::cleanField($on[0]) . '=' . Condition::cleanField($on[1]), self::INNER);

        return $this;
    }

    /**
     * 条件.
     *
     * @param array|Condition $con
     * @param bool            $append
     *
     * @return $this
     */
    public function where($con, $append = true): QueryBuilder {
        $this->whereSet = true;
        if (is_array($con) && !empty ($con)) {
            $con = new Condition ($con, $this->alias);
        }
        if ($con) {
            if ($append && $this->where) {
                $this->where [] = $con;
            } else {
                $this->performed = false;
                $this->sql       = null;
                $this->where     = $con;
            }
        }

        return $this;
    }

    /**
     * 更新条件中的数据.
     *
     * @param $data
     *
     * @return $this
     */
    public function updateWhereData($data): QueryBuilder {
        $this->performed = false;
        $this->whereData = array_merge($this->whereData, $data);

        return $this;
    }

    /**
     * get the where condition.
     *
     * @return \wulaphp\db\sql\Condition
     */
    public function getCondition(): ?Condition {
        return $this->where;
    }

    /**
     * alias of getCondition.
     *
     * @return \wulaphp\db\sql\Condition
     */
    public function getWhere(): ?Condition {
        return $this->where;
    }

    /**
     * @param $field
     *
     * @return $this
     */
    public function asc($field): QueryBuilder {
        $this->order [] = [$field, 'a'];

        return $this;
    }

    /**
     * @param $field
     *
     * @return $this
     */
    public function desc($field): QueryBuilder {
        $this->order [] = [$field, 'd'];

        return $this;
    }

    /**
     * 排序,多个排序字段用','分隔.
     *
     * 当<code>$field</code>为null时，尝试从请求中读取sort[name]做为$field，sort[dir] 做为$order.
     *
     * @param string|array|null $field 排序字段，多个字段使用,分隔.
     * @param string            $order a or d
     *
     * @return $this
     */
    public function sort($field = null, string $order = 'a'): QueryBuilder {
        if ($field === null) {
            $field = rqst('sort.name');
            $order = rqst('sort.dir', 'a');
        }
        if ($field) {
            if (is_string($field)) {
                $orders = explode(',', strtolower($order));
                $fields = explode(',', $field);
                foreach ($fields as $i => $field) {
                    $this->order [] = [$field, isset($orders[ $i ]) ? $orders[ $i ] : $orders[0]];
                }
            } else if (is_array($field)) {
                $this->sort($field[0], isset($field[1]) ? $field[1] : 'a');
            }
        }

        return $this;
    }

    /**
     * limit.
     *
     * @param int      $start start position or limit.
     * @param int|null $limit
     *
     * @return $this
     */
    public function limit(int $start, ?int $limit = null): QueryBuilder {
        if ($limit === null) {
            $limit = intval($start);
            $start = 0;
        } else {
            $start = intval($start);
            $limit = intval($limit);
        }
        if ($start < 0) {
            $start = 0;
        }
        if (!$limit) {
            $limit = 1;
        }
        $this->limit = [$start, $limit];
        if ($this->statement) {
            $this->updateWhereData([':limit_0' => $start, ':limit_1' => $limit]);
        }

        return $this;
    }

    /**
     * 分页.
     * 如果$pageNo等于null，那么直接读取page[page]做为$pageNo和page[size]做为$size.
     *
     * @param int|null $pageNo 页数,从1开始.
     * @param int      $size   默认每页20条
     *
     * @return $this
     * @see QueryBuilder::limit()
     *
     */
    public function page($pageNo = null, $size = 20): QueryBuilder {
        if ($pageNo === null) {
            $pageNo = irqst('pager.page', 1);
            $size   = irqst('pager.size', 20);
        }
        $pageNo = intval($pageNo);
        if ($pageNo <= 0) {
            $pageNo = 1;
        }

        return $this->limit(($pageNo - 1) * $size, $size);
    }

    /**
     * @param $alias
     *
     * @return $this
     */
    public function alias($alias): QueryBuilder {
        $this->alias = $alias;

        return $this;
    }

    /**
     * 获取别名.
     *
     * @return string the alias of the table this query used.
     */
    public function getAlias(): string {
        return $this->alias;
    }

    /**
     * get the dialect binding with this query.
     *
     * @return \wulaphp\db\dialect\DatabaseDialect
     */
    public function getDialect(): ?DatabaseDialect {
        try {
            $this->checkDialect();

            return $this->dialect;
        } catch (DialectException $e) {
            return null;
        }
    }

    /**
     * 检测数据库连接是否有效.
     *
     * @throws \wulaphp\db\DialectException
     */
    protected function checkDialect() {
        if (!$this->dialect instanceof DatabaseDialect) {
            throw new DialectException('Cannot connect to database server');
        }
    }

    /**
     * @return \wulaphp\db\sql\BindValues
     */
    public function getBindValues(): ?BindValues {
        return $this->values;
    }

    /**
     * @param BindValues $values
     */
    public function setBindValues(BindValues $values) {
        $this->values     = $values;
        $this->valueFixed = true;
    }

    /**
     * 设置PDO option,只影响PDOStatement。
     *
     * @param array $options
     */
    public function setPDOOptions(array $options) {
        $this->options = $options;
    }

    /**
     * 最后错误信息
     * @return string|boolean
     */
    public function lastError() {
        return $this->error;
    }

    /**
     * 最后错误信息
     * @return string|boolean
     * @see \wulaphp\db\sql\QueryBuilder::lastError()
     */
    public function error() {
        return $this->error;
    }

    /**
     * @return \Exception
     */
    public function lastExp(): ?\Exception {
        return $this->exception;
    }

    /**
     * 最后出错的SQL.
     *
     * @return string
     */
    public function lastSQL(): string {
        return $this->errorSQL;
    }

    /**
     * 最后出错时的数据.
     *
     * @return array
     */
    public function lastValues(): ?array {
        return $this->errorValues;
    }

    /**
     * 获取\PDOStatement实例dump的调试信息.
     *
     * @param \PDOStatement|null $statement
     *
     * @return mixed
     * @deprecated 请使用getSqlString
     */
    public function dumpSQL(\PDOStatement $statement = null) {
        if ($statement) {
            @ob_start(PHP_OUTPUT_HANDLER_CLEANABLE);
            $statement->debugDumpParams();
            $this->dumpSQL = @ob_get_clean();

            return null;
        } else {
            return $this->dumpSQL;
        }
    }

    /**
     * 添加执行SQL记数
     * @deprecated
     */
    public static function addSqlCount() {
        self::$sqlCount ++;
    }

    /**
     * 获取执行的SQL语句数量.
     *
     * @return int
     * @deprecated
     */
    public static function getSqlCount(): int {
        return self::$sqlCount;
    }

    /**
     * 清洗数据.
     *
     * @param array|string $var
     *
     * @return array|string
     */
    protected function sanitize($var) {
        try {
            $this->checkDialect();
        } catch (DialectException $e) {
            return $var;
        }

        if (is_string($var)) {
            return $this->dialect->sanitize($var);
        } else if (is_array($var)) {
            array_walk_recursive($var, function (&$item) {
                if (is_string($item)) {
                    $item = $this->dialect->sanitize($item);
                }
            });

            return $var;
        } else {
            return $var;
        }
    }

    /**
     * 解析AS语句.
     *
     * @param string      $str
     * @param null|string $alias1
     *
     * @return array
     */
    protected static function parseAs(string $str, ?string $alias1 = null): array {
        $table = preg_split('#\b(as|\s+)\b#i', trim($str));
        if (count($table) == 1) {
            $name  = $table [0];
            $alias = $alias1;
        } else {
            $name  = $table [0];
            $alias = trim(array_pop($table));
        }
        if ($alias) {
            $alias = '`' . trim($alias, ' `"') . '`';
        }

        return [trim($name), $alias];
    }

    /**
     * 解析表.
     *
     * @param array $froms
     *
     * @return array
     */
    protected function prepareFrom(array $froms): array {
        $_froms = [];
        if ($froms) {
            foreach ($froms as $from) {
                $table     = $this->dialect->getTableName($from [0]);
                $alias     = empty ($from [1]) ? $table : $from [1];
                $_froms [] = [$table, $alias];
            }
        }

        return $_froms;
    }

    /**
     * 解析连接查询.
     *
     * @param array $joins
     *
     * @return array
     */
    protected function prepareJoins(array $joins): array {
        $_joins = [];
        if ($joins) {
            foreach ($joins as $join) {
                $table     = $this->dialect->getTableName($join [0]);
                $alias     = empty ($join [3]) ? $table : $join [3];
                $_joins [] = [$table, $join [1], $join [2], $alias];
            }
        }

        return $_joins;
    }

    /**
     * prepare the fields in select SQL
     *
     * @param array      $fields
     * @param BindValues $values
     *
     * @return string
     */
    protected function prepareFields(array $fields, BindValues $values): ?string {
        $_fields = [];
        foreach ($fields as $field) {
            if ($field instanceof Query) { // sub-select SQL as field
                $field->setDialect($this->dialect);
                $field->setBindValues($values);
                $as = trim($field->getAlias(), '`"');
                if ($as) {
                    $_fields [] = '(' . $field . ') AS ' . $this->dialect->sanitize('`' . $as . '`');
                }
            } else if ($field instanceof ImmutableValue) {
                $_fields [] = $field->getValue($this->dialect);
            } else { // this is simple field
                $_fields [] = $this->dialect->sanitize($field);
            }
        }
        if ($_fields) {
            return implode(',', $_fields);
        } else {
            return null;
        }
    }

    /**
     * 执行方法.
     * @return mixed
     */
    public abstract function count();

    /**
     * 最后执行的SQL语句.
     * @return string
     */
    public abstract function getSqlString();
}
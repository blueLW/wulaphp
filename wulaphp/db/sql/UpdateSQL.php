<?php

namespace wulaphp\db\sql;

/**
 * update SQL
 *
 * @author guangfeng.ning
 *
 */
class UpdateSQL extends QueryBuilder {
    use CudTrait;

    private $data  = [];
    private $batch = false;

    /**
     * 创建一个更新语句.
     *
     * @param string|array $table
     */
    public function __construct($table) {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->from [] = self::parseAs($t);
            }
        } else {
            $this->from [] = self::parseAs($table);
        }
    }

    /**
     * 更新表（同update）。
     *
     * @param string $table
     *
     * @return $this
     */
    public function table($table) {
        $this->from[] = self::parseAs($table);

        return $this;
    }

    /**
     * 更新表。
     *
     * @param string $table
     *
     * @return $this
     */
    public function update($table) {
        $this->from[] = self::parseAs($table);

        return $this;
    }

    /**
     * 连表(同@param string $table
     *
     * @return $this
     * @see \wulaphp\db\sql\UpdateSQL::table()).
     *
     */
    public function from($table) {
        $this->from[] = self::parseAs($table);

        return $this;
    }

    /**
     * the data to be updated
     *
     * @param array $data
     * @param bool  $batch
     *
     * @return $this
     */
    public function set(array $data, $batch = false) {
        $this->data  = $data;
        $this->batch = $batch;

        return $this;
    }

    /**
     * 执行
     * @return bool|int
     */
    public function count() {
        if ($this->whereSet && empty($this->where)) {
            return false;
        }
        $sql    = $this->getSQL();
        $values = $this->values;
        if ($sql) {
            $statement = null;
            try {
                try {
                    $statement = $this->dialect->prepare($sql);
                } catch (\Exception $e) {
                    if ($this->retriedCnt == 0 && $e->getCode() == 'HY000') {
                        try {
                            $this->dialect    = $this->dialect->reset();
                            $this->retriedCnt = 1;

                            return $this->count();
                        } catch (\Exception $e1) {
                            $this->exception   = $e1;
                            $this->error       = $e1->getMessage();
                            $this->errorSQL    = $sql;
                            $this->errorValues = $values->__toString();

                            return false;
                        }
                    } else {
                        $this->exception   = $e;
                        $this->error       = $e->getMessage();
                        $this->errorSQL    = $sql;
                        $this->errorValues = $values->__toString();

                        return false;
                    }
                }
                $cnt = false;
                if ($this->batch) {
                    $cnt = 0;
                    foreach ($this->data as $data) {
                        [$da, $where] = $data;
                        foreach ($values as $value) {
                            [$name, $val, $type, , $rkey] = $value;
                            $rval = isset($where[ $rkey ]) ? $where[ $rkey ] : (isset($da[ $rkey ]) ? $da[ $rkey ] : $val);
                            if (!$statement->bindValue($name, $rval, $type)) {
                                $this->errorSQL    = $sql;
                                $this->errorValues = $values->__toString();
                                $this->error       = 'can not bind the value ' . $rval . '[' . $type . '] to the argument:' . $name;

                                return false;
                            }
                        }
                        $rst = $statement->execute();
                        QueryBuilder::addSqlCount();

                        if ($rst) {
                            $cnt += $statement->rowCount();
                        } else {
                            $this->dumpSQL($statement);

                            break;
                        }
                    }
                } else {
                    foreach ($values as $value) {
                        [$name, $val, $type] = $value;
                        if (!$statement->bindValue($name, $val, $type)) {
                            $this->errorSQL    = $sql;
                            $this->errorValues = $values->__toString();
                            $this->error       = 'can not bind the value ' . $val . '[' . $type . '] to the argument:' . $name;

                            return false;
                        }
                    }
                    $rst = $statement->execute();
                    QueryBuilder::addSqlCount();

                    if ($rst) {
                        $cnt = $statement->rowCount();
                    } else {
                        $this->dumpSQL($statement);
                    }
                }

                return $cnt;
            } catch (\PDOException $e) {
                $this->exception   = $e;
                $this->error       = $e->getMessage();
                $this->errorSQL    = $sql;
                $this->errorValues = $values->__toString();

                return false;
            } finally {
                if ($statement) {
                    $statement->closeCursor();
                    $statement = null;
                }
            }
        } else {
            $this->error       = 'Can not generate the delete SQL';
            $this->errorSQL    = '';
            $this->errorValues = $values->__toString();
        }

        return false;
    }

    protected function getSQL() {
        if ($this->sql) {
            return $this->sql;
        }

        if (empty ($this->from)) {
            $this->error = 'no table specified!';

            return false;
        }
        try {
            $this->checkDialect();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();

            return false;
        }

        if (!$this->values) {
            $this->values = new BindValues ();
        } else if (!$this->valueFixed) {
            $this->values->reset();
        }

        $froms = $this->prepareFrom($this->sanitize($this->from));
        $order = $this->sanitize($this->order);
        $ids   = array_keys($this->data);
        $data  = $this->batch ? $this->data[ $ids [0] ][0] : $this->data;
        if ($this->batch) {
            $this->where($this->data[ $ids [0] ][1]);
        }
        $this->sql = $this->dialect->getUpdateSQL($froms, $data, $this->where, $this->values, $order, $this->limit);

        return $this->sql;
    }
}
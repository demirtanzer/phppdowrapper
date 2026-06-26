<?php

class db extends PDO
{
    private $error;
    private $sql;
    private $bind;
    private $errorCallbackFunction;
    private $errorMsgFormat;

    private function debug()
    {
        if (!empty($this->errorCallbackFunction)) {
            $error = ['Error' => $this->error];
            if (!empty($this->sql)) {
                $error['SQL Statement'] = $this->sql;
            }
            if (!empty($this->bind)) {
                $error['Bind Parameters'] = trim(print_r($this->bind, true));
            }
            ini_set('memory_limit', '256M');
            $backtrace = debug_backtrace();
            if (!empty($backtrace)) {
                foreach ($backtrace as $info) {
                    if ($info['file'] != __FILE__) {
                        $error['Backtrace'] = $info['file'] . ' at line ' . $info['line'];
                    }
                }
            }
            $msg = '';
            if ($this->errorMsgFormat == 'html') {
                if (!empty($error['Bind Parameters'])) {
                    $error['Bind Parameters'] = '<pre>' . $error['Bind Parameters'] . '</pre>';
                }
                $cssFile = __DIR__ . '/error.css';
                $css = is_file($cssFile) ? trim(file_get_contents($cssFile)) : '';
                $msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
                $msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
                foreach ($error as $key => $val) {
                    $msg .= "\n\t<label>" . $key . ':</label>' . $val;
                }
                $msg .= "\n\t</div>\n";
            } elseif ($this->errorMsgFormat == 'text') {
                $msg .= "SQL Error\n" . str_repeat('-', 50);
                foreach ($error as $key => $val) {
                    $msg .= "\n\n$key:\n$val";
                }
            }
            $func = $this->errorCallbackFunction;
            $func($msg);
        }
    }

    private function filter($table, $info)
    {
        $driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        $quotedTable = $this->quoteIdentifier($table);
        $tableName = $this->getTableName($table);
        if ($driver == 'sqlite') {
            $sql = 'PRAGMA table_info(' . $quotedTable . ');';
            $key = 'name';
        } elseif ($driver == 'mysql') {
            $sql = 'DESCRIBE ' . $quotedTable . ';';
            $key = 'Field';
        } else {
            $sql = 'SELECT column_name FROM information_schema.columns WHERE table_name = :table;';
            $key = 'column_name';
        }
        $bind = ($driver == 'sqlite' || $driver == 'mysql') ? '' : [':table' => $tableName];
        if (false !== ($list = $this->run($sql, $bind))) {
            $fields = [];
            foreach ($list as $record) {
                $fields[] = $record[$key];
            }
            return array_values(array_intersect($fields, array_keys($info)));
        }
        return [];
    }

    private function cleanup($bind)
    {
        if (!is_array($bind)) {
            if (!empty($bind)) {
                $bind = [$bind];
            } else {
                $bind = [];
            }
        }
        return $bind;
    }

    private function quoteIdentifier($identifier)
    {
        $identifier = trim($identifier);
        $quote = $this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' ? '`' : '"';
        $parts = explode('.', $identifier);

        foreach ($parts as &$part) {
            $part = trim($part);
            if (strlen($part) >= 2 && $part[0] == '`' && substr($part, -1) == '`') {
                $part = substr($part, 1, -1);
            }
            if (strlen($part) >= 2 && $part[0] == '"' && substr($part, -1) == '"') {
                $part = substr($part, 1, -1);
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
            }
            $part = $quote . str_replace($quote, $quote . $quote, $part) . $quote;
        }
        unset($part);

        return implode('.', $parts);
    }

    private function getTableName($table)
    {
        $table = trim($table);
        $parts = explode('.', $table);
        $table = end($parts);

        if (strlen($table) >= 2 && $table[0] == '`' && substr($table, -1) == '`') {
            $table = substr($table, 1, -1);
        }
        if (strlen($table) >= 2 && $table[0] == '"' && substr($table, -1) == '"') {
            $table = substr($table, 1, -1);
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Invalid SQL identifier: ' . $table);
        }

        return $table;
    }

    private function buildWhere($where, $bind = '')
    {
        if (!is_array($where)) {
            return [$where, $this->cleanup($bind)];
        }

        $clauses = [];
        $bind = $this->cleanup($bind);
        $index = 0;

        foreach ($where as $field => $value) {
            $operator = '=';
            if (preg_match('/^(.+?)\s+(=|<>|!=|>|>=|<|<=|LIKE|NOT LIKE)$/i', trim($field), $matches)) {
                $field = $matches[1];
                $operator = strtoupper($matches[2]);
            }

            $quotedField = $this->quoteIdentifier($field);

            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $item) {
                    $placeholder = ':where_' . $index++;
                    $placeholders[] = $placeholder;
                    $bind[$placeholder] = $item;
                }
                $clauses[] = $quotedField . ' IN (' . implode(', ', $placeholders) . ')';
                continue;
            }

            if ($value === null) {
                $clauses[] = $quotedField . (in_array($operator, ['!=', '<>']) ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }

            $placeholder = ':where_' . $index++;
            $clauses[] = $quotedField . ' ' . $operator . ' ' . $placeholder;
            $bind[$placeholder] = $value;
        }

        return [implode(' AND ', $clauses), $bind];
    }

    public function __construct($host, $dbname, $charset = '', $user = '', $passwd = '')
    {
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ];
        try {
            parent::__construct('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset, $user, $passwd, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function delete($table, $where, $bind = '')
    {
        [$where, $bind] = $this->buildWhere($where, $bind);
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $where . ';';
        return $this->run($sql, $bind) !== false;
    }

    public function insert($table, $info)
    {
        $fields = $this->filter($table, $info);
        $columns = [];
        $placeholders = [];
        $bind = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':field_' . $index;
            $columns[] = $this->quoteIdentifier($field);
            $placeholders[] = $placeholder;
            $bind[$placeholder] = $info[$field];
        }
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ');';
        $result = $this->run($sql, $bind);
        if ($result !== false) {
            return $this->lastInsertId();
        }
        return false;
    }

    public function run($sql, $bind = '')
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind);
        $this->error = '';

        try {
            $pdostmt = $this->prepare($this->sql);

            if ($pdostmt === false) {
                throw new RuntimeException('SQL prepare failed');
            }

            if ($pdostmt->execute($this->bind) !== false) {
                if (preg_match('/^(select|describe|pragma|show)\b/i', $this->sql)) {
                    return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
                }

                if (preg_match('/^(delete|insert|update)\b/i', $this->sql)) {
                    return $pdostmt->rowCount();
                }
            }

            return false;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            if (!defined('_OFFLINE') || _OFFLINE !== true) {
                $this->debug();
            }

            return false;
        }
    }

    public function select($table, $where = '', $bind = '', $fields = '*')
    {
        if (is_array($fields)) {
            $fields = implode(', ', array_map([$this, 'quoteIdentifier'], $fields));
        }
        [$where, $bind] = $this->buildWhere($where, $bind);
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->quoteIdentifier($table);
        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ';';
        return $this->run($sql, $bind);
    }

    public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat = 'html')
    {
        if (is_string($errorCallbackFunction) && in_array(strtolower($errorCallbackFunction), ['echo', 'print'])) {
            $errorCallbackFunction = 'print_r';
        }
        if (is_callable($errorCallbackFunction)) {
            $this->errorCallbackFunction = $errorCallbackFunction;
            if (!in_array(strtolower($errorMsgFormat), ['html', 'text'])) {
                $errorMsgFormat = 'html';
            }
            $this->errorMsgFormat = $errorMsgFormat;
        }
    }

    public function update($table, $info, $where, $bind = '')
    {
        $fields = $this->filter($table, $info);
        $fieldSize = sizeof($fields);
        [$where, $bind] = $this->buildWhere($where, $bind);
        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ';
        for ($f = 0; $f < $fieldSize; ++$f) {
            if ($f > 0) {
                $sql .= ', ';
            }
            $sql .= $this->quoteIdentifier($fields[$f]) . ' = :update_' . $f;
        }
        $sql .= ' WHERE ' . $where . ';';
        foreach ($fields as $index => $field) {
            $bind[':update_' . $index] = $info[$field];
        }
        return $this->run($sql, $bind);
    }

    public function upsert($table, $info, $uniqueKey)
    {
        $fields = $this->filter($table, $info);
        $columns = [];
        $placeholders = [];
        $bind = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':field_' . $index;
            $columns[] = $this->quoteIdentifier($field);
            $placeholders[] = $placeholder;
            $bind[$placeholder] = $info[$field];
        }
        $updates = [];
        foreach ($fields as $f) {
            if ($f !== $uniqueKey) {
                $quotedField = $this->quoteIdentifier($f);
                $updates[] = $quotedField . ' = VALUES(' . $quotedField . ')';
            }
        }
        $quotedUniqueKey = $this->quoteIdentifier($uniqueKey);
        $updates[] = $quotedUniqueKey . ' = LAST_INSERT_ID(' . $quotedUniqueKey . ')';
        $updateSql = implode(', ', $updates);
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE $updateSql;";
        $result = $this->run($sql, $bind);
        if ($result !== false) {
            return $this->lastInsertId();
        }
        return false;
    }
}

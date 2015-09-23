#!/usr/bin/env php
<?php
/**
 * uniconv
 *
 * Command Line universal converter for any PDO-available databases
 *
 * @copyright   2015 Robin de Graaf, devvoh webdevelopment
 * @license     MIT
 * @author      Robin de Graaf (hello@devvoh.com)
 */

class cli {

    static $parameters = array();
    static $lastProgressLength = 0;
    static $lines = array();

    public static function write($message) {
        echo $message . PHP_EOL;
    }

    public static function dump($message) {
        print_r($message);
        echo PHP_EOL;
    }

    public static function addLine($message) {
        self::$lines[] = $message;
    }

    public static function writeLines() {
        $output = implode(self::$lines, PHP_EOL);
        self::write($output);
    }

    public static function nl() {
        echo PHP_EOL;
    }

    public static function parseParameters($params) {
        // Check for parameters given
        for ($i = 1; $i < count($params); $i++) {
            if (substr($params[$i], 0, 1) === '-') {
                // set the current param as key and the next one as value
                $key = str_replace('-', '', $params[$i]);
                self::$parameters[$key] = $params[$i+1];
                // and skip the value
                $i++;
            } else {
                // Set the parameters as key and true as value
                self::$parameters[$params[$i]] = true;
            }
        }
    }

    public static function getParameters() {
        return self::$parameters;
    }

    public static function getParameter($key) {
        if (isset(self::$parameters[$key])) {
            return self::$parameters[$key];
        }
        return null;
    }

    public static function yesNo($question, $default = true) {
        // output question and appropriate default value
        echo trim($question) . ($default ? ' [Y/n] ' : ' [y/N] ');
        // get user input from stdin
        $line = fgets(STDIN);
        // turn into lowercase and check specifically for yes and no, call ourselves again if neither
        $value = strtolower(trim($line));

        if (in_array($value, array('y', 'yes'))) {
            return true;
        } elseif (in_array($value, array('n', 'no'))) {
            return false;
        } elseif (empty($value)) {
            // but if it's empty, assume default
            return $default;
        }
        // If nothing has been returned so far, keep asking
        echo "Enter y/yes or n/no.\n";
        return self::yesNo($question, $default);
    }

    public static function progress($message) {
        if (self::$lastProgressLength > 0) {
            echo "\e[" . self::$lastProgressLength . "D";
        }
        self::$lastProgressLength = strlen($message);
        echo $message;
    }

    public static function end() {
        self::writeLines();
        exit;
    }

}

class config {

    public static $filename = null;
    public static $config = null;

    public static function setFilename($filename) {
        self::$filename = $filename;
    }

    public static function getFilename() {
        return self::$filename;
    }

    public static function load() {
        if (!self::$filename) {
            cli::addLine('No config filename given. Use uniconv -config config.json');
            return false;
        }
        if (!file_exists(self::$filename)) {
            cli::addLine('Config file does not exist: ' . self::$filename);
            return false;
        }
        self::$config = json_decode(file_get_contents(self::$filename), true);
        if (!self::$config) {
            cli::addLine('Invalid config file: ' . self::$filename);
            return false;
        }

        // Now set the relevant config values on the converter
        converter::setSettings(self::$config['config']['settings']);
        converter::setDbSource(self::$config['config']['databases']['source']);
        converter::setDbTarget(self::$config['config']['databases']['target']);
        converter::setConversions(self::$config['conversions']);
        return true;
    }

    public static function getConfig($key = null) {
        return self::$config;
    }

}

class converter {

    public static $settings = array();
    public static $dbSource = array();
    public static $dbTarget = array();
    public static $conversions = array();

    public static function setSettings($settings) {
        self::$settings['pass_size']        = (int)$settings['pass_size'];
        self::$settings['ignore_errors']    = (int)$settings['ignore_errors'];
        self::$settings['log_file']         = (int)$settings['log_file'];
    }

    public static function getSettings($key) {
        if ($key) {
            if (isset(self::$settings[$key])) {
                return self::$settings[$key];
            } else {
                return null;
            }
        }
        return self::$settings;
    }

    public static function setDbSource($dbSource) {
        self::$dbSource = $dbSource;
    }

    public static function getDbSource($key = null) {
        if ($key) {
            if (isset(self::$dbSource[$key])) {
                return self::$dbSource[$key];
            } else {
                return null;
            }
        }
        return self::$dbSource;

    }

    public static function setDbTarget($dbTarget) {
        self::$dbTarget = $dbTarget;
    }

    public static function getDbTarget($key = null) {
        if ($key) {
            if (isset(self::$dbTarget[$key])) {
                return self::$dbTarget[$key];
            } else {
                return null;
            }
        }
        return self::$dbTarget;
    }

    public static function setConversions($conversions) {
        self::$conversions = $conversions;
    }

    public static function getConversions($key = null) {
        if ($key) {
            if (isset(self::$conversions[$key])) {
                return self::$conversions[$key];
            } else {
                return null;
            }
        }
        return self::$conversions;
    }

}

class query {

    protected $db;
    protected $tableName;
    protected $tableKey;
    protected $select = '*';
    protected $action = 'select';
    protected $where = array();
    protected $values = array();
    protected $orderBy = array();
    protected $groupBy = array();
    protected $limit;

    /**
     * On construct, if we're given an object, use this object to set the tableName & key
     *
     * @param stdClass $object
     * @return query
     */
    public function __construct($object = null) {
        if ($object !== null && is_object($object)) {
            $this->setTableName($object->getTableName());
            $this->setTableKey($object->getTableKey());
        }
        return $this;
    }

    /**
     * Set the tableName to work on
     *
     * @param string $tableName
     * @return query
     */
    public function setTableName($tableName) {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * Get the currently set tableName
     *
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Set the tableKey to work with (for delete & update
     * )
     * @param string $key
     * @return query
     */
    public function setTableKey($key) {
        $this->tableKey = $key;
        return $this;
    }

    /**
     * Set the type of query we're going to do
         *
         * @param string $action (select, insert, delete, update)
         */
     public function setAction($action) {
         if (in_array($action, array('select', 'insert', 'delete', 'update'))) {
             $this->action = $action;
         }
         return $this;
     }

     /**
      * In case of a select, what we're going to select (default *)
      *
      * @param string $select
      * @return query
      */
     public function select($select) {
         $this->select = $select;
         return $this;
     }

     /**
      * Adds a where condition for relevant queries
      *
      * @param string $condition
      * @param mixed $value
      * @return query
      */
     public function where($condition, $value = null) {
         $this->where[] = array('condition' => $condition, 'value' => $value);
         return $this;
     }

     /**
      * Adds a value to update/insert queries
      *
      * @param string $key
      * @param mixed $value
      * @return query
      */
     public function addValue($key, $value) {
         $this->values[] = array('key' => $key, 'value' => $value);
         return $this;
     }

     /**
      * Sets the order for select queries
      *
      * @param string $key
      * @param string $direction (default DESC)
      * @return query
      */
     public function orderBy($key, $direction = 'DESC') {
         $this->orderBy[] = array('key' => $key, 'direction' => $direction);
         return $this;
     }

     /**
      * Sets the group by for select queries
      *
      * @param string $key
      * @return query
      */
     public function groupBy($key) {
         $this->groupBy[] = $key;
         return $this;
     }

     /**
      * Sets the limit
      *
      * @param int $limit
      * @param int $offset
      * @return query
      */
     public function limit($limit, $offset = null) {
         $this->limit = array('limit' => $limit, 'offset' => $offset);
         return $this;
     }

     /**
      * Sets a db to quote on
      *
      * @param PDO $db
      * @return query
      */
     public function setDb($db) {
         $this->db = $db;
         return $this;
     }

     /**
      * Outputs the actual query for use, empty string if invalid/incomplete values given
      *
      * @return string
      */
     public function __toString() {
         $query = array();

         if ($this->action === 'select') {

             // set select & what needs to be selected
             $query[] = "SELECT " . $this->select;
             // table
             $query[] = "FROM " . $this->tableName;

             // now get the where clauses
             if (count($this->where) > 0) {
                 $wheres = array();
                 foreach ($this->where as $where) {
                     if ($where['value'] !== null) {
                         $wheres[] = str_replace('?', app::getDb()->quote($where['value']), $where['condition']);
                     } else {
                         $wheres[] = $where['condition'];
                     }
                 }
                 $query[] = "WHERE " . implode(' AND ', $wheres);
             }

             // now get the order(s)
             if (count($this->orderBy) > 0) {
                 $orders = array();
                 foreach ($this->orderBy as $orderBy) {
                     $orders[] = $orderBy['key'] . ' ' . $orderBy['direction'];
                 }
                 $query[] = "ORDER BY " . implode(', ', $orders);
             }

             // now get the group(s)
             if (count($this->groupBy) > 0) {
                 $groups = array();
                 foreach ($this->groupBy as $groupBy) {
                     $groups[] = $groupBy;
                 }
                 $query[] = "GROUP BY " . implode(', ', $groups);
             }

             // and the limit
             if (is_array($this->limit)) {
                 if ($this->limit['offset'] !== null && $this->limit['limit'] !== null) {
                     $query[] = "LIMIT " . $this->limit['offset'] . ", " . $this->limit['limit'];
                 } elseif ($this->limit['limit'] !== null) {
                     $query[] = "LIMIT " . $this->limit['limit'];
                 }
             }

         } elseif ($this->action === 'delete') {

             // set delete to the proper table
             $query[] = "DELETE FROM " . $this->tableName;

             // now get the where clauses
             if (count($this->where) > 0) {
                 $wheres = array();
                 foreach ($this->where as $where) {
                     if ($where['value'] !== null) {
                         $wheres[] = str_replace('?', app::getDb()->quote($where['value']), $where['condition']);
                     } else {
                         $wheres[] = $where['condition'];
                     }
                 }
                 $query[] = "WHERE " . implode(' AND ', $wheres);
             } else {
                 $query = [];
             }

         } elseif ($this->action === 'update') {

             // set update to the proper table
             $query[] = "UPDATE " . $this->tableName;

             // now get the values
             if (count($this->values) > 0) {
                 $values = array();
                 foreach ($this->values as $value) {
                     // skip id, since we'll use that as a where condition
                     if ($value['key'] !== $this->tableKey) {
                         $values[] = "'" . $value['key'] . "'=" . $this->db->quote($value['value']);
                     } else {
                         $tableKey = $value['key'];
                         $tableKeyValue = $value['value'];
                     }
                 }
                 $query[] = "SET " . implode(',', $values);
                 $query[] = "WHERE " . $tableKey . " = " . $this->db->quote($tableKeyValue);
             } else {
                 $query = [];
             }

         } elseif ($this->action === 'insert') {

             // set insert to the proper table
             $query[] = "INSERT INTO " . $this->tableName;

             // now get the values
             if (count($this->values) > 0) {
                 foreach ($this->values as $value) {
                     $keys[] = "'" . $value['key'] . "'";
                     $values[] = $this->db->quote($value['value']);
                 }

                 $query[] = "(" . implode(',', $keys) . ")";
                 $query[] = "VALUES";
                 $query[] = "(" . implode(',', $values) . ")";
             } else {
                 $query = [];
             }

         }

         // and now implode it into a nice string, if possible
         if (count($query) > 0) {
             return implode(' ', $query);
         }
         return '';

     }

}

class generatePDO {

    public static function get($config) {
        switch ($config['type']) {
            case 'mysql':
                $pdoString  = 'mysql:host=' . $config['location'];
                $pdoString .= ';dbname=' . $config['database'];
                $pdo = new PDO($pdoString, $config['user'], $config['password']);
                break;
            case 'sqlite':
                $pdo = new PDO('sqlite:' . $config['location']);
                break;
        }
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

}

cli::parseParameters($argv);

config::setFilename(cli::getParameter('config'));
if (!config::load()) {
    cli::end();
}

// Make database connections - first source
$dbSource = generatePDO::get(converter::getDbSource());
$dbTarget = generatePDO::get(converter::getDbTarget());

foreach (converter::getConversions() as $entity => $data) {
    $start = microtime(true);
    // Now get all the values from the data
    $sourceTable = $data['tables']['source'];
    $targetTable = $data['tables']['target'];
    $fields      = $data['fields'];

    // Decide whether there's a condition to take into account
    $where = null;
    if (isset($data['tables']['where'])) {
        $where = explode(' ', $data['tables']['where']);
        if ($data['tables']['wheretype'] == 'value') {
            $key = $where[0] . ' ' . $where[1];
            $value = $dbTarget->quote($where[2]);
            $where = $key . $value;
        } elseif ($data['tables']['wheretype'] == 'fields') {
            $where = implode($where, ' ');
        }
    }

    // Now get all the entities from the source table
    $select = 'select * from ' . $sourceTable . ($where ? ' where ' . $where : '');
    $rows = $dbSource->query($select)->fetchAll();
    $i = 1;
    foreach ($rows as $row) {
        cli::progress($entity . ' ' . $i . '/' . count($rows) . '...');
        $query = (new query())->setDb($dbTarget)->setTableName($targetTable)->setAction('insert');
        foreach ($fields as $fieldData) {
            $value = $row[$fieldData['source']];
            if (isset($fieldData['convert'])) {
                switch ($fieldData['convert']) {
                    case 'timestamp_to_datetime':
                        $dateTime = new DateTime();
                        $dateTime->setTimestamp($value);
                        $value = $dateTime->format('Y-m-d H:i:s');
                        break;
                }
            }
            $query->addValue($fieldData['target'], $value);
        }
        $dbTarget->query($query);
        $i++;
    }
    $time = microtime(true) - $start;
    cli::progress($entity . ' ' . ($i - 1) . '/' . count($rows) . '... finished in ' . number_format($time, 3) . 's');
    cli::nl();
}
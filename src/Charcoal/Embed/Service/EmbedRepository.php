<?php

namespace Charcoal\Embed\Service;

use Charcoal\Embed\Mixin\EmbedAwareTrait;
use Exception;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use InvalidArgumentException;

/**
 * Embed Repository
 *
 * - Store scraped data from embed/embed in a provided database table.
 */
class EmbedRepository implements
    LoggerAwareInterface
{
    use LoggerAwareTrait;
    use EmbedAwareTrait;

    const DEFAULT_TABLE = 'embed_cache';

    const MYSQL_DRIVER_NAME = 'mysql';
    const SQLITE_DRIVER_NAME = 'sqlite';

    /**
     * @var string
     */
    private $table;

    /**
     * The database connector.
     *
     * @var PDO
     */
    private $pdo;

    /**
     * The PSR-6 caching service.
     *
     * @var CacheItemPoolInterface
     */
    private $cachePool;

    // INIT
    // ==========================================================================

    /**
     * @param array $data Dependencies.
     * @throws Exception If missing dependencies.
     * @return self
     */
    public function __construct(array $data)
    {
        $this->pdo = $data['pdo'];
        $this->setLogger($data['logger']);

        if (isset($data['table'])) {
            $this->setTable($data['table']);
        }

        return $this;
    }


    // Methods
    // ==========================================================================

    /**
     * @param string $ident  The embed ident to save from.
     * @param string $format The embed format (null, src, array) @see{Charcoal\Embed\Mixin\EmbedAwareTrait}.
     * @return mixed
     */
    public function saveEmbedData($ident, $format = null)
    {
        // Check if exist and return it
        $item = $this->load($ident);
        if ($item) {
            return $item;
        }

        // Run through embed service.
        $embedItem = $this->processEmbed($ident, $format);
        $this->saveItem($embedItem);

        return $embedItem;
    }

    /**
     * @param string $ident  The embed ident to process.
     * @param string $format The embed format (null, src, array) @see{Charcoal\Embed\Mixin\EmbedAwareTrait}.
     * @return array
     */
    public function processEmbed($ident, $format = null)
    {
        return [
            'ident'      => $ident,
            'embed_data' => $this->formatEmbed($ident, $format)
        ];
    }

    /**
     * Retrieve the database connector.
     *
     * @throws RuntimeException If the database was not set.
     * @return PDO
     */
    public function db()
    {
        if ($this->pdo === null) {
            throw new RuntimeException(
                'Database Connector was not set.'
            );
        }

        return $this->pdo;
    }

    /**
     * Execute a SQL query, with PDO, and returns the PDOStatement.
     *
     * If the query fails, this method will return false.
     *
     * @param  string $query The SQL query to executed.
     * @param  array  $binds Optional. Query parameter binds.
     * @param  array  $types Optional. Types of parameter bindings.
     * @return \PDOStatement|false The PDOStatement, otherwise FALSE.
     */
    public function dbQuery($query, array $binds = [], array $types = [])
    {
        $this->logger->debug($query, $binds);
        $sth = $this->db()->prepare($query);
        if (!$sth) {
            return false;
        }

        if (!empty($binds)) {
            foreach ($binds as $key => $val) {
                if ($binds[$key] === null) {
                    $types[$key] = PDO::PARAM_NULL;
                } elseif (!is_scalar($binds[$key])) {
                    $binds[$key] = json_encode($binds[$key]);
                }
                $type  = (isset($types[$key]) ? $types[$key] : PDO::PARAM_STR);
                $param = ':'.$key;
                $sth->bindParam($param, $binds[$key], $type);
            }
        }

        $result = $sth->execute();
        if ($result === false) {
            return false;
        }

        return $sth;
    }

    /**
     * Create a table from a model's metadata.
     *
     * @return boolean TRUE if the table was created, otherwise FALSE.
     */
    public function createTable()
    {
        if ($this->tableExists() === true) {
            return true;
        }

        $dbh    = $this->db();
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table  = $this->table();
        $engine = '';

        $query = 'CREATE TABLE %table (
                        `ident` varchar(255) NOT NULL DEFAULT \'\',
                        `embed_data` text,
                        PRIMARY KEY (`ident`)
                    ) %engine';

        /** @todo Add indexes for all defined list constraints (yea... tough job...) */
        if ($driver === self::MYSQL_DRIVER_NAME) {
            $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';
        }

        $query = strtr($query, [
            '%table'  => $table,
            '%engine' => $engine
        ]);

        $this->logger->debug($query);
        $dbh->query($query);

        return true;
    }

    /**
     * Determine if the source table exists.
     *
     * @return boolean TRUE if the table exists, otherwise FALSE.
     */
    public function tableExists()
    {
        $dbh    = $this->db();
        $table  = $this->table();
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = sprintf('SELECT name FROM sqlite_master WHERE type = "table" AND name = "%s";', $table);
        } else {
            $query = sprintf('SHOW TABLES LIKE "%s"', $table);
        }

        $this->logger->debug($query);
        $sth    = $dbh->query($query);
        $exists = $sth->fetchColumn(0);

        return !!$exists;
    }

    /**
     * Get the table columns information.
     *
     * @return array An associative array.
     */
    public function tableStructure()
    {
        $dbh    = $this->db();
        $table  = $this->table();
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = sprintf('PRAGMA table_info("%s") ', $table);
        } else {
            $query = sprintf('SHOW COLUMNS FROM `%s`', $table);
        }

        $this->logger->debug($query);
        $sth = $dbh->query($query);

        $cols = $sth->fetchAll((PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC));
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $struct = [];
            foreach ($cols as $col) {
                // Normalize SQLite's result (PRAGMA) with mysql's (SHOW COLUMNS)
                $struct[$col['name']] = [
                    'Type'    => $col['type'],
                    'Null'    => !!$col['notnull'] ? 'NO' : 'YES',
                    'Default' => $col['dflt_value'],
                    'Key'     => !!$col['pk'] ? 'PRI' : '',
                    'Extra'   => ''
                ];
            }

            return $struct;
        } else {
            return $cols;
        }
    }

    /**
     * Save an item (create a new row) in storage.
     *
     * @param  array|mixed $item The object to save.
     * @throws PDOException If a database error occurs.
     * @return mixed The created item ID, otherwise FALSE.
     */
    public function saveItem($item)
    {
        if ($this->tableExists() === false) {
            /** @todo Optionnally turn off for some models */
            $this->createTable();
        }

        $table  = $this->table();
        $struct = array_keys($this->tableStructure());

        $keys   = [];
        $values = [];
        $binds  = [];

        foreach ($item as $key => $value) {
            if (in_array($key, $struct)) {
                $keys[]      = '`'.$key.'`';
                $values[]    = ':'.$key.'';
                $binds[$key] = $value;
            }
        }
        $query = 'INSERT INTO %table (%keys) VALUES (%values)';
        $query = strtr($query, [
            '%table'  => $table,
            '%keys'   => implode(', ', $keys),
            '%values' => implode(', ', $values)
        ]);

        $result = $this->dbQuery($query, $binds);

        if ($result === false) {
            throw new PDOException('Could not save item.');
        } else {
            return $this->db()->lastInsertId();
        }
    }

    /**
     * Load item by the primary column.
     *
     * @param  mixed $ident Ident can be any scalar value.
     * @throws PDOException When query fails.
     * @return array
     */
    public function loadItem($ident)
    {
        if ($this->tableExists() === false) {
            /** @todo Optionnally turn off for some models */
            $this->createTable();
        }

        $table = $this->table();
        $query = 'SELECT * FROM %table WHERE `ident` = :ident LIMIT 1';
        $query = strtr($query, [
            '%table' => $table
        ]);

        $binds = [
            'ident' => $ident
        ];

        $sth = $this->dbQuery($query, $binds);
        if ($sth === false) {
            throw new PDOException('Could not load item.');
        }

        $data = $sth->fetch(PDO::FETCH_ASSOC);

        return $data;
    }

    // /**
    //  * Update an item (update a row) in storage.
    //  *
    //  * @param  array|mixed $item The object to save.
    //  * @throws PDOException If a database error occurs.
    //  * @return mixed The created item ID, otherwise FALSE.
    //  */
    // public function updateItem($item)
    // {
    //     if ($this->tableExists() === false) {
    //         /** @todo Optionnally turn off for some models */
    //         $this->createTable();
    //     }
    //
    //     $table  = $this->table();
    //     $struct = array_keys($this->tableStructure());
    //
    //     $keys   = [];
    //     $values = [];
    //     $binds  = [];
    //
    //     foreach ($item as $key => $value) {
    //         if (in_array($key, $struct)) {
    //             $keys[]      = '`'.$key.'`';
    //             $values[]    = ':'.$key.'';
    //             $binds[$key] = $value;
    //         }
    //     }
    //     $query = 'UPDATE %table SET %updates WHERE `key = :%key`';
    //     $query = strtr($query, [
    //         '%table'  => $table,
    //         '%key'  => $table,
    //         '%keys'   => implode(', ', $keys),
    //         '%values' => implode(', ', $values)
    //     ]);
    //
    //     $result = $this->dbQuery($query, $binds);
    //
    //     if ($result === false) {
    //         throw new PDOException('Could not save item.');
    //     } else {
    //         return $this->db()->lastInsertId();
    //     }
    // }

    /**
     * @param string  $ident      The embed data ident to load.
     * @param boolean $useCache   If FALSE, ignore the cached object. Defaults to TRUE.
     * @param boolean $reloadData If TRUE, refresh the cached object. Defaults to FALSE.
     * @return mixed
     */
    public function load($ident, $useCache = true, $reloadData = false)
    {
        // if (!$useCache) {
        return $this->loadItem($ident);
        // }

        $cacheKey = $this->cacheKey($ident);
        // $cacheItem = $this->cachePool->getItem($cacheKey);
        //
        // if (!$reloadData) {
        //     if ($cacheItem->isHit()) {
        //         $data = $cacheItem->get();
        //
        //         return [];
        //     }
        // }

        // $obj  = $this->loadFromSource($ident);
        // $data = ($obj->id() ? $obj->data() : []);
        // $cacheItem->set($data);
        // $this->cachePool->save($cacheItem);

        return [];
    }

    /**
     * Generate a cache key.
     *
     * @param  string|integer $ident The object identifier to load.
     * @return string
     */
    private function cacheKey($ident)
    {
        $cacheKey = 'embed/'.parse_url($ident);

        return $cacheKey;
    }



    // Magic Methods
    // =============================================================================================

    /**
     * Retrieve an object by its key.
     *
     * @param  string|integer $ident The object identifier to load.
     * @param  mixed          $args  Unused; Method arguments.
     * @return array
     */
    public function __call($ident, $args = null)
    {
        unset($args);

        return $this->load($ident);
    }

    /**
     * Retrieve an object by its key.
     *
     * @param  string|integer $ident The object identifier to load.
     * @return array
     */
    public function __get($ident)
    {
        return $this->load($ident);
    }


    // GETTERS & SETTERS
    // ==========================================================================

    /**
     * Get the database's current table.
     *
     * @return string
     */
    public function table()
    {
        if ($this->table === null) {
            $this->table = self::DEFAULT_TABLE;
        }

        return $this->table;
    }

    /**
     * @param string $table Table for EmbedRepository.
     * @throws InvalidArgumentException When $table is not a string.
     * @return self
     */
    public function setTable($table)
    {
        if (!is_string($table)) {
            throw new InvalidArgumentException(sprintf(
                'DatabaseSource::setTable() expects a string as table. (%s given).',
                gettype($table)
            ));
        }

        /**
         * For security reason, only alphanumeric characters (+ underscores)
         * are valid table names; Although SQL can support more,
         * there's really no reason to.
         */
        if (!preg_match('/[A-Za-z0-9_]/', $table)) {
            throw new InvalidArgumentException(sprintf(
                'Table name "%s" is invalid: must be alphanumeric / underscore.',
                $table
            ));
        }

        $this->table = $table;

        return $this;
    }

    /**
     * Determine if a table is assigned.
     *
     * @return boolean
     */
    public function hasTable()
    {
        return !empty($this->table);
    }
}

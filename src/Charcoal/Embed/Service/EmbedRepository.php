<?php

namespace Charcoal\Embed\Service;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Embed\Mixin\EmbedAwareTrait;
use DateTime;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use UnexpectedValueException;

/**
 * Embed Repository
 *
 * Store scraped data from embed/embed in a provided database table.
 */
class EmbedRepository implements
    EmbedRepositoryInterface,
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
     * The default embed data format.
     *
     * @var self::FORMAT_*
     */
    private $format = self::FORMAT_ARRAY;

    // INIT
    // ==========================================================================

    /**
     * @param  array $data Dependencies.
     * @throws Exception If missing dependencies.
     * @return self
     */
    public function __construct(array $data)
    {
        $this->pdo = $data['pdo'];

        $this->setLogger($data['logger']);

        $config = $data['embed_config'];
        if ($config && is_array($config)) {
            foreach ($config as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    // Methods
    // ==========================================================================

    /**
     * @param  string  $ident  The embed ident to save from.
     * @param  ?string $format The embed format (null, src, array) @see{Charcoal\Embed\Mixin\EmbedAwareTrait}.
     * @return mixed
     */
    public function saveEmbedData($ident, $format = null)
    {
        // Check if exist to know if we have to save or update.
        $item = $this->load($ident);

        // Run through embed service.
        $embedItem = $this->processEmbed($ident);

        if ($item) {
            if (empty($embedItem['embed_data'])) {
                throw new UnexpectedValueException(
                    "Could not update item {$embedItem['ident']}"
                );
            }

            $this->updateItem($embedItem);
        } else {
            if (empty($embedItem['embed_data'])) {
                throw new UnexpectedValueException(
                    "Could not save item {$embedItem['ident']}"
                );
            }

            $this->saveItem($embedItem);
        }

        if ($format) {
            return $this->formatEmbedData($embedItem['embed_data'], $format);
        }

        return $embedItem['embed_data'];
    }

    /**
     * @param  string $ident The embed ident to process.
     * @return array
     */
    private function processEmbed($ident)
    {
        return [
            'ident'            => $ident,
            'embed_data'       => $this->resolveEmbedFormat($ident, self::FORMAT_ARRAY),
            'last_update_date' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Retrieve the database connector.
     *
     * @throws RuntimeException If the database was not set.
     * @return PDO
     */
    private function db()
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
    private function dbQuery($query, array $binds = [], array $types = [])
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
                $param = ':' . $key;
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
    private function createTable()
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
                        `last_update_date` datetime,
                        PRIMARY KEY (`ident`)
                    ) %engine';

        /** @todo Add indexes for all defined list constraints (yea... tough job...) */
        if ($driver === self::MYSQL_DRIVER_NAME) {
            $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';
        }

        $query = strtr($query, [
            '%table'  => $table,
            '%engine' => $engine,
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
    private function tableExists()
    {
        $dbh    = $this->db();
        $table  = $this->table();
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = sprintf(
                'SELECT `name` FROM `sqlite_master` WHERE `type` = "table" AND `name` = "%s";',
                $table
            );
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
    private function tableStructure()
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
                    'Extra'   => '',
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
    private function saveItem($item)
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
                $keys[]      = '`' . $key . '`';
                $values[]    = ':' . $key;
                $binds[$key] = $value;
            }
        }
        $query = 'INSERT INTO %table (%keys) VALUES (%values)';
        $query = strtr($query, [
            '%table'  => $table,
            '%keys'   => implode(', ', $keys),
            '%values' => implode(', ', $values),
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
    private function loadItem($ident)
    {
        if ($this->tableExists() === false) {
            /** @todo Optionnally turn off for some models */
            $this->createTable();
        }

        $table = $this->table();
        $query = 'SELECT * FROM %table WHERE `ident` = :ident LIMIT 1';
        $query = strtr($query, [
            '%table' => $table,
        ]);

        $binds = [
            'ident' => $ident,
        ];

        $sth = $this->dbQuery($query, $binds);
        if ($sth === false) {
            throw new PDOException('Could not load item.');
        }

        $data = $sth->fetch(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * Update an item (update a row) in storage.
     *
     * @param  array|mixed $item The object to save.
     * @throws PDOException If a database error occurs.
     * @return mixed The created item ID, otherwise FALSE.
     */
    public function updateItem($item)
    {
        if ($this->tableExists() === false) {
            /** @todo Optionnally turn off for some models */
            $this->createTable();
        }

        $table  = $this->table();
        $struct = array_keys($this->tableStructure());

        $updates = [];
        $binds   = [];

        foreach ($item as $key => $value) {
            if (in_array($key, $struct)) {
                $updates[]   = sprintf('`%1$s`=:%1$s', $key);
                $binds[$key] = $value;
            }
        }
        $query = 'UPDATE %table SET %updates WHERE `ident` = :ident';
        $query = strtr($query, [
            '%table'   => $table,
            '%updates' => implode(', ', $updates),
        ]);

        $result = $this->dbQuery($query, $binds);

        if ($result === false) {
            throw new PDOException('Could not update item.');
        } else {
            return $this->db()->lastInsertId();
        }
    }

    /**
     * @param  string  $ident The embed url to load data from.
     * @param  ?string $format The embed format (null, src, array) @see{Charcoal\Embed\Mixin\EmbedAwareTrait}.
     * @return mixed
     */
    public function embedData($ident, $format = null)
    {
        if ($ident === '') {
            return false;
        }

        $item = $this->load($ident);
        if (empty($item['embed_data'])) {
            return $this->saveEmbedData($ident, $format);
        }

        if (is_string($item['embed_data'])) {
            return (array) json_decode($item['embed_data'], true);
        }

        if ($format) {
            return $this->formatEmbedData($item['embed_data'], $format);
        }

        return $item['embed_data'];
    }

    /**
     * @param  string  $ident The embed data ident to load.
     * @return mixed
     */
    public function load($ident)
    {
        return $this->loadItem($ident);
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
     * @param  string $table Table for EmbedRepository.
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

    public function format()
    {
        return $this->format;
    }

    /**
     * @param  string $format The default embed format.
     * @return self
     */
    public function setFormat($format)
    {
        $this->assertValidFormat($format);

        $this->format = $format;

        return $this;
    }

    public function isValidFormat($format)
    {
        switch ($format) {
            case self::FORMAT_ARRAY:
            case self::FORMAT_HTML:
            case self::FORMAT_SRC:
                return true;
        }

        return false;
    }

    public function assertValidFormat($format)
    {
        if (!is_string($format)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported format; must be a string, received %s',
                (is_object($format) ? get_class($format) : gettype($format))
            ));
        }

        if (!$this->isValidFormat($format)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported embed format, received %s',
                $format
            ));
        }
    }

    /**
     * @return array<string, mixed>|string|null Returns the corresponding formatted embed.
     */
    public function formatEmbedData(array $data, $format = null)
    {
        switch ($format ?: $this->format()) {
            case self::FORMAT_ARRAY: {
                return $data;
            }

            case self::FORMAT_HTML: {
                return empty($data['iframe']) ? null : $data['iframe'];
            }

            case self::FORMAT_SRC: {
                return empty($data['src']) ? null : $data['src'];
            }
        }

        throw new InvalidArgumentException(
            "Unsupported embed format, received {$format}",
            $format
        );
    }
}

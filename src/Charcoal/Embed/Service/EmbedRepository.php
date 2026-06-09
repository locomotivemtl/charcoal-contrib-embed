<?php

namespace Charcoal\Embed\Service;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Embed\Mixin\EmbedAwareTrait;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
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

    const DEFAULT_TABLE_NAME = 'embed_cache';

    const MYSQL_DRIVER_NAME = 'mysql';
    const SQLITE_DRIVER_NAME = 'sqlite';

    /** The database table name. */
    private ?string $tableName = null;

    /** The database connector. */
    private PDO $pdo;

    /** @var self::FORMAT_* The default embed data format. */
    private string $format = self::FORMAT_ARRAY;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        PDO $pdo,
        LoggerInterface $logger,
        array $options = []
    ) {
        $this->pdo = $pdo;

        $this->setLogger($logger);

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    // Database Source
    // ==========================================================================

    /**
     * @return mixed
     */
    public function saveEmbedData(string $ident, ?string $format = null)
    {
        if ($ident === '') {
            return null;
        }

        // Check if exist to know if we have to save or update.
        $item = $this->loadItem($ident);

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
     * @return mixed
     */
    public function getEmbedData(string $ident, ?string $format = null)
    {
        if ($ident === '') {
            return null;
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
     * Alias of {@see self::getEmbedData()}.
     */
    public function embedData(string $ident, ?string $format = null)
    {
        return $this->getEmbedData($ident, $format);
    }

    /**
     * @param  string $ident The embed ident to process.
     * @return array{ident: string, embed_data: array<string, mixed>, last_update_data: string}
     */
    private function processEmbed(string $ident): array
    {
        return [
            'ident'            => $ident,
            'embed_data'       => $this->resolveEmbedFormat($ident, self::FORMAT_ARRAY),
            'last_update_date' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Execute an SQL query and return the PDOStatement if successful.
     *
     * @param  string                      $query The SQL query to executed.
     * @param  array<string, mixed>        $binds Optional. Query parameter binds.
     * @param  array<string, PDO::PARAM_*> $types Optional. Types of parameter bindings.
     */
    private function dbQuery(string $query, array $binds = [], array $types = []): ?PDOStatement
    {
        $this->logger->debug($query, $binds);
        $sth = $this->pdo->prepare($query);
        if (!$sth) {
            return null;
        }

        foreach ($binds as $key => $val) {
            if ($val === null) {
                $types[$key] = PDO::PARAM_NULL;
            } elseif (!is_scalar($val)) {
                $val = json_encode($val);
            }

            $sth->bindParam(
                ":{$key}",
                $val,
                ($types[$key] ?? PDO::PARAM_STR)
            );
        }

        return $sth->execute() ? $sth : null;
    }

    /**
     * Create a table from a model's metadata.
     *
     * @throws PDOException If the database table could not be created.
     * @return true TRUE if the table was created, otherwise FALSE.
     */
    private function createTable(): bool
    {
        if ($this->tableExists() === true) {
            return true;
        }

        $dbh    = $this->pdo;
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table  = $this->getTableName();
        $engine = '';

        $query = <<<'SQL'
            CREATE TABLE `%table` (
                `ident` VARCHAR(255) NOT NULL DEFAULT "",
                `embed_data` TEXT,
                `last_update_date` DATETIME,
                PRIMARY KEY (`ident`)
            ) %engine
            SQL;

        /** @todo Add indexes for all defined list constraints (yea... tough job...) */
        if ($driver === self::MYSQL_DRIVER_NAME) {
            $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';
        }

        $query = strtr($query, [
            '%table'  => $table,
            '%engine' => $engine,
        ]);

        $this->logger->debug($query);
        if (!$dbh->query($query)) {
            throw new PDOException(
                'Could not create embed database table'
            );
        }

        return true;
    }

    /**
     * Determine if the source table exists.
     *
     * @return bool TRUE if the table exists, otherwise FALSE.
     */
    private function tableExists(): bool
    {
        $dbh    = $this->pdo;
        $table  = $this->getTableName();
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

        return (bool) $exists;
    }

    /**
     * Get the table columns information.
     *
     * @return array<string, mixed>
     */
    private function tableStructure(): array
    {
        $dbh    = $this->pdo;
        $table  = $this->getTableName();
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
        }

        return $cols;
    }

    /**
     * Save an embed record.
     *
     * @param  array<string, mixed> $item The embed record to save.
     * @throws PDOException If the record can not be saved.
     * @return string The created item ID, otherwise FALSE.
     */
    private function saveItem(array $item)
    {
        if (!$this->tableExists()) {
            $this->createTable();
        }

        $table  = $this->getTableName();
        $struct = array_keys($this->tableStructure());

        $keys   = [];
        $values = [];
        $binds  = [];

        foreach ($item as $key => $value) {
            if (in_array($key, $struct)) {
                $keys[]      = "`{$key}`";
                $values[]    = ":{$key}";
                $binds[$key] = $value;
            }
        }
        $query = 'INSERT INTO `%table` (%keys) VALUES (%values)';
        $query = strtr($query, [
            '%table'  => $table,
            '%keys'   => implode(', ', $keys),
            '%values' => implode(', ', $values),
        ]);

        if (!$this->dbQuery($query, $binds)) {
            throw new PDOException(
                "Could not save embed record for URL: {$item['ident']}"
            );
        }

        $id = $this->pdo->lastInsertId();
        if ($id === false) {
            throw new PDOException(
                "Could not retrieve ID of saved embed record for URL: {$item['ident']}"
            );
        }

        return $id;
    }

    /**
     * Update an embed record.
     *
     * @param  array<string, mixed> $item The object to save.
     * @throws PDOException If the record ca not be updated.
     * @return bool TRUE if the item was updated, otherwise FALSE.
     */
    private function updateItem(array $item): bool
    {
        if ($this->tableExists() === false) {
            /** @todo Optionnally turn off for some models */
            $this->createTable();
        }

        $table  = $this->getTableName();
        $struct = array_keys($this->tableStructure());

        $updates = [];
        $binds   = [];

        foreach ($item as $key => $value) {
            if (in_array($key, $struct)) {
                $updates[]   = sprintf('`%1$s` = :%1$s', $key);
                $binds[$key] = $value;
            }
        }
        $query = 'UPDATE `%table` SET %updates WHERE `ident` = :ident';
        $query = strtr($query, [
            '%table'   => $table,
            '%updates' => implode(', ', $updates),
        ]);

        if (!$this->dbQuery($query, $binds)) {
            throw new PDOException(
                "Could not update embed record for URL: {$item['ident']}"
            );
        }

        return true;
    }

    /**
     * Load an embed record by its URI.
     *
     * @param  string $ident The embed URI to lookup.
     * @throws PDOException If the record can not be retrieved.
     * @return ?array<string, mixed>
     */
    private function loadItem(string $ident): ?array
    {
        if ($this->tableExists() === false) {
            return null;
        }

        $table = $this->getTableName();
        $query = 'SELECT * FROM `%table` WHERE `ident` = :ident LIMIT 1';
        $query = strtr($query, [
            '%table' => $table,
        ]);

        $binds = [
            'ident' => $ident,
        ];

        $result = $this->dbQuery($query, $binds);
        if (!$result) {
            throw new PDOException(
                "Could not retrieve embed record for URL: {$ident}"
            );
        }

        return $result->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param  string $ident The embed data ident to load.
     * @return ?array<string, mixed>
     */
    public function load(string $ident): ?array
    {
        return $this->loadItem($ident);
    }


    // Options
    // ==========================================================================

    /**
     * Get the database's current table.
     */
    public function getTableName(): string
    {
        return $this->tableName ??= self::DEFAULT_TABLE_NAME;
    }

    /**
     * @throws InvalidArgumentException When table name is invalid.
     * @return static
     */
    public function setTableName(string $name)
    {
        /**
         * For security reason, only alphanumeric characters (+ underscores)
         * are valid table names; Although SQL can support more,
         * there's really no reason to.
         */
        if (!preg_match('/^\w+$/', $name)) {
            throw new InvalidArgumentException(
                "Expected a table name containing alphanumeric characters, received '{$name}'",
            );
        }

        $this->tableName = $name;

        return $this;
    }

    /**
     * Alias of {@see self::setTableName()}
     */
    public function setTable(string $table)
    {
        $this->setTableName($table);

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return static
     */
    public function setFormat(string $format)
    {
        $this->assertValidFormat($format);

        $this->format = $format;

        return $this;
    }


    // Formatting
    // ==========================================================================

    public function isValidFormat(string $format): bool
    {
        switch ($format) {
            case self::FORMAT_ARRAY:
            case self::FORMAT_HTML:
            case self::FORMAT_SRC:
                return true;
        }

        return false;
    }

    /**
     * Asserts that the embed format is valid, otherwise throws an exception.
     *
     * @throws InvalidArgumentException If the format is unsupported.
     */
    public function assertValidFormat(string $format): void
    {
        if (!$this->isValidFormat($format)) {
            throw new InvalidArgumentException(
                "Unsupported embed format, received {$format}"
            );
        }
    }

    /**
     * @return array<string, mixed>|string|null Returns the corresponding formatted embed.
     */
    public function formatEmbedData(array $data, ?string $format = null)
    {
        switch ($format ?: $this->getFormat()) {
            case self::FORMAT_ARRAY: {
                return $data;
            }

            case self::FORMAT_HTML: {
                return $data['iframe'] ?? null;
            }

            case self::FORMAT_SRC: {
                return $data['src'] ?? null;
            }
        }

        throw new InvalidArgumentException(
            "Unsupported embed format, received {$format}",
            $format
        );
    }
}

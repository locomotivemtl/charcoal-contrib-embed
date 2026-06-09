<?php

namespace Charcoal\Embed\Service;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Embed\Mixin\EmbedResolverTrait;
use DateTimeImmutable;
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
    use EmbedResolverTrait;

    const DEFAULT_TABLE_NAME = 'embed_cache';

    const MYSQL_DRIVER_NAME = 'mysql';
    const SQLITE_DRIVER_NAME = 'sqlite';

    /** The database table name. */
    private ?string $tableName = null;

    /** Recall if table exists. */
    private ?bool $tableExists = null;

    /** Recall table structure. */
    private ?array $tableStructure = null;

    /** The database connector. */
    private PDO $pdo;

    /** @var self::FORMAT_* The default embed data format. */
    private string $format = self::FORMAT_ARRAY;

    /** The embed resolver. */
    private EmbedResolver $resolver;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        EmbedResolver $resolver,
        PDO $pdo,
        LoggerInterface $logger,
        array $options = []
    ) {
        $this->resolver = $resolver;
        $this->pdo = $pdo;

        $this->setLogger($logger);

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function getResolver(): EmbedResolver
    {
        return $this->resolver;
    }

    // Database Source
    // ==========================================================================

    /**
     * @throws UnexpectedValueException If the embed data is invalid.
     */
    public function saveEmbedData(string $url, ?string $format = null)
    {
        $this->assertValidUrl($url);

        $item = $this->processEmbed($url);
        if (empty($item['embed_data'])) {
            throw new UnexpectedValueException(sprintf(
                "Could not save embed record for URL: {$item['ident']}",
                $item['ident']
            ));
        }

        $this->saveItem($item);

        if ($format) {
            return $this->formatEmbedData($item['embed_data'], $format);
        }

        return $item['embed_data'];
    }

    public function getEmbedData(string $url, ?string $format = null)
    {
        $this->assertValidUrl($url);

        $item = $this->loadItem($url);
        if (empty($item['embed_data'])) {
            return $this->saveEmbedData($url, $format);
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
    public function embedData(string $url, ?string $format = null)
    {
        return $this->getEmbedData($url, $format);
    }

    /**
     * @param  string $url The embed URL to process.
     * @return array{ident: string, embed_data: array<string, mixed>, last_update_data: string}
     */
    private function processEmbed(string $url): array
    {
        return [
            'ident'            => $url,
            'embed_data'       => $this->getResolver()->fetchData($url),
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

        foreach ($binds as $key => $value) {
            if ($value === null) {
                $types[$key] = PDO::PARAM_NULL;
            } elseif (!is_scalar($value)) {
                $value = json_encode($value);
            }

            $sth->bindValue(
                ":{$key}",
                $value,
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
    private function performCreateTable(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table  = $this->getTableName();
        $engine = '';

        $query = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `%table` (
                `ident` VARCHAR(255) NOT NULL,
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
        if (!$this->pdo->query($query)) {
            throw new PDOException(
                'Could not create embed database table'
            );
        }

        $this->tableExists = true;
        $this->tableStructure = null;
        return true;
    }

    /**
     * Query the datanase to determine if the table exists.
     *
     * @return bool TRUE if the table exists, otherwise FALSE.
     */
    private function performTableExists(): bool
    {
        $table  = $this->getTableName();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = sprintf(
                'SELECT `name` FROM `sqlite_master` WHERE `type` = "table" AND `name` = "%s";',
                $table
            );
        } else {
            $query = sprintf('SHOW TABLES LIKE "%s"', $table);
        }

        $this->logger->debug($query);
        $sth    = $this->pdo->query($query);
        $exists = $sth->fetchColumn(0);

        return (bool) $exists;
    }

    /**
     * Get the table columns information.
     *
     * @return array<string, mixed>
     */
    private function performTableStructure(): array
    {
        $table  = $this->getTableName();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = sprintf('PRAGMA table_info("%s") ', $table);
        } else {
            $query = sprintf('SHOW COLUMNS FROM `%s`', $table);
        }

        $this->logger->debug($query);
        $sth = $this->pdo->query($query);

        $cols = $sth->fetchAll((PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC));
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $struct = [];
            foreach ($cols as $col) {
                // Normalize SQLite's result (PRAGMA) with mysql's (SHOW COLUMNS)
                $struct[$col['name']] = [
                    'Type'    => $col['type'],
                    'Null'    => $col['notnull'] ? 'NO' : 'YES',
                    'Default' => $col['dflt_value'],
                    'Key'     => $col['pk'] ? 'PRI' : '',
                    'Extra'   => '',
                ];
            }

            return $struct;
        }

        return $cols;
    }

    /**
     * Determine if the database table exists.
     *
     * @return bool TRUE if the table exists, otherwise FALSE.
     */
    private function tableExists(): bool
    {
        return $this->tableExists ??= $this->performTableExists();
    }

    /**
     * Get the table columns information.
     *
     * @return array<string, mixed>
     */
    private function getTableStructure(): array
    {
        return $this->tableStructure ??= $this->performTableStructure();
    }

    /**
     * Save an embed record.
     *
     * @param  array<string, mixed> $item The embed record to save.
     * @throws PDOException If the record can not be saved.
     * @return bool TRUE if the item was inserted or updated, otherwise FALSE.
     */
    private function saveItem(array $item): bool
    {
        if (!$this->tableExists()) {
            $this->performCreateTable();
        }

        $table  = $this->getTableName();
        $struct = $this->getTableStructure();

        $primary = null;
        $keys    = [];
        $inserts = [];
        $updates = [];
        $binds   = [];

        foreach ($item as $key => $value) {
            if (empty($struct[$key])) {
                continue;
            }

            $keys[]      = "`{$key}`";
            $inserts[]   = ":{$key}";
            $binds[$key] = $value;

            if ($struct[$key]['Key'] === 'PRI') {
                $primary = $key;
            } else {
                $updates[] = "`{$key}` = VALUES (`{$key}`)";
            }
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === self::SQLITE_DRIVER_NAME) {
            $query = 'INSERT INTO `%table` (%keys) VALUES (%inserts) ON CONFLICT(%primary) DO UPDATE SET %updates';
        } else {
            $query = 'INSERT INTO `%table` (%keys) VALUES (%inserts) ON DUPLICATE KEY UPDATE %updates';
        }

        $query = strtr($query, [
            '%table'   => $table,
            '%primary' => $primary,
            '%keys'    => implode(', ', $keys),
            '%inserts' => implode(', ', $inserts),
            '%updates' => implode(', ', $updates),
        ]);

        if (!$this->dbQuery($query, $binds)) {
            throw new PDOException(
                "Could not save embed record for URL: {$item['ident']}"
            );
        }

        return true;
    }

    /**
     * Load an embed record by its URI.
     *
     * @param  string $url The embed URI to lookup.
     * @throws PDOException If the record can not be retrieved.
     * @return ?array<string, mixed>
     */
    private function loadItem(string $url): ?array
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
            'ident' => $url,
        ];

        $result = $this->dbQuery($query, $binds);
        if (!$result) {
            throw new PDOException(
                "Could not retrieve embed record for URL: {$url}"
            );
        }

        if ($result->rowCount() === 0) {
            return null;
        }

        return $result->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves the embed data from the database.
     *
     * @param  string $url The embed data URL to retrieve.
     * @return ?array<string, mixed>
     */
    public function load(string $url): ?array
    {
        $this->assertValidUrl($url);

        return $this->loadItem($url);
    }

    /**
     * Retrieves the default list of acceptable URL schemes (e.g., HTTP).
     *
     * @return string[]
     */
    protected function getAllowedUrlSchemes(): array
    {
        return [ 'https', 'http' ];
    }

    public function isValidUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, $this->getAllowedUrlSchemes())) {
            return false;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * @throws \InvalidArgumentException If the URL is invalid.
     */
    public function assertValidUrl(string $url): void
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException(
                "Invalid embed URL, received {$url}"
            );
        }
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
        $this->tableExists = null;
        $this->tableStructure = null;

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

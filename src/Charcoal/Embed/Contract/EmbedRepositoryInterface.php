<?php

namespace Charcoal\Embed\Contract;

/**
 * Embed Repository Interface
 */
interface EmbedRepositoryInterface
{
    public const FORMAT_ARRAY = 'array';
    public const FORMAT_HTML  = 'html';
    public const FORMAT_SRC   = 'src';

    /**
     * Processes the URL, saves the embed data to the database,
     * and returns the data.
     *
     * @param  string  $url    The URL to save.
     * @param  ?string $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function saveEmbedData(string $url, ?string $format = null);

    /**
     * Retrieves the embed data from the database,
     * otherwise processes the URL and persists
     * the data to the database.
     *
     * @param  string  $url    The URL to retrieve.
     * @param  ?string $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function getEmbedData(string $url, ?string $format = null);

    /**
     * Retrieves the default format of embed data.
     *
     * @return self::FORMAT_*
     */
    public function getFormat(): string;

    /**
     * @param  array<string, mixed> $data   The embed data to format.
     * @param  ?string              $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function formatEmbedData(array $data, ?string $format = null);

    /**
     * Determines if the embed format is valid.
     *
     * @param  string $format The format to test.
     * @return bool TRUE if the format is supported, otherwise FALSE.
     */
    public function isValidFormat(string $format): bool;

    /**
     * Asserts that the embed format is valid, otherwise throws an exception.
     *
     * @param  string $format The format to test.
     * @throws \Exception If the format is unsupported.
     */
    public function assertValidFormat(string $format): void;
}

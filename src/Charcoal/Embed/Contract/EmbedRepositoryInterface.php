<?php

namespace Charcoal\Embed\Contract;

/**
 * Embed Repository Interface
 */
interface EmbedRepositoryInterface
{
    const FORMAT_ARRAY = 'array';
    const FORMAT_HTML  = 'html';
    const FORMAT_SRC   = 'src';

    /**
     * Processes the URL, saves the embed data to the database,
     * and returns the data.
     *
     * @param  string  $ident  The embed URL to save.
     * @param  ?string $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function saveEmbedData($ident, $format = null);

    /**
     * Retrieves the embed data from the database,
     * otherwise processes the URL and persists
     * the data to the database.
     *
     * @param  string  $ident  The embed URL to retrieve.
     * @param  ?string $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function embedData($ident, $format = null);

    /**
     * Retrieves the default format of embed data.
     *
     * @return self::FORMAT_*
     */
    public function format();

    /**
     * @param  array<string, mixed> $data   The embed data to format.
     * @param  ?string              $format The format in which to return the embed.
     * @return mixed Returns the corresponding formatted embed.
     */
    public function formatEmbedData(array $data, $format = null);

    /**
     * Determines if the embed format is valid.
     *
     * @param  string $format The format to test.
     * @return boolean
     */
    public function isValidFormat($format);

    /**
     * Asserts that the embed format is valid, throws an exception if not.
     *
     * @param  string $format The format to test.
     * @throws \InvalidArgumentException If the format is not a string or unsupported.
     * @return void
     */
    public function assertValidFormat($format);
}

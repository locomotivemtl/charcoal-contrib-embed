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
     * @param  string $ident  The embed URI to save from.
     * @param  string $format The embed format.
     * @return mixed
     */
    public function saveEmbedData($ident, $format = null);

    /**
     * @param  string $ident The embed URI to load data from.
     * @return boolean|mixed
     */
    public function embedData($ident);
}

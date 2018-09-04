<?php

namespace Charcoal\Embed\Contract;

interface EmbedRepositoryInterface
{
    /**
     * @param string $ident  The embed ident to save from.
     * @param string $format The embed format (null, src, array) @see{Charcoal\Embed\Mixin\EmbedAwareTrait}.
     * @return mixed
     */
    public function saveEmbedData($ident, $format = null);

    /**
     * @param string $ident The embed url to load data from.
     * @return boolean|mixed
     */
    public function embedData($ident);
}

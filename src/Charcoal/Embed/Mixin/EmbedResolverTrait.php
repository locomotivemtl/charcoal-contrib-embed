<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Service\EmbedResolver;

/**
 * Provides awareness to embed resolver.
 */
trait EmbedResolverTrait
{
    protected ?EmbedResolver $embedResolver = null;

    /**
     * Retrieve the embed resolver service.
     */
    public function getEmbedResolver(): EmbedResolver
    {
        return $this->embedResolver;
    }

    /**
     * @return static
     */
    public function setEmbedResolver(EmbedResolver $resolver)
    {
        $this->embedResolver = $resolver;

        return $this;
    }
}

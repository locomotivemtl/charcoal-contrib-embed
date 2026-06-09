<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;

/**
 * Provides awareness to embed repository.
 */
trait EmbedRepositoryTrait
{
    protected ?EmbedRepositoryInterface $embedRepository = null;

    /**
     * Retrieve the embed repository service.
     */
    public function embedRepository(): EmbedRepositoryInterface
    {
        return $this->embedRepository;
    }

    /**
     * @return static
     */
    public function setEmbedRepository(EmbedRepositoryInterface $repository)
    {
        $this->embedRepository = $repository;

        return $this;
    }
}

<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use RuntimeException;

/**
 * Provides Awareness to Embed Repository.
 *
 * Trait EmbedRepositoryAwareTrait
 * @package Charcoal\Embed\Mixin
 */
trait EmbedRepositoryTrait
{
    /**
     * @var EmbedRepositoryInterface
     */
    protected $embedRepository;

    /**
     * Retrieve the embed repository service
     *
     * @throws RuntimeException If the embed repository is missing.
     * @return EmbedRepositoryInterface
     */
    public function embedRepository()
    {
        if (!isset($this->embedRepository)) {
            throw new RuntimeException(sprintf(
                'Embed Repository is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->embedRepository;
    }

    /**
     * @param EmbedRepositoryInterface $embedRepository EmbedRepository for EmbedRepositoryAwareTrait.
     * @return self
     */
    public function setEmbedRepository(EmbedRepositoryInterface $embedRepository)
    {
        $this->embedRepository = $embedRepository;

        return $this;
    }
}

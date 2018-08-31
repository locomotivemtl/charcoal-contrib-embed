<?php

namespace Charcoal\Property;

use RuntimeException;

// From 'charcoal-property'
use Charcoal\Embed\Service\EmbedRepository;
use Charcoal\Property\UrlProperty;
use Pimple\Container;

/**
 * Class EmbedProperty
 */
class EmbedProperty extends UrlProperty
{
    /**
     * @var EmbedRepository
     */
    protected $embedRepository;

    /**
     * @param Container $container A Pimple DI container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['charcoal/embed/repository']);
    }

    /**
     * @throws RuntimeException If embed repository is missing.
     * @return EmbedRepository
     */
    public function embedRepository()
    {
        if (!isset($this->embedRepository)) {
            throw new RuntimeException(sprintf(
                'embed repository is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->embedRepository;
    }

    /**
     * @param EmbedRepository $embedRepository EmbedRepository for EmbedProperty.
     * @return self
     */
    public function setEmbedRepository(EmbedRepository $embedRepository)
    {
        $this->embedRepository = $embedRepository;

        return $this;
    }

    /**
     * @param mixed $val The value, at time of saving.
     * @return mixed
     */
    public function save($val)
    {
        $val = parent::save($val);

        $this->embedRepository()->saveEmbedData($val);

        return $val;
    }
}

<?php

namespace Charcoal\Property;

use RuntimeException;

// From Pimple
use Pimple\Container;

// From 'charcoal-translator'
use Charcoal\Translator\Translation;

// From 'charcoal-property'
use Charcoal\Property\UrlProperty;

// From 'charcoal-contrib-embed'
use Charcoal\Embed\Mixin\EmbedRepositoryTrait;
use Charcoal\Embed\Service\EmbedRepository;

/**
 * Class EmbedProperty
 */
class EmbedProperty extends UrlProperty
{
    use EmbedRepositoryTrait;

    /**
     * @var string|null $embedFormat
     */
    protected $embedFormat = 'array';

    /**
     * @param Container $container A Pimple DI container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['embed/repository']);
    }

    /**
     * @return null|string
     */
    public function embedFormat()
    {
        return $this->embedFormat;
    }

    /**
     * @param null|string $embedFormat EmbedFormat for EmbedProperty.
     * @return self
     */
    public function setEmbedFormat($embedFormat)
    {
        $this->embedFormat = $embedFormat;

        return $this;
    }

    /**
     * @param mixed $val The value, at time of saving.
     * @return mixed
     */
    public function save($val)
    {
        $val = parent::save($val);

        if ($val instanceof Translation) {
            foreach ($val->data() as $lang => $value) {
                if (!empty($value)) {
                    $this->embedRepository()->saveEmbedData($value, $this->embedFormat());
                }
            }
        } else {
            if (!empty($val)) {
                $this->embedRepository()->saveEmbedData($val, $this->embedFormat());
            }
        }

        return $val;
    }
}

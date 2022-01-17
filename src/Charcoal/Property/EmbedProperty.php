<?php

namespace Charcoal\Property;

use Charcoal\Embed\Mixin\EmbedRepositoryTrait;
use Charcoal\Embed\Service\EmbedRepository;
use Charcoal\Property\UrlProperty;
use Charcoal\Translator\Translation;
use Pimple\Container;
use RuntimeException;

/**
 * Embed Property
 */
class EmbedProperty extends UrlProperty
{
    use EmbedRepositoryTrait;

    /**
     * @var string|null $embedFormat
     */
    protected $embedFormat = 'array';

    /**
     * @param  Container $container A Pimple DI container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['embed/repository']);
    }

    /**
     * @return string|null
     */
    public function embedFormat()
    {
        return $this->embedFormat;
    }

    /**
     * @param  string|null $format The embed value return format.
     * @return self
     */
    public function setEmbedFormat($format)
    {
        $this->embedFormat = $format;

        return $this;
    }

    /**
     * @param  mixed $val The value, at time of saving.
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

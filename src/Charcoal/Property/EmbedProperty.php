<?php

namespace Charcoal\Property;

use Charcoal\Embed\Mixin\EmbedRepositoryTrait;
use Charcoal\Property\UrlProperty;
use Charcoal\Translator\Translation;
use Pimple\Container;

/**
 * Embed Property
 */
class EmbedProperty extends UrlProperty
{
    use EmbedRepositoryTrait;

    /** @var ?string The default format of embed data. */
    protected $embedFormat = null;

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
     * @return string
     */
    public function embedFormat()
    {
        if ($this->embedFormat) {
            return $this->embedFormat;
        }

        return $this->embedRepository()->format();
    }

    /**
     * @param  ?string $format The embed value return format.
     * @return self
     */
    public function setEmbedFormat($format)
    {
        if (is_string($format)) {
            $this->embedRepository()->assertValidFormat($format);
        }

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
            return $val->each(function ($v) {
                return $this->saveEmbedData($v);
            });
        }

        if (is_array($val)) {
            return array_map(function ($v) {
                return $this->saveEmbedData($v);
            }, $val);
        }

        return $this->saveEmbedData($val);
    }

    /**
     * @param  mixed $val The value, at time of saving.
     * @return mixed
     */
    private function saveEmbedData($val)
    {
        if ($val) {
            $this->embedRepository()->saveEmbedData($val);
        }

        return $val;
    }
}

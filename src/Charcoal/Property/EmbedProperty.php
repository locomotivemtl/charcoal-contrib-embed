<?php

namespace Charcoal\Property;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
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

    /** @var ?EmbedRepositoryInterface::FORMAT_* The default format of embed data. */
    protected ?string $embedFormat = null;

    /**
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['embed/repository']);
    }

    public function getEmbedFormat(): string
    {
        return $this->embedFormat ?? $this->embedRepository()->getFormat();
    }

    /**
     * @param  ?string $format The default format or
     *     NULL to fallback to the repository's default format.
     * @return static
     */
    public function setEmbedFormat(?string $format)
    {
        if (is_string($format)) {
            $this->embedRepository()->assertValidFormat($format);
        }

        $this->embedFormat = $format;

        return $this;
    }

    /**
     * @param  mixed $val
     * @return string
     */
    public function parseOne($val)
    {
        return trim(parent::parseOne($val));
    }

    /**
     * @param  Translation|array<string>|string $val The value, at time of saving.
     * @return Translation|array<string>|string
     */
    public function save($val)
    {
        $val = parent::save($val);

        if ($val instanceof Translation) {
            return $val->each(
                fn($v) => $this->saveEmbedData($v)
            );
        }

        if (is_array($val)) {
            return array_map(
                fn($v) => $this->saveEmbedData($v),
                $val
            );
        }

        return $this->saveEmbedData($val);
    }

    private function saveEmbedData(string $url): string
    {
        if ($url) {
            $this->embedRepository()->saveEmbedData($url);
        }

        return $url;
    }
}

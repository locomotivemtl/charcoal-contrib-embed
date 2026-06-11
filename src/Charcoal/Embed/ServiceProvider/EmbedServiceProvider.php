<?php

namespace Charcoal\Embed\ServiceProvider;

use Charcoal\Embed\Service\EmbedFactory;
use Charcoal\Embed\Service\EmbedRepository;
use Charcoal\Embed\Service\EmbedResolver;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Embed Contrib Service Provider
 */
class EmbedServiceProvider implements ServiceProviderInterface
{
    /**
     * @return void
     */
    public function register(Container $container)
    {
        $container['embed/repository'] = fn(Container $container): EmbedRepository => new EmbedRepository(
            $container['embed/resolver'],
            $container['database'],
            $container['logger'],
            $container['config']->get('embed_config') ?? [],
        );

        $container['embed/resolver'] = function (Container $container): EmbedResolver {
            $settings = $container['config']->get('embed_config.extractor_settings');
            return new EmbedResolver(
                $settings ? new EmbedFactory($settings) : null
            );
        };
    }
}

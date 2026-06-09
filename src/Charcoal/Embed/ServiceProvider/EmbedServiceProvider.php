<?php

namespace Charcoal\Embed\ServiceProvider;

use Charcoal\Embed\Service\EmbedRepository;
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
            $container['database'],
            $container['logger'],
            $container['config']->get('embed_config') ?? [],
        );
    }
}

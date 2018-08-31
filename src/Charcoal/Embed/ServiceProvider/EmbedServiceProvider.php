<?php

namespace Charcoal\Embed\ServiceProvider;

// from 'pimple'
use Charcoal\Embed\Service\EmbedRepository;
use Pimple\ServiceProviderInterface;
use Pimple\Container;

/**
 * The Embed Contrib Service Provider
 */
class EmbedServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the contrib's services.
     *
     * @param  Container $container The service locator.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @param Container $container Pimple container.
         * @return EmbedRepository
         */
        $container['charcoal/embed/repository'] = function (Container $container) {
            return new EmbedRepository([
                'pdo'    => $container['database'],
                'logger' => $container['logger']
            ]);
        };
    }
}

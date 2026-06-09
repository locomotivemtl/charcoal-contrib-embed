<?php

namespace Charcoal\Embed;

use Charcoal\App\Module\AbstractModule;
use Charcoal\Embed\ServiceProvider\EmbedServiceProvider;

/**
 * Embed Contrib Module
 */
class EmbedModule extends AbstractModule
{
    const ADMIN_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-embed/config/admin.json';
    const APP_CONFIG   = 'vendor/locomotivemtl/charcoal-contrib-embed/config/config.json';

    /**
     * @return static
     */
    public function setup()
    {
        /** @var \Pimple\Container */
        $container = $this->app()->getContainer();

        $embedServiceProvider = new EmbedServiceProvider();
        $container->register($embedServiceProvider);

        return $this;
    }
}

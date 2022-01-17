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
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $embedServiceProvider = new EmbedServiceProvider();
        $container->register($embedServiceProvider);

        return $this;
    }
}

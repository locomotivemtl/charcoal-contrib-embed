<?php

namespace Charcoal\Embed\Action;

// from Psr-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// from 'charcoal-app'
use Charcoal\App\Action\AbstractAction;

// from 'pimple'
use Pimple\Container;

// local
use Charcoal\Embed\Mixin\EmbedRepositoryTrait;

/**
 * UpdateEmbedDataAction
 */
class UpdateEmbedDataAction extends AbstractAction
{
    use EmbedRepositoryTrait;

    /**
     * Give an opportunity to children classes to inject dependencies from a Pimple Container.
     *
     * Does nothing by default, reimplement in children classes.
     *
     * The `$container` DI-container (from `Pimple`) should not be saved or passed around, only to be used to
     * inject dependencies (typically via setters).
     *
     * @param Container $container A dependencies container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['embed/repository']);
    }

    /**
     * Gets a psr7 request and response and returns a response.
     *
     * Called from `__invoke()` as the first thing.
     *
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $ident = $request->getParam('ident');

        $results = $this->embedRepository()->saveEmbedData($ident, 'array');

        if ($results['ident']) {
            $this->setSuccess(true);
        }

        return $response;
    }

    /**
     * Returns an associative array of results (set after being  invoked / run).
     *
     * The raw array of results will be called from `__invoke()`.
     *
     * @return array
     */
    public function results()
    {
        return [
            'success' => $this->success()
        ];
    }
}

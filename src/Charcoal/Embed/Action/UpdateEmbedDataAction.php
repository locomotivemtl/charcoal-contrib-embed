<?php

namespace Charcoal\Embed\Action;

use Charcoal\App\Action\AbstractAction;
use Charcoal\Embed\Mixin\EmbedRepositoryTrait;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Update Embed Data Action Controller
 */
class UpdateEmbedDataAction extends AbstractAction
{
    use EmbedRepositoryTrait;

    /**
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setEmbedRepository($container['embed/repository']);
    }

    /**
     * @param  \Slim\Http\Request  $request
     * @param  \Slim\Http\Response $response
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $url = $request->getParam('url');

        if ($this->embedRepository()->saveEmbedData($url)) {
            $this->setSuccess(true);
        }

        return $response;
    }

    /**
     * @return array{success: bool}
     */
    public function results()
    {
        return [
            'success' => $this->success(),
        ];
    }
}

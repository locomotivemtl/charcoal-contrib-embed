<?php

namespace Charcoal\Embed\Service;

use Embed\Embed;
use Embed\ExtractorFactory;
use Embed\Http\Crawler;

/**
 * {@see \Embed\Embed} factory
 */
class EmbedFactory
{
    private array $settings;

    public function __construct(?array $settings = [])
    {
        $this->settings = $settings ?? [];
    }

    public function createEmbed(?Crawler $crawler = null, ?ExtractorFactory $extractorFactory = null): Embed
    {
        $embed = new Embed($crawler, $extractorFactory);
        $embed->setSettings($this->settings);

        return $embed;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }
}

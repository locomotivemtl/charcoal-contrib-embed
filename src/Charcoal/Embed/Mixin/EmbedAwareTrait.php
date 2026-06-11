<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Translator\Translation;
use DOMDocument;
use DOMElement;
use Embed\Embed;
use Exception;

/**
 * Provides support for the use of embedded ressource.
 */
trait EmbedAwareTrait
{
    /**
     * Whether the embed is loaded.
     *
     * @var boolean
     */
    protected $isEmbedLoaded = false;

    /**
     * Format the embed link.
     *
     * @param  mixed       $value  The link to the embedable resource.
     * @param  string|null $format The format in which to return the embed.
     * @return Translation|array|string|null
     */
    public function formatEmbed($value, $format = null)
    {
        if (empty($value) && !is_numeric($value)) {
            return $value;
        }

        if ($value instanceof Translation) {
            foreach ($value->data() as $lang => $trans) {
                try {
                    $value[$lang] = (string)$this->resolveEmbedFormat($trans, $format);
                } catch (Exception $e) {
                    $value[$lang] = null;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                try {
                    $value[$k] = $this->resolveEmbedFormat($value[$k], $format);
                } catch (Exception $e) {
                    $value[$k] = null;
                }
            }
        } else {
            try {
                $value = $this->resolveEmbedFormat($value, $format);
            } catch (Exception $e) {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * Resolve the format of an embedable resource.
     *
     * @param  string      $url    The URL to the embedable resource.
     * @param  string|null $format The format in which to return the embed. Defaults to 'iframe'.
     * @return array|string|null Returns a string of HTML or the URL of the source object.
     */
    private function resolveEmbedFormat($url, $format = null)
    {
        if (is_string($url)) {
            $url = trim($url);
        }

        if (!$url) {
            return null;
        }

        $embed = Embed::create($url);

        if (empty($embed->code)) {
            return null;
        }

        // Strip width/height from iframe.
        $iframe = preg_replace('~\s*(width|height)=["\'][^"\']+["\']~', '', $embed->code);

        // Fix unencoded ampersands
        $iframe = preg_replace('~&(?!amp;)~i', '&amp;', $iframe);

        $src = $url;

        if (strpos($iframe, '<iframe') !== false) {
            // Extract the `src` attribute from embedable iframe.
            $doc = new DOMDocument();
            if ($doc->loadHTML($iframe)) {
                $elems = $doc->getElementsByTagName('iframe');
                if ($elems->length > 0) {
                    $elem = $elems->item(0);
                    if (
                        $elem &&
                        ($elem instanceof DOMElement) &&
                        $elem->hasAttribute('src')
                    ) {
                        $src = $elem->getAttribute('src');
                    }
                }
            }
        }

        if ($format === EmbedRepositoryInterface::FORMAT_SRC) {
            return $src;
        }

        if ($format === EmbedRepositoryInterface::FORMAT_ARRAY) {
            $provider = strtolower($embed->providerName);
            $image    = $embed->image;
            $id       = null;

            switch ($provider) {
                case 'youtube': {
                    if (count($embed->images) > 1) {
                        // The largest image available for YouTube will be near the end of the images array.
                        // However, the last image image is some kind of tracking pixel.
                        $images = array_slice($embed->images, 0, -1);
                        $image  = array_pop($images)['url'];
                    }

                    $regExp = '!^(?:https?://)?(?:.+\.)?(?:youtube(?:-nocookie)?\.com\/(?:(?:v|e(?:mbed)?)/|watch/|[^/\s]+\/.+/|.*[?&]v=)|youtu\.be/)(?<playback_id>[\w\-]{11})!i';
                    if (preg_match($regExp, $url, $match)) {
                        $id = $match['playback_id'];
                    }
                    break;
                }

                case 'vimeo': {
                    if (count($embed->images) > 1) {
                        // Vimeo sticks an overlay on their best quality image. Find the width, and replace it on the
                        // smaller image (without an overlay).
                        $smallImage = $embed->images[0];
                        $largeImage = array_pop($embed->images);

                        if ($largeImage) {
                            $image = preg_replace(
                                '/_(\d+)?(x)?(\d+)?\.[\w-]+$/',
                                sprintf(
                                    '_%sx%s.jpg',
                                    $largeImage['width'],
                                    $largeImage['height']
                                ),
                                $smallImage['url']
                            );
                        }
                    }

                    $regExp = '!^(?:https?://)?(?:.+\.)?vimeo\.com/(?:(?:progressive_redirect/playback|video)/)?(?<playback_id>\d+)!i';
                    if (preg_match($regExp, $url, $match)) {
                        $id = $match['playback_id'];
                    }
                    break;
                }
            }

            return [
                'iframe'   => $iframe,
                'src'      => $src,
                'image'    => $image,
                'provider' => $provider,
                'id'       => $id,
            ];
        }

        return $iframe;
    }
}

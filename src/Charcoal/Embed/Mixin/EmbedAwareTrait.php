<?php

namespace Charcoal\Embed\Mixin;

// From 'embed/embed'
use Embed\Embed;

// From 'charcoal-translator'
use Charcoal\Translator\Translation;

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

        $value = trim($value);

        if ($value instanceof Translation) {
            foreach ($value->data() as $lang => $trans) {
                $value[$lang] = $this->resolveEmbedFormat($trans, $format);
            }
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolveEmbedFormat($value[$k], $format);
            }
        } else {
            $value = $this->resolveEmbedFormat($value, $format);
        }

        return $value;
    }

    /**
     * Resolve the format of an embedable resource.
     *
     * @param  string      $link   The link to the embedable resource.
     * @param  string|null $format The format in which to return the embed. Defaults to 'iframe'.
     * @return Embed|string        Returns markup or the source for embedding the linked resource.
     */
    private function resolveEmbedFormat($link, $format)
    {
        $url = $link;

        if ($url) {
            $embed = Embed::create($url);
            $provider = strtolower($embed->providerName);

            // Extract the iframe markup.
            $iframe = preg_replace('~\s*(width|height)=["\'][^"\']+["\']~', '', $embed->code);

            // Extract the `src` attribute from embedable iframe.
            $doc = new \DOMDocument();
            $doc->loadHTML($embed->code);
            $src = $doc->getElementsByTagName('iframe')->item(0)->getAttribute('src');

            if ($format === 'array') {
                // Extract an image preview from embedable iframe.
                // Defaults to the image extracted by the Embed object.
                $images = $embed->images;
                $image  = $embed->image;

                if (count($images) > 1) {
                    if ($provider === 'youtube') {
                        // The largest image available for YouTube will be near the end of the images array.
                        // However, the last image image is some kind of tracking pixel.
                        $images = array_slice($images, 0, -1);
                        $image = array_pop($images)['url'];
                    } else if ($provider === 'vimeo') {
                        // Vimeo sticks an overlay on their best quality image. Find the width, and replace it on the
                        // smaller image (without an overlay).
                        $smallImage = $images[0];
                        $largeImage = array_pop($images);

                        if ($largeImage) {
                            $image = preg_replace(
                                "/_(\d+)?(x)?(\d+)?\.[\w-]+$/",
                                sprintf(
                                    '_%sx%s.jpg',
                                    $largeImage['width'],
                                    $largeImage['height']
                                ),
                                $smallImage['url']
                            );
                        }
                    }
                }

                if ($provider === 'youtube') {
                    $regExp = '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/';
                    preg_match($regExp, $link, $match);
                    $id = ($match && strlen($match[7]) === 11) ? $match[7] : false;
                } else {
                    $regExp = '/^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/))?([0-9]+)/';
                    preg_match($regExp, $link, $match);
                    $id = $match ? $match[5] : false;

                }

                $embed = [
                    'iframe'   => $iframe,
                    'src'      => $src,
                    'image'    => $image,
                    'provider' => $provider,
                    'id'       => $id
                ];
            } else if ($format === 'src') {
                $embed = $src;
            } else {
                $embed = preg_replace('~\s*(width|height)=["\'][^"\']+["\']~', '', $embed->code);
            }
        } else {
            $embed = '';
        }

        return $embed;
    }
}

<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

/**
 * Mux
 *
 * A video-hosting platform.
 *
 * Supported URL patterns:
 *
 * - Embed URL: `https://player.mux.com/{asset_id}`
 * - Stream URL (HLS Playlist): `https://stream.mux.com/{asset_id}.m3u8`
 *
 * URL pattern variables:
 *
 * - `asset_id` represents the video ID.
 *
 * Notes:
 *
 * - The `canonical_url` is unavailable because Mux
 *   is not a social media platform.
 */
class Mux extends AbstractResolver
{
    public const REGEXP_URL_PATTERN = '~^(?:https?://)?(?:player|stream)\.mux\.com/(?<asset_id>[a-z\d]+)(?:\.m3u8)?~i';

    /** @var array{asset_id: string} */
    private array $urlPatternVars;

    /**
     * @throws InvalidArgumentException
     * @return static
     */
    public static function from(Extractor $info)
    {
        if (!preg_match(
            static::REGEXP_URL_PATTERN,
            $info->url,
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidArgumentException(sprintf(
                'Expected a Mux video URL format, received %s',
                $info->url
            ));
        }

        $resolver = new static();
        $resolver->urlPatternVars = $resolver->filterNamedPregMatches($matches);

        return $resolver;
    }

    public function format(array $data = []): array
    {
        $data = array_replace($data, $this->urlPatternVars);

        $data['type']          = 'video';
        $data['id']            = $data['asset_id'];
        $data['image']         = $this->getThumbnailUrl($data['asset_id']);
        $data['canonical_url'] = null;
        $data['embed_url']     = $this->getEmbedUrl($data['asset_id']);
        $data['stream_url']    = $this->getStreamUrl($data['asset_id']);

        return $data;
    }

    public function getEmbedUrl(string $assetId): string
    {
        return "https://player.mux.com/{$assetId}";
    }

    public function getStreamUrl(string $assetId): string
    {
        return "https://stream.mux.com/{$assetId}.m3u8";
    }

    public function getThumbnailUrl(string $assetId): string
    {
        return "https://image.mux.com/{$assetId}/thumbnail.jpg";
    }
}

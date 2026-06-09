<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

/**
 * Bunny Stream
 *
 * A video-hosting platform.
 *
 * The recommended URL is the embed which includes the library ID
 * and the asset ID. This allows {@see Extractor} to potentially
 * discover the region ID from the related thumbnail URL.
 *
 * Supported URL patterns:
 *
 * - Embed URL ("Direct Play"):
 *   - Current: `https://player.mediadelivery.net/embed/{library_id}/{asset_id}`
 *   - Legacy: `https://iframe.mediadelivery.net/embed/{library_id}/{asset_id}`
 * - Stream URL (HLS Playlist): `https://{$regionId}.b-cdn.net/{$assetId}/playlist.m3u8`
 *
 * URL pattern variables:
 *
 * - `asset_id` represents the video ID.
 * - `region_id` represents the storage zone ID.
 *
 * Notes:
 *
 * - The `canonical_url` is unavailable because Bunny Stream
 *   is not a social media platform.
 *
 * References:
 *
 * - {@link https://docs.bunny.net/stream Documentation}.
 * - {@link https://docs.bunny.net/api-reference/stream/oembed/get-oembed oEmbed endpoint}
 */
class BunnyStream extends AbstractResolver
{
    public const REGEXP_URL_PATTERN = '~^(?:https?://)?(?:(?<region_id>[^\.]+)\.b-cdn\.net|(?:iframe|player)\.mediadelivery\.net/embed/(?<library_id>\d+))/(?<asset_id>[0-9a-f]{8}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{12})~i';

    /** @var array{asset_id: string, library_id: ?string, region_id: ?string} */
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
                'Expected a Bunny Stream video URL format, received %s',
                $info->url
            ));
        }

        $resolver = new static();
        $resolver->urlPatternVars = $resolver->filterNamedPregMatches($matches);

        /**
         * If using the "Direct Play URL" (the embed URL), the region
         * can be retrieved from the video's thumbnail URL provided
         * by Bunny's oEmbed endpoint.
         */
        if (!$resolver->urlPatternVars['region_id'] && preg_match(
            static::REGEXP_URL_PATTERN,
            $info->image,
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            $resolver->urlPatternVars['region_id'] = $matches['region_id'];
        }

        return $resolver;
    }

    public function format(array $data = []): array
    {
        $data = array_replace($data, $this->urlPatternVars);

        /** Overriding the provider name since it's two words. */
        $data['provider']      = 'bunny';
        $data['type']          = 'video';
        $data['id']            = $data['asset_id'];
        $data['canonical_url'] = null;
        $data['embed_url']     = null;
        $data['stream_url']    = null;

        if ($data['region_id']) {
            $data['image']      = $this->getThumbnailUrl($data['asset_id'], $data['region_id']);
            $data['stream_url'] = $this->getStreamUrl($data['asset_id'], $data['region_id']);
        }

        if ($data['library_id']) {
            $data['embed_url'] = $this->getEmbedUrl($data['asset_id'], $data['library_id']);
        }

        return $data;
    }

    public function getEmbedUrl(string $assetId, string $libraryId): string
    {
        return "https://player.mediadelivery.net/embed/{$libraryId}/{$assetId}";
    }

    public function getStreamUrl(string $assetId, string $regionId): string
    {
        return "https://{$regionId}.b-cdn.net/{$assetId}/playlist.m3u8";
    }

    public function getThumbnailUrl(string $assetId, string $regionId): string
    {
        return "https://{$regionId}.b-cdn.net/{$assetId}/thumbnail.jpg";
    }
}

<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

/**
 * Cloudflare Stream
 *
 * A video-hosting platform.
 *
 * Supported URL patterns:
 *
 * - Embed URL: `https://customer-{customer_id}.cloudflarestream.com/{asset_id}/iframe`
 * - Stream URL (HLS Playlist): `https://customer-{customer_id}.cloudflarestream.com/{asset_id}/manifest/video.m3u8`
 *
 * URL pattern variables:
 *
 * - `asset_id` represents the video ID.
 * - `customer_id` represents the customer account ID.
 *
 * Notes:
 *
 * - The `canonical_url` is unavailable because Cloudflare Stream
 *   is not a social media platform.
 *
 * References:
 *
 * - {@link https://developers.cloudflare.com/stream/ Documentation}
 */
class CloudflareStream extends AbstractResolver
{
    public const REGEXP_URL_PATTERN = '~^(?:https?://)?customer-(?<customer_id>[^\.]+)\.cloudflarestream\.com/(?<asset_id>[a-z\d]+)~i';

    /** @var array{asset_id: string, customer_id: string} */
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
                'Expected a Cloudflare Stream video URL format, received %s',
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

        /** Overriding the provider name since it's two words. */
        $data['provider']      = 'cloudflare';
        $data['type']          = 'video';
        $data['id']            = $data['asset_id'];
        $data['image']         = $this->getThumbnailUrl($data['asset_id'], $data['customer_id']);
        $data['canonical_url'] = null;
        $data['embed_url']     = $this->getEmbedUrl($data['asset_id'], $data['customer_id']);
        $data['stream_url']    = $this->getStreamUrl($data['asset_id'], $data['customer_id']);

        return $data;
    }

    public function getEmbedUrl(string $assetId, string $customerId): string
    {
        return "https://customer-{$customerId}.cloudflarestream.com/{$assetId}/iframe";
    }

    public function getStreamUrl(string $assetId, string $customerId): string
    {
        return "https://customer-{$customerId}.cloudflarestream.com/{$assetId}/manifest/video.m3u8";
    }

    public function getThumbnailUrl(string $assetId, string $customerId): string
    {
        return "https://customer-{$customerId}.cloudflarestream.com/{$assetId}/thumbnails/thumbnail.jpg";
    }
}

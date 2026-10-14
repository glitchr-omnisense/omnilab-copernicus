<?php

namespace Omnilab\Copernicus;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Asset;
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Model\Product;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\ProductQuery;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Copernicus Data Space Ecosystem's STAC catalogue
 * (stac.dataspace.copernicus.eu/v1): the products of the Sentinel missions,
 * of Landsat, MODIS, the Copernicus DEM and the Copernicus services, by
 * collection, place, period and cloud cover - read without an account.
 *
 * A product's assets keep the addresses the catalogue gives (s3://eodata/...
 * with an https:// alternate on download.dataspace.copernicus.eu); reading
 * them needs a CDSE access token, which is the application's business: this
 * source only searches. The thumbnail, when there is one, is open.
 *
 * Paging follows the catalogue's "next" link (its token is the cursor).
 * Without fields selection the catalogue serves at most 100 Sentinel-2
 * items a page.
 */
final class CopernicusSource extends HttpSource
{
    public const BASE_URI = 'https://stac.dataspace.copernicus.eu/v1/';

    /** The most per page the catalogue serves whole items for. */
    public const MAX_PER_PAGE = 100;

    /**
     * @param list<string> $collections what a query that names none searches
     */
    public function __construct(
        HttpClientInterface $http,
        private readonly array $collections = ['sentinel-2-l2a'],
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'copernicus';
    }

    public function capabilities(): array
    {
        return [Capability::PRODUCTS, Capability::AREA, Capability::CLOUD_COVER];
    }

    public function products(ProductQuery $query): Page
    {
        $collections = $query->collections ?: $this->collections;
        if (!$collections) {
            throw NotSupportedException::operation('copernicus', 'search every collection at once (name one: sentinel-2-l2a, sentinel-1-grd...)');
        }
        $data = $this->getJson('search', [
            'collections' => implode(',', $collections),
            'bbox' => $query->place instanceof BBox ? implode(',', $query->place->toArray()) : null,
            'intersects' => $query->place instanceof Point ? json_encode($query->place->toGeoJson(), \JSON_THROW_ON_ERROR) : null,
            'datetime' => null === $query->from && null === $query->to ? null : self::instant($query->from).'/'.self::instant($query->to),
            'limit' => max(1, min(self::MAX_PER_PAGE, $query->limit)),
            'sortby' => ProductQuery::OLDEST === $query->sort ? '+datetime' : '-datetime',
            'filter' => null === $query->maxCloudCover ? null : 'eo:cloud_cover<='.self::number($query->maxCloudCover),
            'filter-lang' => null === $query->maxCloudCover ? null : 'cql2-text',
            'token' => $query->cursor,
        ]) ?? [];

        $products = array_map($this->toProduct(...), $data['features'] ?? []);
        $next = null;
        foreach ($data['links'] ?? [] as $link) {
            if ('next' === ($link['rel'] ?? null) && \is_string($link['href'] ?? null)) {
                parse_str((string) parse_url($link['href'], \PHP_URL_QUERY), $params);
                $next = isset($params['token']) ? (string) $params['token'] : null;
            }
        }

        return new Page($products, $products ? $next : null, isset($data['numberMatched']) ? (int) $data['numberMatched'] : null);
    }

    /** @param array<string, mixed> $item a STAC item */
    private function toProduct(array $item): Product
    {
        $properties = array_filter($item['properties'] ?? [], static fn (string $key) => !str_starts_with($key, '_'), \ARRAY_FILTER_USE_KEY);
        $assets = [];
        foreach ($item['assets'] ?? [] as $name => $asset) {
            if (!\is_string($asset['href'] ?? null)) {
                continue;
            }
            $alternates = [];
            foreach ($asset['alternate'] ?? [] as $alternate => $other) {
                if (\is_string($other['href'] ?? null)) {
                    $alternates[(string) $alternate] = $other['href'];
                }
            }
            $assets[(string) $name] = new Asset(
                name: (string) $name,
                href: $asset['href'],
                type: $asset['type'] ?? null,
                roles: array_values(array_map('strval', $asset['roles'] ?? [])),
                title: $asset['title'] ?? null,
                size: isset($asset['file:size']) ? (int) $asset['file:size'] : null,
                alternates: $alternates,
            );
        }
        $preview = null;
        foreach ($assets as $asset) {
            if ($asset->hasRole('thumbnail') && null !== ($preview = $asset->httpsHref())) {
                break;
            }
        }
        $self = null;
        foreach ($item['links'] ?? [] as $link) {
            if ('self' === ($link['rel'] ?? null)) {
                $self = $link['href'] ?? null;
            }
        }

        return new Product(
            id: (string) $item['id'],
            collection: (string) ($item['collection'] ?? ''),
            datetime: self::time($properties['datetime'] ?? null),
            start: self::time($properties['start_datetime'] ?? null),
            end: self::time($properties['end_datetime'] ?? null),
            bbox: isset($item['bbox']) && \is_array($item['bbox']) && \count($item['bbox']) >= 4 ? BBox::fromArray($item['bbox']) : null,
            geometry: \is_array($item['geometry'] ?? null) ? $item['geometry'] : null,
            properties: $properties,
            assets: $assets,
            preview: $preview,
            url: \is_string($self) ? $self : null,
            source: 'copernicus',
        );
    }

    /** An end of the period in RFC 3339, UTC; ".." when open. */
    private static function instant(?\DateTimeInterface $time): string
    {
        return null === $time ? '..' : \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.4F', $value), '0'), '.');
    }
}

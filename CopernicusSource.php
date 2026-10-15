<?php

namespace Omnilab\Copernicus;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Stac;
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
 * The catalogue is read by the core's STAC reader (Source\Stac): the cloud
 * cover filtered in CQL2 text, paging by the "next" link's token (the
 * cursor).
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
        $data = $this->getJson('search', Stac::parameters($query, $collections, self::MAX_PER_PAGE, Stac::CQL2) + ['token' => $query->cursor]) ?? [];

        return Stac::page($data, 'copernicus', 'token');
    }
}

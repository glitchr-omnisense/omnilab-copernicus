<?php

namespace Omnilab\Copernicus\Tests;

use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Source\Capability;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Query;
use Omnilab\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from stac.dataspace.copernicus.eu/v1 on 2026-10-15:
 * search-page1.json and search-page2.json (the Sentinel-2 L2A scenes over
 * Strasbourg, 48.5734 N 7.7521 E, from 2026-10-05 to 10-15 under 30 %
 * cloud cover, newest first, two a page, followed by the next link's
 * token; each item's assets cut from 47 to five, the statistics left out),
 * error-collection.json (a collection the catalogue does not have: 400).
 */
final class CopernicusSourceTest extends TestCase
{
    private const TOKEN = 'next:sentinel-2-l2a:S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607';

    /** @var list<array<string, string>> */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = []): SourceInterface
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $this->calls[] = ['path' => (string) parse_url($url, \PHP_URL_PATH)] + $query;
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                'sentinel-2-l2a' !== ($query['collections'] ?? null) => 'error-collection.json',
                self::TOKEN === ($query['token'] ?? null) => 'search-page2.json',
                default => 'search-page1.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture), ['http_code' => 'error-collection.json' === $fixture ? 400 : 200]);
        });

        return (new CopernicusSourceFactory($http))->create($options);
    }

    private static function query(): ProductQuery
    {
        return new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521), new \DateTimeImmutable('2026-10-05T00:00:00Z'), new \DateTimeImmutable('2026-10-15T00:00:00Z'), maxCloudCover: 30, limit: 2);
    }

    public function testTheSentinel2ScenesOverStrasbourgUnderThirtyPercentCloud(): void
    {
        $page = $this->source()->products(self::query());

        self::assertCount(2, $page);
        self::assertSame(self::TOKEN, $page->next, 'the next link\'s token');
        self::assertNull($page->total, 'the catalogue does not count');
        $scene = $page->items[0];
        self::assertSame('S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607', $scene->id);
        self::assertSame('sentinel-2-l2a', $scene->collection);
        self::assertSame('2026-10-12T10:29:11+00:00', $scene->datetime->format(\DATE_ATOM));
        self::assertSame(28.94, $scene->cloudCover());
        self::assertSame('sentinel-2a', $scene->platform());
        self::assertSame('MGRS-32UMU', $scene->properties['grid:code']);
        self::assertArrayNotHasKey('_private', $scene->properties, 'the catalogue\'s private block is left out');
        self::assertTrue($scene->bbox->contains(new Point(48.5734, 7.7521)));
        self::assertSame('Polygon', $scene->geometry['type']);
        self::assertSame('https://stac.dataspace.copernicus.eu/v1/collections/sentinel-2-l2a/items/S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607', $scene->url);
        self::assertSame('copernicus', $scene->source);
        self::assertSame(1.84, $page->items[1]->cloudCover());

        $tci = $scene->asset('TCI_10m');
        self::assertStringStartsWith('s3://eodata/Sentinel-2/MSI/L2A/2026/10/12/', $tci->href, 'kept as the catalogue gives it');
        self::assertStringStartsWith('https://download.dataspace.copernicus.eu/odata/v1/Products(922e9d3c-eea1-4125-acc8-7a97f0400c2a)/', $tci->httpsHref());
        self::assertSame('image/jp2', $tci->type);
        self::assertContains('visual', $tci->roles);
        self::assertArrayHasKey('https', $tci->alternates);
        self::assertSame(['thumbnail'], array_keys($scene->assets('thumbnail')));
        self::assertStringStartsWith('https://datahub.creodias.eu/odata/v1/Assets(', $scene->preview, 'the open thumbnail');
        self::assertSame('application/zip', $scene->asset('Product')->type);
    }

    public function testTheQueryAsTheCatalogueReadsIt(): void
    {
        $this->source()->products(self::query());

        self::assertSame([
            'path' => '/v1/search',
            'collections' => 'sentinel-2-l2a',
            'intersects' => '{"type":"Point","coordinates":[7.7521,48.5734]}',
            'datetime' => '2026-10-05T00:00:00Z/2026-10-15T00:00:00Z',
            'limit' => '2',
            'sortby' => '-datetime',
            'filter' => 'eo:cloud_cover<=30',
            'filter-lang' => 'cql2-text',
        ], $this->calls[0]);

        $this->source()->products(new ProductQuery(place: new BBox(7.68, 48.53, 7.83, 48.64), from: new \DateTimeImmutable('2026-10-05T02:00:00+02:00'), limit: 500, sort: ProductQuery::OLDEST));
        self::assertSame(['path' => '/v1/search', 'collections' => 'sentinel-2-l2a', 'bbox' => '7.68,48.53,7.83,48.64', 'datetime' => '2026-10-05T00:00:00Z/..', 'limit' => '100', 'sortby' => 'datetime'], $this->calls[1], 'the configured collection, a box, an open end in UTC, at most 100 a page');
    }

    public function testPagesFollowTheNextToken(): void
    {
        $source = $this->source();
        $first = $source->products(self::query());
        $second = $source->products(self::query()->with(['cursor' => $first->next]));

        self::assertSame(self::TOKEN, $this->calls[1]['token']);
        self::assertSame(['S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235', 'S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235'], array_map(static fn ($p) => $p->id, $second->items));
        self::assertTrue($second->isLast());
    }

    public function testErrorsAndWhatItDoesNotDo(): void
    {
        try {
            $this->source()->products(new ProductQuery(['nope']));
            self::fail('an unknown collection is a 400');
        } catch (ProviderException $e) {
            self::assertSame(400, $e->status);
            self::assertStringContainsString('One or more requested collections do not exist.', $e->getMessage());
        }
        try {
            $this->source(new MockResponse('', ['http_code' => 502]))->products(self::query());
            self::fail('a 502 is an UnavailableException');
        } catch (UnavailableException) {
        }
        try {
            $this->source(null, ['collections' => []])->products(new ProductQuery());
            self::fail('every collection at once is refused');
        } catch (NotSupportedException) {
        }
        self::assertSame([Capability::PRODUCTS, Capability::AREA, Capability::CLOUD_COVER], $this->source()->capabilities());

        $this->expectException(NotSupportedException::class);
        $this->source()->search(new Query('Strasbourg'));
    }
}

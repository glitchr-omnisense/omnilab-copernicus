# omnilab/copernicus

The Copernicus Data Space Ecosystem's STAC catalogue (`https://stac.dataspace.copernicus.eu/v1/`,
STAC API 1.0) for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): the scenes
and products of Sentinel-1, -2, -3 and -5P, Landsat, MODIS, the Copernicus DEM and the Copernicus
services, by collection, place, period and cloud cover, as `Product`s. One shape: products. No key
to search.

## Installation

```sh
composer require omnilab/copernicus
```

```php
use Omnilab\Copernicus\CopernicusSourceFactory;

$copernicus = (new CopernicusSourceFactory($httpClient))->create([
    'collections' => ['sentinel-2-l2a'],   // optional
]);
```

With the Symfony bundle, the factory is registered as `copernicus` (see the core's docs).

## Credentials

None to search: the Data Space's STAC catalogue is open, no key, no account. Reading an asset's
file (a band, the product's zip) needs a Copernicus Data Space Ecosystem access token, which this
package does not obtain nor send: the assets' addresses are given as the catalogue gives them, and
the download is the application's ([the README](https://github.com/glitchr-omnisense/omnilab-copernicus#downloading-out-of-scope)).

## Options

The factory's options, with the defaults it applies (`CopernicusSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `collections` | `list<string>` | `[sentinel-2-l2a]` | no | the collections a query that names none searches; empty: such a query is refused (`NotSupportedException`) rather than searching the catalogue's 427 collections |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: the Data Space publishes quotas, no rate for its catalogue |
| `base_uri` | `string` | `https://stac.dataspace.copernicus.eu/v1/` | no | the STAC API (`/search` is joined to it) |

## Capabilities

`capabilities()` answers `products`, `area`, `cloud_cover`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | no |  |
| `search` | no |  |
| `series` | no |  |
| `products` | yes | an item search: the query's collections or the `collections` option, a place, a period, a cloud cover, newest or oldest first, 100 a page at most, the next page by the `token` of the `next` link; no `total` (the catalogue does not count) |
| `occurrences` | no |  |
| `datasets` | no | the catalogue's `/collections` is not read through the contract |
| `jobs` | no |  |
| `open` | no | not offered: a download needs a CDSE token, out of this package |
| `structure` | no |  |
| `cross_references` | no |  |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | yes | a `BBox` as `bbox`; a `Point` as `intersects` |
| `station` | no |  |
| `cloud_cover` | yes | `filter=eo:cloud_cover<=N` in CQL2 text; a radar collection has no cloud cover, so the filter matches nothing there |

## Calls

`products(ProductQuery)` only, as a GET on `https://stac.dataspace.copernicus.eu/v1/search`
(STAC API 1.0, item search with its filter and sort extensions), written and read by the core's
STAC reader (`Omnilab\Source\Stac`, shared with omnilab/earthdata's `stac` option):

| `ProductQuery` | STAC |
|---|---|
| `collections` | `collections`; the option's when empty (none: refused rather than searching 400 collections) |
| `place` | a `Point`: `intersects` (GeoJSON); a `BBox`: `bbox` |
| `from`, `to` | `datetime`, `from/to` in RFC 3339, UTC; an open end is `..` |
| `maxCloudCover` | `filter=eo:cloud_cover<=N`, `filter-lang=cql2-text` |
| `limit` | `limit`, 100 at most (the catalogue refuses more Sentinel-2 items without fields selection) |
| `sort` | `sortby=-datetime` (newest, the default) or `datetime` (oldest: the same order as `+datetime`, checked on 2026-10-15; the CMR refuses the `+`) |
| `cursor` | `token`: the one of the `next` link of the page before |

The catalogue does not count the matches: `Page::$total` is null. Some collection ids, as the
catalogue named them on 2026-10-15 (`/v1/collections` lists the 427): `sentinel-1-grd`,
`sentinel-1-slc`, `sentinel-2-l1c`, `sentinel-2-l2a`, `sentinel-2-global-mosaics`,
`sentinel-3-olci-1-efr-ntc`, `sentinel-3-sl-2-lst-ntc`, `sentinel-5p-l2-no2-offl`,
`landsat-c2-l1-oli-tirs`, `cop-dem-glo-30-dged-cog`, `modis-terra-mod13q1`, and the Copernicus
Land Monitoring Service's `clms_*`.

## What a product holds

`id`, `collection`, `datetime` (and `start`, `end` from `start_datetime`, `end_datetime`),
`bbox` and the GeoJSON `geometry` of its footprint, `properties` as the catalogue names them
(`eo:cloud_cover`, `platform`, `constellation`, `instruments`, `grid:code`, `sat:relative_orbit`,
`sat:orbit_state`, `view:sun_elevation`, `processing:version`, `product:type`... - its private
block left out), `assets` by name (a Sentinel-2 L2A scene has 47: each band at 10, 20 and 60 m,
`TCI_10m`, `SCL_20m`, `thumbnail`, `Product`, the metadata files), `preview` (the thumbnail's
https address), `url` (the item's own address).

## What the catalogue does not give here

No download (see the README); no collection listing nor queryables through the contract (the
catalogue's `/collections` and `/queryables` are open); no filter beyond cloud cover (CQL2 on any
queryable would be a small addition); a radar collection (Sentinel-1) has no cloud cover, so a
cloud filter on it matches nothing.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Model\Point;
use Omnilab\Source\ProductQuery;
use Symfony\Component\HttpClient\HttpClient;

$copernicus = (new CopernicusSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$query = new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521), new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-26'), maxCloudCover: 50, limit: 3);
$page = $copernicus->products($query);
foreach ($page as $scene) {
    printf("%s  %5.2f %% cloud  %s  %d assets, preview %s\n", $scene->datetime->format('Y-m-d H:i'), $scene->cloudCover(), $scene->id, count($scene->assets), $scene->preview ? 'yes' : 'no');
}
echo $page->isLast() ? "last page\n" : "next page: token ".substr($page->next, 0, 12)."...\n";
```

```
2026-10-22 10:30  46.38 % cloud  S2A_MSIL2A_20261022T103031_N0513_R108_T32UMU_20261022T170604  47 assets, preview yes
2026-10-15 10:30  37.18 % cloud  S2B_MSIL2A_20261015T103019_N0513_R108_T32UMU_20261015T142540  47 assets, preview yes
2026-10-15 10:30  42.55 % cloud  S2B_MSIL2A_20261015T103019_N0513_R108_T32ULU_20261015T142540  47 assets, preview yes
next page: token next:sentine...
```

(as answered on 2026-10-27; the times are UTC). The next page is
`$copernicus->products($query->with(['cursor' => $page->next]))`.

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        copernicus: { factory: copernicus, options: { mailto: '%env(OMNILAB_MAILTO)%', collections: [sentinel-2-l2a] } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: a query that names no collection when the `collections` option is empty is
refused (`NotSupportedException`); the operations the catalogue does not serve here (entities,
series, occurrences, datasets, files) are `HttpSource`'s refusals. An unknown collection is the
catalogue's 400, a `ProviderException` with its message (recorded in `Tests/Fixtures`).

## Verified, and not

The tests (four) read answers recorded from the catalogue on 2026-10-15 (`Tests/Fixtures`): the Sentinel-2 L2A scenes over Strasbourg
from 2026-10-05 to 10-15 under 30 % cloud, two a page over two pages (each item's assets cut to
five), and the 400 of an unknown collection.

Against the catalogue on 2026-10-27: the Sentinel-2 L2A scenes over Strasbourg from 10-01 to 10-26
under 50 % cloud, three a page, with their 47 assets, previews and the next page's token (the
example above). Not verified here: the other collections' items beyond their ids, the catalogue's
quotas, a download (out of this package).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the European Commission, ESA or the Copernicus Data Space Ecosystem. The Sentinel
data are free and open under the
[Sentinel data legal notice](https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice)
(credit "contains modified Copernicus Sentinel data [year]"), the other collections under their
own licences, and the Data Space's [terms and conditions](https://dataspace.copernicus.eu/terms-and-conditions)
and quotas apply. Whoever runs the package is responsible for complying with them and for any use
made of what it returns: see the README's
[Responsibility](https://github.com/glitchr-omnisense/omnilab-copernicus#responsibility) section.

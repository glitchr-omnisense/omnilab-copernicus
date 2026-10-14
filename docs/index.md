# omnilab/copernicus

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

## Calls

`products(ProductQuery)` only, as a GET on `https://stac.dataspace.copernicus.eu/v1/search`
(STAC API 1.0, item search with its filter and sort extensions):

| `ProductQuery` | STAC |
|---|---|
| `collections` | `collections`; the option's when empty (none: refused rather than searching 400 collections) |
| `place` | a `Point`: `intersects` (GeoJSON); a `BBox`: `bbox` |
| `from`, `to` | `datetime`, `from/to` in RFC 3339, UTC; an open end is `..` |
| `maxCloudCover` | `filter=eo:cloud_cover<=N`, `filter-lang=cql2-text` |
| `limit` | `limit`, 100 at most (the catalogue refuses more Sentinel-2 items without fields selection) |
| `sort` | `sortby=-datetime` (newest, the default) or `+datetime` |
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

## Tests

`Tests/Fixtures` holds answers recorded on 2026-10-15: the Sentinel-2 L2A scenes over Strasbourg
from 2026-10-05 to 10-15 under 30 % cloud, two a page over two pages (each item's assets cut to
five), and the 400 of an unknown collection.

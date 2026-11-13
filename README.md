# omnilab/copernicus

The Copernicus Data Space Ecosystem's STAC catalogue for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): the products of Sentinel-1, -2,
-3, -5P and -6, Landsat, MODIS, the Copernicus DEM and the Copernicus services, searched by
collection, point or box, period and cloud cover, page by page - their footprint, properties,
assets and thumbnail. Searching needs no account.

```php
$copernicus = (new CopernicusSourceFactory($http))->create();
$page = $copernicus->products(new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521), new \DateTimeImmutable('-10 days'), maxCloudCover: 30));
foreach ($page as $scene) {
    $scene->datetime;                        // 2026-10-12T10:29:11Z
    $scene->cloudCover();                    // 1.84
    $scene->properties['grid:code'];         // MGRS-32ULU
    $scene->preview;                         // an open thumbnail
    $scene->asset('TCI_10m')->href;          // s3://eodata/...: kept as the catalogue gives it
    $scene->asset('TCI_10m')->httpsHref();   // https://download.dataspace.copernicus.eu/...: needs a token
}
$copernicus->products($query->with(['cursor' => $page->next]));
```

```yaml
omnilab:
    sources:
        copernicus:
            factory: copernicus
            options:
                collections: [sentinel-2-l2a]    # what a query that names none searches
```

## Install and a first call

```sh
composer require omnilab/copernicus
```

A real call, with no account and no key: the Sentinel-2 L2A scenes over Strasbourg in the first half
of October 2026, from the Copernicus Data Space's STAC catalogue (searching needs no account).
`mailto` is your address, sent in the User-Agent so that the service can reach you before it blocks
a caller; the source keeps to the limits its [`policy.json`](policy.json) declares.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Model\Point;
use Omnilab\Source\ProductQuery;

$copernicus = (new CopernicusSourceFactory())->create(['mailto' => 'you@example.org']);   // your address, in the User-Agent
$scenes = $copernicus->products(new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521),
    new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-14T23:59:59Z'), maxCloudCover: 30, limit: 5));
foreach ($scenes as $scene) {
    echo $scene->datetime->format('Y-m-d H:i'), ' ', $scene->cloudCover(), ' % cloud ', $scene->id, "\n";
}
```

Its output on 2026-11-13:

```
2026-10-12 10:29 28.94 % cloud S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607
2026-10-12 10:29 1.84 % cloud S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607
2026-10-05 10:28 11.1 % cloud S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235
2026-10-05 10:28 5.62 % cloud S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235
```

## Downloading: out of scope

Every asset's address is kept: an `s3://eodata/...` one (the CDSE S3 endpoint, with S3 keys) and
an `https://download.dataspace.copernicus.eu/...` alternate. Reading either needs a free CDSE
account: an access token from
`https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token` sent as
`Authorization: Bearer` - by the application, with its own HTTP client. This package searches
only, and has no token option: a token would be an account's secret in the source's
configuration for no call it makes. The thumbnail (`$product->preview`) is open.

## Terms of use

The catalogue is open. The Sentinel data are free and open to all under the
[Sentinel data legal notice](https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice)
(credit "contains modified Copernicus Sentinel data [year]"); the other collections carry their
own licences (each collection's `license` link); the Data Space's
[terms and conditions](https://dataspace.copernicus.eu/terms-and-conditions) and quotas apply.

See [docs/](docs/index.md).

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://documentation.dataspace.copernicus.eu/Quotas.html on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnilab/blob/1.x/docs/policies.md)).

- Limits: not published (unknown); at most 4 in flight.
- Licence: Copernicus Sentinel Data legal notice (Regulation (EU) No 1159/2013); commercial use: allowed.
- Credit: required: Copernicus Sentinel data [Year] (https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice).
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the Copernicus programme and its data providers. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.

# omnilab/copernicus

The Copernicus Data Space Ecosystem's STAC catalogue for
[glitchr/omnilab](https://github.com/glitchr-studio/omnilab): the products of Sentinel-1, -2,
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

License: MIT.

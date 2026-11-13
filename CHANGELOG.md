# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab Copernicus: the products of the Copernicus Data Space Ecosystem's STAC catalogue -
  Sentinel-1, -2, -3, -5P, Landsat, MODIS, the Copernicus DEM and services - searched by collection,
  point or box, period and cloud cover, page by page, with their footprints, properties, assets and
  thumbnails; open without an account (downloading the assets needs a CDSE token and stays with the
  application).

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/copernicus` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.

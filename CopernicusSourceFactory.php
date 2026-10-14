<?php

namespace Omnilab\Copernicus;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The Copernicus Data Space's STAC catalogue: Sentinel and Copernicus
 * products by place, period and cloud cover - no account needed to search.
 *
 *   options:
 *     collections: [sentinel-2-l2a]    # what a query that names none searches
 *     base_uri: 'https://stac.dataspace.copernicus.eu/v1/'
 */
final class CopernicusSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'copernicus',
            'omnilab.required_options' => [],
            'collections' => ['sentinel-2-l2a'],
            'base_uri' => CopernicusSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new CopernicusSource(
            $this->http ?? HttpClient::create(),
            array_values(array_map('strval', (array) $c->get('collections'))),
            (string) $c->get('base_uri', CopernicusSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}

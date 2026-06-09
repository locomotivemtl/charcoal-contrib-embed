# Charcoal Embed

[![License][badge-license]][locomotivemtl/charcoal-contrib-embed]
[![Latest Stable Version][badge-version]][locomotivemtl/charcoal-contrib-embed]

A [Charcoal][locomotivemtl/charcoal-app] service provider for the [Embed][embed/embed] library.

## Installation

The preferred and only supported method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-embed
```

### Dependencies

#### Required

* **[PHP](https://php.net)** v7.4 or v8
* **[locomotivemtl/charcoal-app]** : v0.8+
* **[locomotivemtl/charcoal-property]** : v0.8+
* **[guzzlehttp/psr7]** : v2.11
* **[embed/embed]** : v3.4

## Service Provider

### Services

- **embed/repository** is an instance of `Charcoal\Embed\EmbedRepository` and serves as the primary API for fetching and caching embed data.

## Configuration

Including the embed module in the projects's configuration file will register the service provider and the [locomotivemtl/charcoal-admin] route for remotely updating the cached embed information:

```json
{
    "modules": {
       "charcoal/embed/embed": {}
    }
}
```

Otherwise, the service provider can be included directly:

```json
{
    "service_providers": {
        "charcoal/embed/service-provider/embed": {}
    }
}
```

The contrib package can be configured from the the project's configuration file (default values for illustration):

```json
{
    "embed_config": {
        "format": "array",
        "table": "embed_cache"
    }
}
```

## Usage

The contrib package provides a custom `embed` model property that upon save will fetch the URL's embed information and store a subset of that data in a custom database table.

```json
{
    "video": {
        "type": "embed",
        "l10n": true,
        "label": "Video",
        "notes": "Absolute URL: <code>https://www.youtube.com/watch?v={video_id}</code>"
    }
}
```

A URL's embed data can be retrieved using the `EmbedRepository`:

```php
$this->embedRepository()->getEmbedData('https://youtube.com/{video_id}');
```

## Development

The package can be linted with [squizlabs/php_codesniffer] and tested with [phpunit/phpunit] from the following command:

```shell
$ composer tests
```

### Coding Style

The charcoal-contrib-embed module follows the Charcoal coding-style:

* _[PSR-1]_
* _[PSR-2]_
* _[PSR-4]_, autoloading is therefore provided by _Composer_.
* _[phpDocumentor](http://phpdoc.org/)_ comments.
* [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

## Credits

* [Locomotive](https://locomotive.ca/)

## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.

[embed/embed]:                           https://packagist.org/packages/embed/embed
[guzzlehttp/psr7]:                       https://packagist.org/packages/guzzlehttp/psr7
[locomotivemtl/charcoal-app]:            https://packagist.org/packages/locomotivemtl/charcoal-app
[locomotivemtl/charcoal-contrib-embed]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-embed
[locomotivemtl/charcoal-property]:       https://packagist.org/packages/locomotivemtl/charcoal-property
[phpunit/phpunit]:                       https://packagist.org/packages/phpunit/phpunit
[squizlabs/php_codesniffer]:             https://packagist.org/packages/squizlabs/php_codesniffer

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square

[PSR-1]:  https://www.php-fig.org/psr/psr-1/
[PSR-2]:  https://www.php-fig.org/psr/psr-2/
[PSR-3]:  https://www.php-fig.org/psr/psr-3/
[PSR-4]:  https://www.php-fig.org/psr/psr-4/
[PSR-6]:  https://www.php-fig.org/psr/psr-6/
[PSR-7]:  https://www.php-fig.org/psr/psr-7/
[PSR-11]: https://www.php-fig.org/psr/psr-11/

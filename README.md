Charcoal Embed
===============

[![License][badge-license]][charcoal-contrib-embed]
[![Latest Stable Version][badge-version]][charcoal-contrib-embed]
[![Code Quality][badge-scrutinizer]][dev-scrutinizer]
[![Coverage Status][badge-coveralls]][dev-coveralls]
[![Build Status][badge-travis]][dev-travis]

A [Charcoal][charcoal-app] service provider embed property.



## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Service Provider](#service-provider)
    -   [Parameters](#parameters)
    -   [Services](#services)
-   [Configuration](#configuration)
-   [Usage](#usage)
-   [Development](#development)
    -  [API Documentation](#api-documentation)
    -  [Development Dependencies](#development-dependencies)
    -  [Coding Style](#coding-style)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-embed
```



### Dependencies

#### Required

-   [**PHP 5.6+**](https://php.net): _PHP 7_ is recommended.
-   **[charcoal-property]** : ^0.7
-   **[guzzle]** : ~0.6
-   **[embed]** : ^3.3


## Service Provider

The following services are provided with the use of [charcoal-contrib-embed]

### Services

- **embed/repository** instance of `Embed\EmbedRepository`
    - **Charcoal\Embed\Mixin\EmbedRepositoryTrait** provided for ease of use.

## Configuration

Include the embed module in the projects's config file.
This will provide everything needed for [charcoal-contrib-embed] to work properly.
No need for metadata/views/action/routes path etc.

```Json
{
    "modules": {
       "charcoal/embed/embed": {}
    }
}
```

You can provide additional configurations in the project's config file like so : 

```json
{
    "embed_config": {
        "ttl": 3600,
        "format": "array",
        "table": "embed_cache"
    }
}
```

This is the actual default config.

## Usage

The Embed Contrib provides a custom Property type : "embed".
When using it, the property will fetch embed data from media providers and store them in a third table.

```json
{
    "video": {
        "type": "embed",
        "l10n": true,
        "label": {
            "en": "Video",
            "fr": "Video"
        },
        "notes": "Full video url. ex.: https://www.youtube.com/watch?v=_VIDEO_ID"
    }
}
```

To load the embed data from database, use **EmbedRepository** service method

```php
$this->embedRepository()
     ->embedData('https://youtube.com/someid');
```

Dependency injection  :

```php
use EmbedRepositoryTrait;

/**
 * Inject dependencies from a DI Container.
 *
 * @param  Container $container A dependencies container instance.
 * @return void
 */
protected function setDependencies(Container $container)
{
    parent::setDependencies($container);
    $this->setEmbedRepository($container['embed/repository']);
}
```

## Development

To install the development environment:

```shell
$ composer install
```

To run the scripts (phplint, phpcs, and phpunit):

```shell
$ composer test
```



### API Documentation

-   The auto-generated `phpDocumentor` API documentation is available at:  
    [https://locomotivemtl.github.io/charcoal-contrib-embed/docs/master/](https://locomotivemtl.github.io/charcoal-contrib-embed/docs/master/)
-   The auto-generated `apigen` API documentation is available at:  
    [https://codedoc.pub/locomotivemtl/charcoal-contrib-embed/master/](https://codedoc.pub/locomotivemtl/charcoal-contrib-embed/master/index.html)



### Development Dependencies

-   [php-coveralls/php-coveralls][phpcov]
-   [phpunit/phpunit][phpunit]
-   [squizlabs/php_codesniffer][phpcs]



### Coding Style

The charcoal-contrib-embed module follows the Charcoal coding-style:

-   [_PSR-1_][psr-1]
-   [_PSR-2_][psr-2]
-   [_PSR-4_][psr-4], autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.



## Credits

-   [Locomotive](https://locomotive.ca/)



## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.



[charcoal-contrib-embed]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-embed
[charcoal-property]:       https://packagist.org/packages/locomotivemtl/charcoal-property
[guzzle]:                  https://packagist.org/packages/guzzlehttp/guzzle
[embed]:                   https://packagist.org/packages/embed/embed
[charcoal-app]:            https://packagist.org/packages/locomotivemtl/charcoal-app

[dev-scrutinizer]:    https://scrutinizer-ci.com/g/locomotivemtl/charcoal-contrib-embed/
[dev-coveralls]:      https://coveralls.io/r/locomotivemtl/charcoal-contrib-embed
[dev-travis]:         https://travis-ci.org/locomotivemtl/charcoal-contrib-embed

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square
[badge-scrutinizer]:  https://img.shields.io/scrutinizer/g/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square
[badge-coveralls]:    https://img.shields.io/coveralls/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square
[badge-travis]:       https://img.shields.io/travis/locomotivemtl/charcoal-contrib-embed.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/

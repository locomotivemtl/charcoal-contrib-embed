# Changelog

## 0.2.2

* Ensure `embed_data` is not empty during update to avoid purging the previous value in the database.

## 0.2.1

* Removed call to validateTtl from `EmbedRepository->embedData()`, as it was making a cURL request for each fetched embed if it hadn't been fetched in 60 minutes (default TTL)

## 0.2.0.5

* Relax constraint on `locomotivemtl/charcoal-app` to `~0.8`.

## 0.2.0.4

* Relax Composer constraint on `guzzlehttp/promises` to restore support for Guzzle v6.
* Ensure `DOMNodeList` returns a `DOMElement`.

## 0.2.0.3

* Fixed the utility functions of `guzzlehttp/promises` replaced with a helper methods.
* Add missing Composer dependencies: `locomotivemtl/charcoal-app` and `locomotivemtl/charcoal-config`, `guzzlehttp/promises`, `pimple/pimple`, and `psr/log`.
* Relax Composer constraint on `embed/embed` to `^3.4`.

## 0.2.0.2

* Check if embed code contains `<iframe>` before parsing through `DOMDocument`.

## 0.2.0.1

* Fix embed format usage in action controller.

## 0.2.0

* Updated Composer requirements:
    * PHP ^5.6, ^7.2, ^8.0
    * embed/embed ^3.4.10 (supports PHP ^5.6, ^7.0, ^8.0)
    * guzzlehttp/guzzle ^6.0 (supports PHP >=5.5) or ^7.0 (supports PHP ^7.2.5)
    * locomotivemtl/charcoal-property ^0.8 (supports PHP ^5.6, ^7.0)
* Added methods `isValidFormat()` and `assertValidFormat()` to `EmbedRepository`.
* Added class constants `FORMAT_*` to `EmbedRepositoryInterface` to standardize supported formats.
* Cleaned-up block comments.
* Sorted PHP imports according to PSR-12.
* Fixed linting issues and improved syntax.

## 0.1.7

* Fix formatting of embedable URL
* Prevent unnecessary requests to database
* Fetch embed data from third-party if none returned from database
* Refactor `EmbedAwareTrait::resolveEmbedFormat()`:
    * Reorganized method to return early instead of nesting.
    * Changed signature to allow `$format` to be null.
    * Fixed edge cases where:
        * `&` are already encoded as `&amp;`.
        * `Embed` would return a `DataInterface` instance that contains no HTML.
        * HTML might be invalid when processed by `DOMDocument`.
        * HTML does not contain any `<iframe>` elements.
        * ID in URL can not be correctly matched by regular expression.

## 0.1.6

* Fixed unencoded ampersands causing `DOMDocument` errors.

## 0.1.5

* Added support for multilingual model properties.

## 0.1.4

* Relax Composer constraint on `locomotivemtl/charcoal-property` to `~0.7`.

## 0.1.3

* Activate SSL verification with cURL.

## 0.1.2

* Decrase timeout for HTTP request to validate `last_update_date`. 

## 0.1.1

* Fix case when not refreshing data when `last_update_date` is `null`.

## 0.1.0

* Initial release

# LaravelSmartRestful

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

This is CRUD package for create some wonderful thing quickly.

## Structure

If any of the following are applicable to your project, then the directory structure should follow industry best practices by being named the following.

```
bin/        
config/
src/
tests/
vendor/
```


## Install

Via Composer

``` bash
$ composer require alive2212/laravel-smart-restful
```

install language
``` bash
$ php artisan vendor:publish --tag=laravel_smart_restful.lang
```


### Laravel

### Lumen

after install put following into bootstrap\app.php

```php
$app->instance('path.config', app()->basePath() . DIRECTORY_SEPARATOR . 'config');
$app->instance('path.storage', app()->basePath() . DIRECTORY_SEPARATOR . 'storage');
$app->register(Laravel\Scout\ScoutServiceProvider::class);
$app->configure('scout');
```


## Usage

``` php
$skeleton = new Alive2212\LaravelSmartRestful();
echo $skeleton->echoPhrase('Hello, League!');
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email alive2212@gmail.com instead of using the issue tracker.

## Credits

- [Babak Nodoust][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/Alive2212/LaravelSmartRestful.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/Alive2212/LaravelSmartRestful/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/Alive2212/LaravelSmartRestful.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Alive2212/LaravelSmartRestful.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/Alive2212/LaravelSmartRestful.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/Alive2212/LaravelSmartRestful
[link-travis]: https://travis-ci.org/Alive2212/LaravelSmartRestful
[link-scrutinizer]: https://scrutinizer-ci.com/g/Alive2212/LaravelSmartRestful/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/Alive2212/LaravelSmartRestful
[link-downloads]: https://packagist.org/packages/Alive2212/LaravelSmartRestful
[link-author]: https://github.com/https://github.com/Alive2212
[link-contributors]: ../../contributors

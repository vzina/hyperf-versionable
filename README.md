# Hyperf Versionable

[![Build Status](https://github.com/overtrue/laravel-versionable/workflows/CI/badge.svg)](https://github.com/overtrue/laravel-versionable/actions)
[![Latest Stable Version](https://poser.pugx.org/overtrue/laravel-versionable/v/stable.svg)](https://packagist.org/packages/overtrue/laravel-versionable)
[![Latest Unstable Version](https://poser.pugx.org/overtrue/laravel-versionable/v/unstable.svg)](https://packagist.org/packages/overtrue/laravel-versionable)
[![Total Downloads](https://poser.pugx.org/overtrue/laravel-versionable/downloads)](https://packagist.org/packages/overtrue/laravel-versionable)
[![License](https://poser.pugx.org/overtrue/laravel-versionable/license)](https://packagist.org/packages/overtrue/laravel-versionable)

It's a minimalist way to make your model support version history, and it's very simple to revert to the specified
version.

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me-button-s.svg?raw=true)](https://github.com/sponsors/overtrue)

## Requirement

1. PHP >= 8.1.0
2. hyperf/framework >= 3.1

## Features

- Keep the specified number of versions.
- Whitelist and blacklist for versionable attributes.
- Easily revert to the specified version.
- Record only changed attributes.
- Easy to customize.

## Installing

```shell
composer require vzina/hyperf-versionable -vvv
```

First, publish the config file and migrations:

```bash
php bin/hyperf.php vendor:publish vzina/hyperf-versionable
```

Then run this command to create a database migration:

```bash
php bin/hyperf.php migrate
```

## Usage

Add `Vzina\HyperfVersionable\Versionable` trait to the model and set versionable attributes:

```php
use Vzina\HyperfVersionable\Versionable;

class Post extends \Hyperf\DbConnection\Model\Model
{
    use Versionable;

    /**
     * Versionable attributes
     *
     * @var array
     */
    protected $versionable = ['title', 'content'];

    // Or use a blacklist
    //protected $dontVersionable = ['created_at', 'updated_at'];

    <...>
}
```

Versions will be created on the vensionable model saved.

```php
$post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
$post->update(['title' => 'version2']);
```

### Get versions

```php
$post->versions; // all versions
$post->latestVersion; // latest version
// or
$post->lastVersion;

$post->versions->first(); // first version
// or
$post->firstVersion;

$post->versionAt('2022-10-06 12:00:00'); // get version from a specific time
// or
$post->versionAt(\Carbon\Carbon::create(2022, 10, 6, 12));
```

### Revert

Revert a model instance to the specified version:

```php
$post->getVersion(3)->revert();

// or

$post->revertToVersion(3);
```

#### Revert without saving

```php
$version = $post->versions()->first();

$post = $version->revertWithoutSaving();
```

### Remove versions

```php
// soft delete
$post->removeVersion($versionId = 1);
$post->removeVersions($versionIds = [1, 2, 3]);
$post->removeAllVersions();

// force delete
$post->forceRemoveVersion($versionId = 1);
$post->forceRemoveVersions($versionIds = [1, 2, 3]);
$post->forceRemoveAllVersions();
```

### Restore deleted version by id

```php
$post->restoreTrashedVersion($id);
```

### Temporarily disable versioning

```php
// create
Post::withoutVersion(function () use (&$post) {
    Post::create(['title' => 'version1', 'content' => 'version1 content']);
});

// update
Post::withoutVersion(function () use ($post) {
    $post->update(['title' => 'updated']);
});
```

### Custom Version Store strategy

You can set the following different version policies through property `protected $versionStrategy`:

- `Vzina\HyperfVersionable\VersionStrategy::DIFF` - Version content will only contain changed attributes (default
  strategy).
- `Vzina\HyperfVersionable\VersionStrategy::SNAPSHOT` - Version content will contain all versionable attribute
  values.

### Show diff between the two versions

```php
$diff = $post->getVersion(1)->diff($post->getVersion(2));
```

`$diff` is a object `Vzina\HyperfVersionable\Diff`, it based
on [jfcherng/php-diff](https://github.com/jfcherng/php-diff).

You can render the diff to [many formats](https://github.com/jfcherng/php-diff#introduction), and all formats result
will be like follows:

```php
[
    $attribute1 => $diffOfAttribute1,
    $attribute2 => $diffOfAttribute2,
    ...
    $attributeN => $diffOfAttributeN,
]
```

#### toArray()

```php
$diff->toArray();
//
[
    "name" => [
        "old" => "John",
        "new" => "Doe",
    ],
    "age" => [
        "old" => 25,
        "new" => 26,
    ],
]
```

### Other formats

```php
toArray(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toText(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toJsonText(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toContextText(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toHtml(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toInlineHtml(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toJsonHtml(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
toSideBySideHtml(array $differOptions = [], array $renderOptions = [], bool $stripTags = false): array
```

> **Note**
>
> `$differOptions` and `$renderOptions` are optional, you can set them following the README
> of [jfcherng/php-diff](https://github.com/jfcherng/php-diff#example).
> `$stripTags` allows you to remove HTML tags from the Diff, helpful when you don't want to show tags.

### Using custom version model

You can define `$versionModel` in a model, that used this trait to change the model(table) for versions

> **Note**
>
> Model MUST extend class `\Vzina\HyperfVersionable\Version`;

```php
<?php

class PostVersion extends \Vzina\HyperfVersionable\Version
{
    //
}
```

Update the model attribute `$versionModel`:

```php
<?php
namespace App\Models;

use Hyperf\DbConnection\Model\Model;
use Vzina\HyperfVersionable\Versionable;

class Post extends Model
{
    use Versionable;

    public string $versionModel = PostVersion::class;
}
```

## Intergrations

- [mansoorkhan96/filament-versionable](https://github.com/mansoorkhan96/filament-versionable) Effortlessly manage revisions of your Eloquent models in Filament.

## :heart: Sponsor me

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me.svg?raw=true)](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)

## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and
PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT

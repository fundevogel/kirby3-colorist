# kirby3-colorist
[![Release](https://img.shields.io/github/release/Fundevogel/kirby3-colorist.svg)](https://github.com/Fundevogel/kirby3-colorist/releases) [![License](https://img.shields.io/github/license/Fundevogel/kirby3-colorist.svg)](https://github.com/Fundevogel/kirby3-colorist/blob/master/LICENSE) [![Issues](https://img.shields.io/github/issues/Fundevogel/kirby3-colorist.svg)](https://github.com/Fundevogel/kirby3-colorist/issues) [![Status](https://travis-ci.org/fundevogel/kirby3-colorist.svg?branch=master)](https://travis-ci.org/fundevogel/kirby3-colorist)

A Kirby v3 wrapper for `colorist`.


## What
This library acts as alternative **thumb driver** and is fully `Darkroom` compliant. Therefore, `kirby3-colorist` doesn't interfere with custom `thumb()` methods (shipped with other plugins), while also being fully **compatible to the popular ['Focus' plugin](https://github.com/flokosiol/kirby-focus)**.

It is a Kirby v3 wrapper for the Joe Drago's [`colorist`](https://github.com/joedrago/colorist). While it is capable of **generating and manipulating next-gen image formats** ([unlimited](https://jakearchibald.com/2020/avif-has-landed) - [AVIF](https://aomediacodec.github.io/av1-avif) - [power](https://caniuse.com/avif)!), some features aren't supported (like applying `blur` or `grayscale`).

From the `colorist` README:

> Colorist is an image file and ICC profile converter, generator, and identifier. Why make such a tool when the venerable ImageMagick already exists and seems to offer every possible image processing tool you can imagine? The answer is **absolute luminance**.

If that sounds interesting and you want to read on, be sure to check it out on the commandline or just visit [its homepage](https://joedrago.github.io/colorist) for more information.


## How
Install this package with [Composer](https://getcomposer.org):

```text
composer require fundevogel/kirby3-colorist
```

Now, enable the plugin:

```php
// config.php

return [
    // ..
    'thumbs.driver' => 'colorist',
];
```

**Note:** If you just want to generate something like `webp`, you don't need to, because `gd` and `im` can handle it, hassle-free. Using `toFormat('webp')` doesn't require `colorist` to be selected as `thumb.driver` (see below).

### Usage
This plugin exposes several methods & configuration options.

For example, if you want to convert an image to another format:

```php
// Converting a single image to single format:
$image = $page->image('example.jpg');
$webp = $image->toFormat('webp');
// Since this method return a `$file` object, chaining works as usual
$thumb = $webp->thumb('some-preset');

// Converting a single image to multiple formats:
$image = $page->image('example.jpg');
$results = $image->toFormats(['png', 'webp']);
```

For convenience, there are also methods for multiple images:

```php
// Converting multiple images to single format:
$images = $page->images();
$webps = $images->toFormat('webp');

// Converting multiple images to multiple formats:
$images = $page->images();
$results = $images->toFormats(['png', 'webp']);
```

You may also extract image profile information, like this:

```php
$image = $page->image('example.jpg');
$profile = $image->identify();
```

For further details, have a look at the following sections.

#### Configuration
You may also change certain options from your `config.php` globally, like this: `'fundevogel.colorist.optionName'` (or simply pass them to the `thumb()` method:

| Option       | Type        | Default(s to)               | Description                                                                            |
| ------------ | ----------- | --------------------------- | -------------------------------------------------------------------------------------- |
| `'bin'`      | string      | `__DIR__ . '/bin/colorist'` | Path to `colorist` executable                                                          |
| `'bpc'`      | integer     | `'auto'`                    | Set bits-per-channel (J2K/JP2 only); ranging from `8` to `16`                          |
| `'formats'`  | array       | `['webp']`                  | Default file formats to be used on image uploads                                       |
| `'deflum'`   | integer     | `80`                        | default/fallback luminance value in nits                                               |
| `'hlglum'`   | integer     | `null`                      | Like `'deflum'`, but uses an appropriate diffuse white based on peak HLG               |
| `'jobs'`     | integer     | `0`                         | Number of jobs to use when working (`0` = unlimited)                                   |
| `'speed'`    | integer     | `'auto'`                    | Quality/speed tradeoff when encoding (AVIF only); `0` = best quality, `10` = fastest   |
| `'template'` | string      | `'image'`                   | Set file blueprint for generated images                                                |
| `'tonemap'`  | string|bool | `'auto'`                    | Set tonemapping (`'on'` or `'off'`, but `true` & `false` are possible, too)            |
| `'yuv'`      | string      | `'auto'`                    | Choose yuv output format for supported formats (`'444'`, `'422'`, `'420'` or `'yv12'`) |

The `colorist` library has [much more](https://github.com/joedrago/colorist/blob/master/docs/Usage.md) to offer, and more options will be made available in time - if one of it's many features you really feel is missing, feel free to open a PR!

**Note:** When working with multiple formats, you may want to turn `thumbs.quality` into an array:

```php
// config.php

return [
    // ..
    'thumbs.quality' => [
        'avif' => 60,
        'webp' => 80,
    ],
];


// template.php
$image->toFormat('avif')->thumb(['width' => 300]);
```

#### Methods
For now, the following methods are available:

##### `identify (bool $asArray)`
Provides information about an image's color profile (primaries, luminance and such) as well as width, height & depth.

##### `toFormat (string $format = 'avif')`
Converts an image to `$format` and places it alongside the original version in the respective `content` folder. It returns a `$file` object, ready to be used via `thumb()` etc.

##### `toFormats (array $formats)`
Converts an image to multiple `$formats` and places them alongside the original version in the respective `content` folder. It returns a `$files` object.

##### `hasFormat (string $format)`
Checks if `$file` has image of given `$format`, returns `bool`.

##### `isFormat (string $format)`
Checks if `$file` is image of given `$format`, returns `bool`.

#### Hooks
On image upload, files are automatically converted to all formats in the `'fundevogel.colorist.formats'` option (`['webp']` by default).

#### Tag
The `(colorist: example.jpg)` tag supports converting / resizing right from the editor.

##### Options
WIP


## Roadmap
- [ ] Add tests
- [x] ~~Add hooks for file upload/update~~
- [x] ~~Add tag for editor use~~
- [x] ~~Add compatibility with 'Focus' plugin by @flokosiol~~
- [ ] Add methods for editing ICC color profile


## Credits
Credit where credit is due - as creator of `colorist`, [Joe Drago](https://github.com/joedrago) is the man of the hour. The included binary powers this project, and I'm thankful for his great work.

Also, I want to say thanks to [@flokosiol](https://github.com/flokosiol) and [@hashandsalt](https://github.com/HashandSalt), from whose work I learned (and borrowed) one or two things.

**Happy coding!**


:copyright: Fundevogel Kinder- und Jugendbuchhandlung

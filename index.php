<?php

# Initialize plugin
# (1) Load `colorist` class
load([
    'fundevogel\\colorist' => 'src/Colorist.php'
], __DIR__);


# (2) Add `colorist` as thumb driver if enabled
if (option('thumbs.driver') === 'colorist') {
    Kirby\Image\Darkroom::$types['colorist'] = 'Fundevogel\Colorist';
}

/**
 * Kirby v3 wrapper for `colorist`
 *
 * @package   kirby-colorist
 * @author    Martin Folkers <maschinenraum@fundevogel.de>
 * @link      https://fundevogel.de
 * @copyright Kinder- und Jugendbuchhandlung Fundevogel
 * @license   https://opensource.org/licenses/MIT
 *
 * The included `colorist` binary was released by Joe Drago
 * See https://github.com/joedrago/colorist
 *
 */
Kirby::plugin('fundevogel/colorist', [
    'options' => [
        'bin' => __DIR__ . '/bin/colorist',
        'sizes' => [1920, 1140, 640, 320],
        'speed' => 0,
        'template' => 'image',
        'tonemap' => 'off',
        'yuv' => '420',
    ],
    'snippets' => [
        'colorist' => __DIR__ . '/snippets/colorist.php',
    ],
    'fileMethods' => [
        # Provides basic information about the image,
        # like dimensions, bit depth, embedded ICC profile, ..
        'identify' => function (bool $asArray = true) {
            if (!$this) {
                return null;
            }

            # Check if image
            if ($this->type() !== 'image') {
                throw new Exception('Invalid file type: "' . $this->type() . '"');
            }

            return \Fundevogel\Colorist::identify($this->root(), $asArray);
        },
        'toFormat' => function (string $format = 'avif') {
            # Check if image
            if ($this->type() !== 'image') {
                throw new Exception('Invalid file type: "' . $this->type() . '"');
            }

            # Build file information
            $oldName = $this->filename();
            $newName = Kirby\Toolkit\F::name($oldName) . '.' . $format;
            $src = $this->root();
            $dst = Kirby\Toolkit\Str::replace($src, $oldName, $newName);

            $template = option('fundevogel.colorist.template');

            # Check if there's an array with a template for each format
            if (is_array($template)) {
                $template = $template[$format];
            }

            $file = File::factory([
                'source' => $dst,
                'parent' => $this->parent(),
                'filename' => $newName,
                'template' => $template,
            ]);

            if ($file->exists()) {
                return $file;
            }

            $colorist = new \Fundevogel\Colorist();
            $colorist->toFormat($src, $dst, $format);

            return $file->save();
        },
        'toFormats' => function (...$formats) {
            // if (empty($formats)) {
            //     throw new Exception('No formats specified.');
            // }

            $files = [];

            foreach ($formats as $format) {
                $files[] = $this->toFormat($format);
            }

            return new Files($files, $this->parent());
        },
        'hasFormat' => function (string $format) {
            # TODO: Benchmark if $this->parent()->image(F::filename($this->root()) . '.' . $format) is faster
            $path = F::dirname($this->root());
            $name = F::filename($this->root());

            return F::exists($path . '/' . $name . '.' . $format);
        },
        'isFormat' => function (string $format) {
            return F::extension($this->root()) === $format;
        },
    ],
    'filesMethods' => [
        'toFormat' => function (string $format = 'avif') {
            $files = [];

            foreach ($this as $file) {
                $files[] = $file->toFormat($format);
            }

            return new Files($files, $this->parent());
        },
        'toFormats' => function (...$formats) {
            $files = [];

            foreach ($formats as $format) {
                foreach ($this as $file) {
                    $files[] = $file->toFormat($format);
                }
            }

            return new Files($files, $this->parent());
        },
    ],
]);

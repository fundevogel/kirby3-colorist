<?php

namespace Fundevogel;

use Kirby\Image\Darkroom;


/**
 * Colorist
 *
 * Kirby v3 wrapper for `colorist` by Joe Drago
 *
 * See https://github.com/joedrago/colorist
 */
class Colorist extends Darkroom
{
    /**
     * Prerequisites
     */

    protected function defaults(): array
    {
        return parent::defaults() + [
            'format'  => null,
            'speed'   => option('fundevogel.colorist.speed'),
            'tonemap' => option('fundevogel.colorist.tonemap'),
            'yuv'     => option('fundevogel.colorist.yuv'),
        ];
    }

    # Check if `exec()` is available
    #
    # For more information, see
    # https://stackoverflow.com/questions/3938120/check-if-exec-is-disabled
    # https://stackoverflow.com/questions/2749591/php-exec-check-if-enabled-or-disabled
    public static function exec_enabled(): bool
    {
        # (1) Ensure `exec()` exists
        $function_exists = function_exists('exec');

        # (2) Ensure that `exec()` isn't disabled
        # TODO: Handle `ini_get()` being disabled, too
        $not_disabled = !in_array('exec', array_map('trim', explode(', ', ini_get('disable_functions'))));

        if ($not_disabled === false && @exec('echo EXEC') == 'EXEC') {
            $not_disabled = true;
        }

        # (3) Ensure that safe mode is off
        $no_safe_mode = !(strtolower(ini_get('safe_mode')) != 'off');

        $answers = array_filter([
            'function_exists' => $function_exists,
            'not_disabled' => $not_disabled,
            'no_safe_mode' => $no_safe_mode,
        ]);

        if (empty($answers)) {
            return false;
        }

        return true;
    }


    /**
     * Helpers
     *
     * Building command strings
     */

    protected function convert(string $file): string
    {
        return sprintf(option('fundevogel.colorist.bin') . ' convert %s', $file);
    }

    protected function format(array $options): string
    {
        $formats = [
            'avif',
            'bmp',
            'jpg',
            'jp2',
            'j2k',
            'png',
            'tiff',
            'webp',
        ];

        if (in_array($options['format'], $formats)) {
            return '--format ' . $options['format'];
        }

        return '';
    }

    protected function quality(array $options): string
    {
        $quality = $options['quality'];

        if (is_array($quality) && in_array($options['format'], $quality)) {
            $quality = $quality[$options['format']];
        }

        return '--quality ' . $quality;
    }

    protected function resize(array $options): string
    {
        if ($options['crop'] === false) {
            return sprintf('--resize %sx%s', $options['width'], $options['height']);
        }

        # TODO: Use @flokosiol's 'Focus Plugin' if available
        # See https://github.com/flokosiol/kirby-focus
        // if (class_exists('Flokosiol\Focus') && !empty($options['focus'])) {
        //     $focusCropValues = \Flokosiol\Focus::cropValues($options);

        //     $command  = sprintf('--resize %s,%s', $options['width'], $options['height']);
        //     $command .= sprintf(' --crop %s,%s,%s,%s', $focusCropValues['x1'], $focusCropValues['y1'], $focusCropValues['width'], $focusCropValues['height']);

        //     return $command;
        // }

        # TODO: Crop relative to gravity, like `center`, `bottom`, etc
        # See https://github.com/getkirby/kirby/blob/master/src/Image/Darkroom/ImageMagick.php#L199
        $command  = sprintf('--resize %s,%s', $options['width'], $options['height']);
        $command .= sprintf(' --crop 0,0,%s,%s', $options['width'], $options['height']);

        return $command;
    }

    protected function tonemap(array $options): string
    {
        $tonemap = [
            'on',
            'off',
        ];

        if (in_array($options['tonemap'], $tonemap)) {
            return '--tonemap ' . $options['tonemap'];
        }

        return '--tonemap auto';
    }

    protected function yuv(array $options): string
    {
        $yuv = [
            '444',
            '422',
            '420',
            'yv12',
        ];

        if (in_array($options['yuv'], $yuv)) {
            return '--yuv ' . $options['yuv'];
        }

        return '--yuv auto';
    }

    protected function speed(array $options): string
    {
        $min = 0;
        $max = 10;

        if (($min <= (int) $options['speed']) && ((int) $options['speed'] <= $max)) {
            return '--speed ' . $options['speed'];
        }

        return '--speed auto';
    }

    protected function save(string $file): string
    {
        return sprintf('%s', $file);
    }

    public function preprocess(string $file, array $options = [])
    {
        $options = $this->options($options);

        # TODO: As the underlying PHP function `getimagesize`
        # doesn't recognize next-gen image formats (like AVIF) yet,
        # this has to suffice ..

        return $options;
    }


    /**
     * Core
     */

    public static function identify(string $file, bool $asArray = true)
    {
        $command = sprintf(option('fundevogel.colorist.bin') . ' identify --json %s', $file);

        exec($command, $output, $status);

        if ($status !== 0) {
            throw new Exception('Command failed with non-zero exit: "' . $command . '"');
        }

        return json_decode($output[0], $asArray);
    }

    public function toFormat(string $src, string $dst, string $format)
    {
        $options = $this->preprocess($src, ['format' => $format]);
        $command = [];

        $command[] = $this->convert($src, $options);
        $command[] = $this->format($options);
        $command[] = $this->save($dst);

        # (1) Remove falsey entries
        # (2) Convert command to string
        $command = implode(' ', array_filter($command));

        var_dump($command);
        # Execute command
        exec($command, $output, $status);

        if ($status !== 0) {
            throw new Exception('Command failed with non-zero exit: "' . $command . '"');
        }
    }

    public function process(string $file, array $options = []): array
    {
        $options = $this->preprocess($file, $options);
        $command = [];

        $command[] = $this->convert($file);
        $command[] = $this->format($options);
        $command[] = $this->quality($options);
        $command[] = $this->speed($options);
        $command[] = $this->resize($options);
        $command[] = $this->tonemap($options);
        $command[] = $this->yuv($options);
        $command[] = $this->save($file);

        # (1) Remove falsey entries
        # (2) Convert command to string
        $command = implode(' ', array_filter($command));

        # Execute command
        exec($command, $output, $status);

        if ($status !== 0) {
            throw new Exception('Command failed with non-zero exit: "' . $command . '"');
        }

        return $options;
    }
}

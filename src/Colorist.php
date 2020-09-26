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

    protected function resize(string $file, array $options): string
    {
        if ($options['crop'] === false) {
            return sprintf('--resize %sx%s', $options['width'], $options['height']);
        }

        # TODO: Crop relative to gravity, like `center`, `bottom`, etc
        # See https://github.com/getkirby/kirby/blob/master/src/Image/Darkroom/ImageMagick.php#L199

        # Get width & height of original image
        #  _________
        # |         |
        # |         |y
        # |         |
        # |_________|
        #     x

        $infos = $this->identify($file, false);
        $x = $infos->width;
        $y = $infos->height;

        # Get width & height of desired image
        #  _________
        # |   __    |
        # |  |__|y2 |y
        # |   x1    |
        # |_________|
        #     x

        $x1 = $options['width'];
        $y1 = $options['height'];

        # Normalize them
        if ($x1 === 0 || $y1 === 0) {
            $x1 = ($x1) ? $x1 : $y1;
            $y1 = ($y1) ? $y1 : $x1;
        }

        # Build `resize` command
        $command  = sprintf('--resize %s,%s', $x1, $y1);

        # Get aspect ratio of original & desired image
        $ar = $x / $y;
        $ar1 = $x1 / $y1;

        # If they match, resize will suffice (square crop included)
        if ($ar === $ar1) {
            return $command;
        }

        # If they don't, calculate crop position
        # (1) Determine 'fit' mode
        $fit = $ar1 > 1
            ? 'width'
            : 'height'
        ;

        # (1a) Desired image's width is greater than its height = 'width'
        #  __________
        # |          |
        # |    x2    | y
        # |__________|
        # |__________| y2
        #      x

        if ($fit === 'width') {
            $x2 = $x;
            $y2 = floor($x2 / $ar1);

            $xpos = 0;
            $ypos = floor(($y - $y2) / 2);
        }


        # (1b) Desired image's height is greater than its width = 'height'
        #  _______
        # |   |    |
        # |   |    |
        # |   |y2  | y
        # |   |    |
        # | x2|    |
        # |___|____|
        #     x

        if ($fit === 'height') {
            $y2 = $y;
            $x2 = floor($y2 * $ar1);

            $xpos = floor(($x - $x2) / 2);
            $ypos = 0;
        }

        # Build `crop` command
        $command .= sprintf(' --crop %s,%s,%s,%s', $xpos, $ypos, $x2, $y2);

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
        var_dump($this->resize($file, $options));
        $options = $this->preprocess($file, $options);
        $command = [];

        $command[] = $this->convert($file);
        $command[] = $this->format($options);
        $command[] = $this->quality($options);
        $command[] = $this->speed($options);
        $command[] = $this->resize($file, $options);
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

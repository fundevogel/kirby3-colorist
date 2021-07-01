<?php

namespace Fundevogel;

use Exception;

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
        # For more information on all options `colorist` offers,
        # see https://github.com/joedrago/colorist/blob/master/docs/Usage.md
        return parent::defaults() + [
            # (1) Basic options
            'jobs'        => option('fundevogel.colorist.jobs', 0),
            'cmm'         => option('fundevogel.colorist.cmm', null),
            'deflum'      => option('fundevogel.colorist.deflum', null),
            'hlglum'      => option('fundevogel.colorist.hlglum', null),
            # (2) Input profile options
            'iccin'       => option('fundevogel.colorist.iccin', null),
            'frameindex'  => option('fundevogel.colorist.frameindex', 0),
            # (3) Output profile options
            'iccout'      => option('fundevogel.colorist.iccout', null),
            'autograde'   => option('fundevogel.colorist.autograde', false),
            'copyright'   => option('fundevogel.colorist.copyright', null),
            'description' => option('fundevogel.colorist.description', null),
            'gamma'       => option('fundevogel.colorist.gamma', null),
            'luminance'   => option('fundevogel.colorist.luminance', null),
            'primaries'   => option('fundevogel.colorist.primaries', null),
            'noprofile'   => option('fundevogel.colorist.noprofile', false),
            # (4) Output format options
            'bpc'         => option('fundevogel.colorist.bpc', null),
            'format'      => null,
            'quality'     => option('thumbs.quality', 90),
            'rate'        => option('fundevogel.colorist.rate', 0),
            'tonemap'     => option('fundevogel.colorist.tonemap', null),
            'yuv'         => option('fundevogel.colorist.yuv', null),
            'speed'       => option('fundevogel.colorist.speed', null),
            'nclx'        => option('fundevogel.colorist.nclx', null),
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
     * Building command strings
     */

    protected function convert(string $file): string
    {
        return sprintf('"' . option('fundevogel.colorist.bin') . '"' . ' convert "%s"', $file);
    }

    protected function save(string $file): string
    {
        return sprintf('"%s"', $file);
    }


    /**
     * Overwriting Darkroom options
     */

    # https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-q---quality
    protected function quality(string $file, array $options): string
    {
        $quality = $options['quality'];

        if (is_array($quality)) {
            $format = $options['format'] === null
                ? pathinfo($file, PATHINFO_EXTENSION)
                : $options['format']
            ;

            if (array_key_exists($format, $quality)) {
                $quality = $quality[$format];
            } else {
                return '';
            }
        }

        return '--quality ' . $quality;
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#--resize
    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-z---rect---crop
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
        if ($x1 === null || $y1 === null) {
            $x1 = ($x1) ? $x1 : $y1;
            $y1 = ($y1) ? $y1 : $x1;
        }

        # Build `resize` command
        $command  = sprintf('--resize %s,%s', $x1, $y1);

        # Get aspect ratio of original & desired image
        $ar = $x / $y;
        $ar1 = $x1 / $y1;

        # If aspect ratios match, resizing the image will suffice
        if ($ar === $ar1) {
            return $command;
        }

        # If aspect ratios don't match, calculate crop position
        # (1) Determine 'fit' mode
        $fit = $ar < $ar1
            ? 'width'
            : 'height'
        ;

        # (2) Use @flokosiol's 'Focus Plugin' if available
        # See https://github.com/flokosiol/kirby-focus
        $usingFocus = class_exists('Flokosiol\Focus') && isset($options['focus']) && $options['focus'] === true;

        if ($usingFocus === true) {
            # TODO: Figure out how this could work outside of `focusCrop`
            # (1) Doesn't work
            // $page = new Kirby\Cms\Page([
            //     'dirname' => Kirby\Toolkit\F::dirname($file),
            //     'slug' => Kirby\Toolkit\Str::slug(Kirby\Toolkit\F::name(Kirby\Toolkit\F::dirname($file))),
            // ]);
            // $file = $page->file(Kirby\Toolkit\F::filename($file));

            # (2) Doesn't work either
            // $file = new Kirby\Cms\File([
            //     'root' => Kirby\Toolkit\F::name($file),
            //     'source' => $file,
            //     'filename' => Kirby\Toolkit\F::filename($file),
            // ]);

            # (3) Nope
            // $path = Kirby\Toolkit\Str::replace($file, kirby()->root('content') . '/', '');
            // $path = Kirby\Toolkit\Str::replace($path, '/' . Kirby\Toolkit\F::filename($file), '');
            // $file = page($path)->file(Kirby\Toolkit\F::filename($file));

            # (4) Not a chance
            // $file = new Image($file);

            // $focusX = \Flokosiol\Focus::coordinates($file, 'x');
            // $focusY = \Flokosiol\Focus::coordinates($file, 'y');
            $focusX = $options['focusX'];
            $focusY = $options['focusY'];

            $options = [
                'originalWidth' => $x,
                'originalHeight' => $y,
                'ratio' => \Flokosiol\Focus::numberFormat($ar1),
                'fit' => $fit,
                'crop' => $focusX * 100 . '-' . $focusY * 100,
                'focusX' => \Flokosiol\Focus::numberFormat($focusX),
                'focusY' => \Flokosiol\Focus::numberFormat($focusY),
            ];

            $focus = \Flokosiol\Focus::cropValues($options);

            $command .= sprintf(' --crop %s,%s,%s,%s', $focus['x1'], $focus['y1'], $focus['width'], $focus['height']);

            return $command;
        }

        # (3a) Desired image's width is greater than its height = 'width'
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

        # (3b) Desired image's height is greater than its width = 'height'
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


    /**
     * Implementing Colorist options
     *
     * (1) Basic options
     * (2) Input profile options
     * (3) Output profile options
     * (4) Output format options
     */

    # (1) Basic options

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-j---jobs
    protected function jobs(array $options): string
    {
        if ($options['jobs'] > 0) {
            return '--jobs ' . $options['jobs'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#--cmm---cms
    protected function cmm(array $options): string
    {
        $modules = [
            'colorist',
            'lcms',
        ];

        if (in_array($options['cmm'], $modules)) {
            return '--cmm ' . $options['cmm'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#--deflum---hlglum
    protected function deflum(array $options): string
    {
        if ($options['deflum'] !== null) {
            return '--deflum ' . $options['deflum'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#--deflum---hlglum
    protected function hlglum(array $options): string
    {
        if ($options['hlglum'] !== null) {
            return '--hlglum ' . $options['hlglum'];
        }

        return '';
    }

    # (2) Input profile options
    protected function iccin(array $options): string
    {
        if ($options['iccin'] !== null) {
            return '--iccin ' . $options['iccin'];
        }

        return '';
    }

    protected function frameindex(array $options): string
    {
        if ($options['frameindex'] > 0) {
            return '--frameindex ' . $options['frameindex'];
        }

        return '';
    }

    # (3) Output profile options

    protected function iccout(array $options): string
    {
        if ($options['iccout'] !== null) {
            return '--iccout ' . $options['iccout'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-a---autograde
    protected function autograde(array $options): string
    {
        if ($options['autograde'] === true) {
            return '--autograde';
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-c---copyright
    protected function copyright(array $options): string
    {
        if ($options['copyright'] !== null) {
            return '--copyright ' . $options['copyright'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-d---description
    protected function description(array $options): string
    {
        if ($options['description'] !== null) {
            return '--description ' . $options['description'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-g---gamma
    protected function gamma(array $options): string
    {
        $gamma = [
            'pq',
            'hlg',
            'source',
        ];

        if (in_array($options['gamma'], $gamma)) {
            return '--gamma ' . $options['gamma'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-l---luminance
    protected function luminance(array $options): string
    {
        if ($options['luminance'] !== null) {
            return '--luminance ' . $options['luminance'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-p---primaries
    protected function primaries(array $options): string
    {
        $primaries = [
            'bt709',
            'bt2020',
            'p3',
        ];

        if (in_array($options['primaries'], $primaries)) {
            return '--primaries ' . $options['primaries'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-n---noprofile
    protected function noprofile(array $options): string
    {
        if ($options['noprofile'] === true) {
            return '--noprofile';
        }

        return '';
    }

    # (4) Output format options

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-b---bpc
    protected function bpc(array $options): string
    {
        if ($options['bpc'] === null) {
            return '';
        }

        $min = 8;
        $max = 16;

        if (($min <= (int) $options['bpc']) && ((int) $options['bpc'] <= $max)) {
            return '--bpc ' . $options['bpc'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-f---format
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

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-r---rate
    protected function rate(array $options): string
    {
        if ($options['rate'] > 0) {
            return '--rate ' . $options['rate'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#-t---tonemap
    protected function tonemap(array $options): string
    {
        # Normalize booleans
        if ($options['tonemap'] === true) {
            $options['tonemap'] = 'on';
        }

        if ($options['tonemap'] === false) {
            $options['tonemap'] = 'off';
        }

        $tonemap = [
            'on',
            'off',
        ];

        if (in_array($options['tonemap'], $tonemap)) {
            return '--tonemap ' . $options['tonemap'];
        }

        return '';
    }

    # See https://github.com/joedrago/colorist/blob/master/docs/Usage.md#--yuv
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

        return '';
    }

    protected function speed(array $options): string
    {
        if ($options['speed'] === null) {
            return '';
        }

        $min = 0;
        $max = 10;

        if (($min <= (int) $options['speed']) && ((int) $options['speed'] <= $max)) {
            return '--speed ' . $options['speed'];
        }

        return '';
    }

    protected function nclx(array $options): string
    {
        if ($options['nclx'] !== null) {
            return '--nclx ' . $options['nclx'];
        }

        return '';
    }


    /**
     * Core
     */

    public static function identify(string $file, bool $asArray = true)
    {
        # Build `identify` command
        $command = sprintf(option('fundevogel.colorist.bin') . ' identify --json %s', $file);

        exec($command, $output, $status);

        if ($status !== 0) {
            throw new Exception('Command failed with non-zero exit: "' . $command . '"');
        }

        return json_decode($output[0], $asArray);
    }

    public function preprocess(string $file, array $options = [])
    {
        $options = $this->options($options);

        # TODO: As the underlying PHP function `getimagesize`
        # doesn't recognize next-gen image formats (like AVIF) yet,
        # we need to overwrite this function, at least for now

        return $options;
    }

    public function toFormat(string $src, string $dst, string $format)
    {
        $options = $this->preprocess($src, ['format' => $format]);
        $command = [];

        # Build `convert` command
        $command[] = $this->convert($src, $options);
        $command[] = $this->format($options);
        $command[] = $this->save($dst);

        # Let's get ready to rumble
        # (1) Remove falsey entries
        # (2) Convert command to string
        $command = implode(' ', array_filter($command));

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

        # Build `convert` command
        $command[] = $this->convert($file);
        # (1) Darkroom options
        $command[] = $this->quality($file, $options);
        $command[] = $this->resize($file, $options);
        # (2) Basic options
        $command[] = $this->jobs($options);
        $command[] = $this->cmm($options);
        $command[] = $this->deflum($options);
        $command[] = $this->hlglum($options);
        # (3) Input profile options
        $command[] = $this->iccin($options);
        $command[] = $this->frameindex($options);
        # (4) Output profile options
        $command[] = $this->iccout($options);
        $command[] = $this->autograde($options);
        $command[] = $this->copyright($options);
        $command[] = $this->description($options);
        $command[] = $this->gamma($options);
        $command[] = $this->luminance($options);
        $command[] = $this->primaries($options);
        $command[] = $this->noprofile($options);
        # (5) Output format options
        $command[] = $this->bpc($options);
        $command[] = $this->format($options);
        $command[] = $this->rate($options);
        $command[] = $this->tonemap($options);
        $command[] = $this->yuv($options);
        $command[] = $this->speed($options);
        $command[] = $this->nclx($options);
        # (6) Save image
        $command[] = $this->save($file);

        # Let's get ready to rumble
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

<?php
    $formats = $src->toFormats(['avif', 'webp']);
    $sizes = $sizes ?? option('fundevogel.colorist.sizes');
?>

<picture>
    <?php
        foreach ($formats as $format) :
        foreach ($sizes as $max) :
    ?>
    <source
        media="(min-width: <?=$max?>px)"
        <?php
            # Note: Once Kirby supports next-gen MIME types, like `image/avif`,
            # we may also use `F::extensionToMime($format->extension())`
        ?>
        type="image/<?= $format->extension() ?>"
        srcset="<?= $format->resize($max)->url() ?>"
    >
    <?php
        endforeach;
        endforeach;

        foreach ($sizes as $max) :
    ?>
    <source
        media="(min-width: <?= $max?>px)"
        <?php
            # Note: Once Kirby supports next-gen MIME types, like `image/avif`,
            # we may also use `F::extensionToMime($src->extension())`
        ?>
        type="image/<?= $src->extension() ?>"
        srcset="<?= $src->resize($max)->url() ?>"
    >
    <?php endforeach?>

    <img
        src="<?= $src->resize($width, $height)->url() ?>"
        title="<?= $src->title() ?>" alt="<?= $src->alt() ?>"
        width="<?= $width ?>" height="<?= $height ?>"
    >
</picture>

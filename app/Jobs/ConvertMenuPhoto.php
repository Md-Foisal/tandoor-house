<?php

namespace App\Jobs;

use App\Http\Controllers\MenuController;
use App\Models\MenuItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded dish photo into 4:5 crops at 480, 960 and 1600px (AVIF and
 * WebP) and a blurred placeholder, stored as menu/{item}/{hash}-{width}.{ext}.
 *
 * Engines, in order: Imagick when it can write AVIF, the project's sharp via
 * scripts/convert-photo.mjs, then GD (WebP only, so the AVIF list stays empty
 * and the browser takes the WebP source).
 */
class ConvertMenuPhoto implements ShouldQueue
{
    use Queueable;

    public const WIDTHS = [480, 960, 1600];

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $menuItemId, public readonly string $source) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($this->source)) {
            return;
        }

        $work = storage_path('app/tmp/photo-'.Str::random(12));
        File::ensureDirectoryExists($work);

        try {
            $original = $work.'/original';
            File::put($original, $disk->get($this->source));
            $hash = substr(sha1_file($original) ?: Str::random(40), 0, 12);

            $meta = $this->convert($original, $work);

            $stored = [];
            $sources = ['avif' => [], 'webp' => []];

            foreach ($sources as $format => $_) {
                foreach (self::WIDTHS as $width) {
                    $file = "$work/$width.$format";

                    if (! is_file($file)) {
                        continue;
                    }

                    $path = "menu/{$this->menuItemId}/$hash-$width.$format";
                    $disk->put($path, (string) file_get_contents($file));
                    $stored[] = $path;
                    $sources[$format][] = [$width, $path];
                }
            }
        } finally {
            File::deleteDirectory($work);
        }

        $stale = DB::transaction(function () use ($meta, $sources, $stored) {
            $item = MenuItem::query()->lockForUpdate()->find($this->menuItemId);

            // Replaced or deleted while converting: this result is already out of date.
            if ($item === null || $item->image !== $this->source) {
                return $stored;
            }

            $old = $item->photoPaths();
            $item->photo = [
                'width' => $meta['width'],
                'height' => $meta['height'],
                'placeholder' => $meta['placeholder'],
                'sources' => $sources,
            ];
            $item->save();

            return array_values(array_diff($old, $stored));
        });

        Cache::forget(MenuController::CACHE_KEY);
        $disk->delete($stale);
    }

    /**
     * @return array{width: int, height: int, placeholder: string}
     */
    private function convert(string $original, string $work): array
    {
        if ($this->imagickCanWriteAvif()) {
            return $this->withImagick($original, $work);
        }

        $node = $this->nodeBinary();

        if ($node !== null) {
            try {
                return $this->withSharp($node, $original, $work);
            } catch (ProcessFailedException) {
                // Fall through to GD rather than leave the dish without derived images.
            }
        }

        return $this->withGd($original, $work);
    }

    private function imagickCanWriteAvif(): bool
    {
        return class_exists(\Imagick::class) && in_array('AVIF', \Imagick::queryFormats('AVIF'), true);
    }

    private function nodeBinary(): ?string
    {
        $result = Process::run(['which', 'node']);

        return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : null;
    }

    /**
     * @return array{width: int, height: int, placeholder: string}
     */
    private function withSharp(string $node, string $original, string $work): array
    {
        $result = Process::path(base_path())->timeout(120)->run([$node, 'scripts/convert-photo.mjs', $original, $work])->throw();

        /** @var array{width: int, height: int, placeholder: string} $meta */
        $meta = json_decode(trim($result->output()), true, flags: JSON_THROW_ON_ERROR);

        return $meta;
    }

    /**
     * @return array{width: int, height: int, placeholder: string}
     */
    private function withImagick(string $original, string $work): array
    {
        $source = new \Imagick($original);
        $source->autoOrient();
        $source->stripImage();

        foreach (self::WIDTHS as $width) {
            $height = (int) round($width * 1.25);

            foreach (['avif' => 50, 'webp' => 74] as $format => $quality) {
                $crop = clone $source;
                $crop->cropThumbnailImage($width, $height);
                $crop->setImageFormat($format);
                $crop->setImageCompressionQuality($quality);
                $crop->writeImage("$work/$width.$format");
                $crop->clear();
            }
        }

        $tiny = clone $source;
        $tiny->cropThumbnailImage(16, 20);
        $tiny->blurImage(0, 1);
        $tiny->setImageFormat('webp');
        $tiny->setImageCompressionQuality(40);
        $placeholder = 'data:image/webp;base64,'.base64_encode($tiny->getImageBlob());
        $tiny->clear();
        $source->clear();

        return ['width' => 1600, 'height' => 2000, 'placeholder' => $placeholder];
    }

    /**
     * @return array{width: int, height: int, placeholder: string}
     */
    private function withGd(string $original, string $work): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($original));

        if ($source === false) {
            throw new RuntimeException('The uploaded photo could not be read.');
        }

        $source = $this->oriented($source, $original);

        foreach (self::WIDTHS as $width) {
            $crop = $this->crop($source, $width, max(1, (int) round($width * 1.25)));
            imagewebp($crop, "$work/$width.webp", 74);
        }

        $tiny = $this->crop($source, 16, 20);
        imagefilter($tiny, IMG_FILTER_GAUSSIAN_BLUR);
        ob_start();
        imagewebp($tiny, null, 40);
        $placeholder = 'data:image/webp;base64,'.base64_encode((string) ob_get_clean());

        return ['width' => 1600, 'height' => 2000, 'placeholder' => $placeholder];
    }

    private function oriented(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function crop(\GdImage $source, int $width, int $height): \GdImage
    {
        $sw = imagesx($source);
        $sh = imagesy($source);
        $scale = max($width / $sw, $height / $sh);
        $cw = (int) round($width / $scale);
        $ch = (int) round($height / $scale);

        $target = imagecreatetruecolor($width, $height);
        imagecopyresampled($target, $source, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $width, $height, $cw, $ch);

        return $target;
    }
}

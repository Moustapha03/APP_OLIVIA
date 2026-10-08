<?php

namespace App\Services\Photos;

use App\Models\Subscriber;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Subscriber photo (shown to the controller to check the holder).
 *
 * - stored on the PRIVATE "local" disk, under a random name;
 * - re-encoded as JPEG with GD: drops every metadata (EXIF, GPS position
 *   of the phone…) and any content hidden in the file;
 * - rotated upright (phone orientation) and reduced to 800 px max.
 * Served only by an authorised route (SubscriberPhotoController + SubscriberPolicy).
 */
class SubscriberPhoto
{
    public const DISK = 'local';

    public const DIRECTORY = 'photos';

    private const MAX_SIZE = 800;

    public function store(Subscriber $subscriber, UploadedFile $file): string
    {
        $image = $this->load($file);
        $image = $this->orient($image, $file);
        $image = $this->resize($image);

        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $path = self::DIRECTORY.'/'.Str::uuid().'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);

        $previous = $subscriber->photo_path;
        $subscriber->forceFill(['photo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }

        return $path;
    }

    public function delete(Subscriber $subscriber): void
    {
        if ($subscriber->photo_path !== null) {
            Storage::disk(self::DISK)->delete($subscriber->photo_path);
            $subscriber->forceFill(['photo_path' => null])->save();
        }
    }

    private function load(UploadedFile $file): GdImage
    {
        $data = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($data);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unreadable image.');
        }

        return $image;
    }

    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function resize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, self::MAX_SIZE / max($width, $height));

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        // Always redraw, even without resizing: guarantees a clean bitmap.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $canvas;
    }
}

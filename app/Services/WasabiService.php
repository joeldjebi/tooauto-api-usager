<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WasabiService
{
    protected function disk()
    {
        config([
            'filesystems.disks.wasabi' => [
                'driver' => 's3',
                'key' => config('wasabi.access_key'),
                'secret' => config('wasabi.secret_key'),
                'region' => config('wasabi.region'),
                'bucket' => config('wasabi.bucket'),
                'endpoint' => config('wasabi.endpoint'),
                'url' => config('wasabi.url'),
                'use_path_style_endpoint' => true,
            ],
        ]);

        return Storage::disk('wasabi');
    }

    public function uploadFile(UploadedFile $file, $directory, $prefix = 'file')
    {
        $directory = trim((string) $directory, '/');
        $filename = $prefix . '-' . time() . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        $this->disk()->putFileAs(
            $directory,
            $file,
            $filename,
            [
                'ContentType' => $file->getMimeType(),
            ]
        );

        return $path;
    }

    public function uploadAvatar(UploadedFile $file)
    {
        return $this->uploadFile(
            $file,
            config('wasabi.avatar_directory', 'images/avatar'),
            'user'
        );
    }

    public function temporaryUrl($fileUrl, $expirationMinutes = 10080)
    {
        $path = $this->extractPath($fileUrl);

        if (!$path) {
            return null;
        }

        // URL signée mise en cache jusqu'à 1 jour avant son expiration :
        // évite de re-signer à chaque requête et garde une URL stable (cache d'images côté mobile)
        $cacheMinutes = (int) floor($expirationMinutes * 6 / 7);

        if ($cacheMinutes < 1) {
            return $this->disk()->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        }

        return Cache::remember(
            'wasabi_url:' . md5($path . '|' . $expirationMinutes),
            now()->addMinutes($cacheMinutes),
            fn () => $this->disk()->temporaryUrl($path, now()->addMinutes($expirationMinutes))
        );
    }

    public function deleteFile($fileUrl)
    {
        $path = $this->extractPath($fileUrl);

        if ($path && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    public function extractPath($fileUrl)
    {
        if (empty($fileUrl)) {
            return null;
        }

        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            $path = ltrim(parse_url($fileUrl, PHP_URL_PATH) ?? '', '/');
            $bucket = trim((string) config('wasabi.bucket'), '/');

            if ($bucket !== '' && Str::startsWith($path, $bucket . '/')) {
                return Str::after($path, $bucket . '/');
            }

            return $path ?: null;
        }

        return ltrim($fileUrl, '/');
    }
}

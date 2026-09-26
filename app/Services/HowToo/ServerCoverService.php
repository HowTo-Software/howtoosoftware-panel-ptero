<?php

namespace Pterodactyl\Services\HowToo;

use Throwable;
use RuntimeException;
use Pterodactyl\Models\Server;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ServerCoverService
{
    public function store(Server $server, UploadedFile $image): void
    {
        $this->storeImage($server, $image, 'cover_image', 'server-covers');
    }

    public function storeIcon(Server $server, UploadedFile $image): void
    {
        $this->storeImage($server, $image, 'icon_image', 'server-icons');
    }

    private function storeImage(Server $server, UploadedFile $image, string $field, string $directory): void
    {
        $directory .= '/' . $server->uuid;
        $filename = Str::uuid() . '.' . $image->extension();
        $path = $image->storeAs($directory, $filename, 'local');

        if (!is_string($path)) {
            throw new RuntimeException('The server cover image could not be stored.');
        }

        $previous = $server->{$field};
        try {
            $server->forceFill([$field => $path])->saveOrFail();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }

    public function delete(Server $server): void
    {
        $this->deleteImage($server, 'cover_image');
    }

    public function deleteIcon(Server $server): void
    {
        $this->deleteImage($server, 'icon_image');
    }

    private function deleteImage(Server $server, string $field): void
    {
        $previous = $server->{$field};
        if (!$previous) {
            return;
        }

        $server->forceFill([$field => null])->saveOrFail();
        Storage::disk('local')->delete($previous);
    }
}

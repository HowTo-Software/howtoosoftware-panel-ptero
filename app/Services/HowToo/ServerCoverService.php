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
        $directory = 'server-covers/' . $server->uuid;
        $filename = Str::uuid() . '.' . $image->extension();
        $path = $image->storeAs($directory, $filename, 'local');

        if (!is_string($path)) {
            throw new RuntimeException('The server cover image could not be stored.');
        }

        $previous = $server->cover_image;
        try {
            $server->forceFill(['cover_image' => $path])->saveOrFail();
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
        $previous = $server->cover_image;
        if (!$previous) {
            return;
        }

        $server->forceFill(['cover_image' => null])->saveOrFail();
        Storage::disk('local')->delete($previous);
    }
}

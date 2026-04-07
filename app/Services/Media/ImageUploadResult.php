<?php

namespace App\Services\Media;

final class ImageUploadResult
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly bool $isPrivate,
        public readonly ?string $url = null,
    ) {}

    /**
     * @return array{disk:string,path:string,is_private:bool,url:?string}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'is_private' => $this->isPrivate,
            'url' => $this->url,
        ];
    }
}

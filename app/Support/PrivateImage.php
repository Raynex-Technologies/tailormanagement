<?php

namespace App\Support;

final class PrivateImage
{
    public function __construct(
        public readonly string $path,
        public readonly string $scope = 'staff',
    ) {}
}

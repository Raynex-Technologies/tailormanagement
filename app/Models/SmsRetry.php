<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;

class SmsRetry extends Model
{
    use BranchScoped;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'claimed_at' => 'datetime'];
    }
}

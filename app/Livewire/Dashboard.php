<?php

namespace App\Livewire;

use App\Services\Dashboard\DashboardStats;
use App\Support\BranchContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $stats = app(DashboardStats::class)->for($user);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'user' => $user,
            'currentBranch' => BranchContext::branch(),
        ]);
    }
}

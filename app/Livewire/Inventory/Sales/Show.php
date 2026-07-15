<?php

namespace App\Livewire\Inventory\Sales;

use App\Models\BusinessSetting;
use App\Models\PosSale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Sale Details')]
class Show extends Component
{
    use AuthorizesRequests;

    public PosSale $sale;

    public function mount(PosSale $sale): void
    {
        $this->authorize('view', $sale);

        $this->sale = $sale->load(['items.inventoryItem', 'customer', 'user', 'branch']);
    }

    public function render()
    {
        return view('livewire.inventory.sales.show', [
            'settings' => BusinessSetting::instance(),
        ]);
    }
}

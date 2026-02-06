<?php

namespace App\Livewire\DeliveryNotes;

use App\Models\DeliveryNote;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    public DeliveryNote $deliveryNote;

    public function mount(DeliveryNote $deliveryNote): void
    {
        $this->authorize('view', $deliveryNote);
        $this->deliveryNote = $deliveryNote->load([
            'order.customer',
            'order.lines',
            'deliveredBy',
            'branch',
        ]);
    }

    public function getTitle(): string
    {
        return "Delivery Note {$this->deliveryNote->delivery_note_no}";
    }

    public function render()
    {
        return view('livewire.delivery-notes.show')
            ->title($this->getTitle());
    }
}

<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\MeasurementProfile;
use App\Support\Customers\CustomerAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class MeasurementRevisionShow extends Component
{
    public Customer $customer;

    public MeasurementProfile $measurementProfile;

    public function mount(Customer $customer, MeasurementProfile $measurementProfile): void
    {
        CustomerAccess::authorizeView($customer);

        if ($measurementProfile->customer_id !== $customer->id || $measurementProfile->profile_name !== 'Default') {
            abort(404);
        }

        $this->customer = $customer->load('branch');
        $this->measurementProfile = $measurementProfile->load(['values.field', 'recordedBy']);
    }

    public function render()
    {
        return view('livewire.customers.measurement-revision-show')
            ->title(__('Measurement Revision :revision', ['revision' => $this->measurementProfile->revision]));
    }
}

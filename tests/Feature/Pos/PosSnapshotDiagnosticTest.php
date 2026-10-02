<?php

namespace Tests\Feature\Pos;

class PosSnapshotDiagnosticTest extends \Tests\TestCase
{
    public function test_cashier_without_customer_create_permission_is_forbidden(): void
    {
        $this->actingAsRole('sales', $this->branch);
        \Livewire\Livewire::test(\App\Livewire\Pos\PosTerminal::class)->call('openCustomerModal')->assertForbidden();
    }
}

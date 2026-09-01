<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        $order = $invoice->order;
        if (! $order) {
            return false;
        }

        if ((int) $invoice->branch_id !== (int) $order->branch_id) {
            return false;
        }

        return app(OrderPolicy::class)->view($user, $order);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        $order = $invoice->order;
        if (! $order) {
            return false;
        }

        return app(OrderPolicy::class)->update($user, $order);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send_email') && $this->view($user, $invoice);
    }
}

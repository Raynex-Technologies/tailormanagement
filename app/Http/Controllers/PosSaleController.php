<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\PosSale;
use App\Services\Pos\PosSaleService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosSaleController extends Controller
{
    public function store(Request $request, PosSaleService $service): RedirectResponse
    {
        $request->user()->can('pos.sell') || abort(403);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $sale = $service->complete(
            cart: $validated['items'],
            cashier: $request->user(),
            customerId: $validated['customer_id'] ?? null,
            discountAmount: (float) ($validated['discount_amount'] ?? 0),
            taxAmount: (float) ($validated['tax_amount'] ?? 0),
            amountPaid: (float) $validated['amount_paid'],
            paymentMethod: $validated['payment_method'],
            paymentReference: $validated['payment_reference'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('pos.sales.show', $sale)
            ->with('success', "Sale {$sale->sale_number} completed.");
    }

    public function show(PosSale $sale): View
    {
        request()->user()->can('pos.view') || abort(403);

        return view('pos.receipt', [
            'sale' => $sale->load(['items.inventoryItem', 'customer', 'user', 'branch']),
            'settings' => BusinessSetting::instance(),
            'receiptUrl' => $sale->public_receipt_url,
            'receiptQrCodeSvg' => $this->receiptQrCodeSvg($sale->public_receipt_url),
        ]);
    }

    public function publicShow(string $token): View
    {
        $sale = PosSale::query()
            ->where('receipt_token', $token)
            ->with(['items.inventoryItem', 'customer', 'user', 'branch'])
            ->firstOrFail();

        return view('pos.receipt', [
            'sale' => $sale,
            'settings' => BusinessSetting::instance(),
            'receiptUrl' => $sale->public_receipt_url,
            'receiptQrCodeSvg' => $this->receiptQrCodeSvg($sale->public_receipt_url),
            'publicReceipt' => true,
        ]);
    }

    protected function receiptQrCodeSvg(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(150),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($url);
    }
}

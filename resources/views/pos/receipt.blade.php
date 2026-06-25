<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>{{ __('POS Receipt') }}</title>
    </head>
    <body class="min-h-screen bg-zinc-100 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <main class="mx-auto max-w-3xl space-y-6 px-4 py-6">
            <div class="pos-receipt-actions flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ __('Receipt') }} {{ $sale->sale_number }}</h1>
                    <p class="text-sm text-zinc-500">{{ $sale->sold_at?->format('M d, Y H:i') }}</p>
                </div>
                <div class="flex gap-2">
                    @unless ($publicReceipt ?? false)
                        <a href="{{ route('pos.index') }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            {{ __('Back to POS') }}
                        </a>
                    @endunless
                    <button type="button" onclick="window.print()" class="inline-flex items-center rounded-lg bg-zinc-950 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-zinc-800 dark:bg-lime-400 dark:text-zinc-950 dark:hover:bg-lime-300">
                        {{ __('Print') }}
                    </button>
                </div>
            </div>

            <div class="pos-receipt-page-shell">
                @include('pos.partials.receipt-slip', [
                    'sale' => $sale,
                    'settings' => $settings,
                    'receiptUrl' => $receiptUrl,
                    'receiptQrCodeSvg' => $receiptQrCodeSvg,
                ])
            </div>
        </main>

        <style>
            .pos-receipt-page-shell {
                display: flex;
                justify-content: center;
                padding: 2rem 1rem;
                border-radius: 0.75rem;
                background: #ece7df;
            }

            .pos-receipt-slip {
                width: min(100%, 23rem);
                color: #18181b;
                filter: drop-shadow(0 12px 18px rgb(0 0 0 / 0.18));
            }

            .pos-receipt-edge {
                height: 14px;
                background:
                    linear-gradient(135deg, transparent 8px, #fff 0) top left,
                    linear-gradient(225deg, transparent 8px, #fff 0) top right;
                background-size: 16px 14px;
                background-repeat: repeat-x;
            }

            .pos-receipt-edge-bottom {
                transform: rotate(180deg);
            }

            .pos-receipt-body {
                background: #fff;
                padding: 2rem 2.25rem;
            }

            .pos-receipt-slip svg {
                width: 100%;
                height: 100%;
            }

            @media print {
                .pos-receipt-actions {
                    display: none !important;
                }

                body,
                main,
                .pos-receipt-page-shell {
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                }

                .pos-receipt-slip {
                    width: 80mm;
                    filter: none;
                }
            }
        </style>
    </body>
</html>

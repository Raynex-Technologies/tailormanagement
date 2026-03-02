<?php

use App\Livewire\Administration\BusinessSettings as AdministrationBusinessSettings;
use App\Livewire\Branches\Index as BranchesIndex;
use App\Livewire\DeliveryNotes\Show as DeliveryNoteShow;
use App\Livewire\Inventory\Categories\Index as CategoriesIndex;
use App\Livewire\Inventory\Items\Index as ItemsIndex;
use App\Livewire\Inventory\Stock\Index as StockIndex;
use App\Livewire\Inventory\Transactions\Index as TransactionsIndex;
use App\Livewire\Inventory\Units\Index as UnitsIndex;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Invoices\Show as InvoicesShow;
use App\Livewire\Orders\Board as OrdersBoard;
use App\Livewire\Orders\Form as OrdersForm;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Orders\StockRequests\Index as OrderStockRequestsIndex;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Models\BusinessSetting;
use App\Livewire\Store\StockRequests\Index as StoreStockRequestsIndex;
use App\Livewire\Store\StockRequests\Show as StoreStockRequestShow;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Support\InvoicePdfRenderer;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Health Check (public endpoint for monitoring)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
        'app' => config('app.name'),
        'version' => '1.0.0',
    ]);
})->name('health');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (with Branch Context)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'branch.context'])->group(function () {
    // Dashboard - accessible to all authenticated users
    Route::get('dashboard', \App\Livewire\Dashboard::class)
        ->name('dashboard');

    // Branch Management
    Route::get('administration/branches', BranchesIndex::class)
        ->middleware('can:branches.view')
        ->name('branches.index');

    // My Tasks - personal todo management
    Route::get('tasks', \App\Livewire\Tasks\Index::class)->name('tasks.index');

    // Users Management
    Route::prefix('users')->middleware('can:users.view')->group(function () {
        Route::get('/', \App\Livewire\Users\Index::class)->name('users.index');
        Route::get('/create', \App\Livewire\Users\Form::class)
            ->middleware('can:users.manage')
            ->name('users.create');
        Route::get('/{user}', \App\Livewire\Users\Show::class)->name('users.show');
        Route::get('/{user}/edit', \App\Livewire\Users\Form::class)
            ->middleware('can:users.manage')
            ->name('users.edit');
    });

    // Customers Management
    Route::prefix('customers')->middleware('can:users.view')->group(function () {
        Route::get('/', \App\Livewire\Customers\Index::class)->name('customers.index');
        Route::get('/{customer}', \App\Livewire\Customers\Show::class)->name('customers.show');
    });

    // Access Control (Roles & Permissions) - requires roles.manage permission
    Route::get('access-control', fn () => redirect()->route('access-control.roles.index'))->middleware('can:roles.manage')->name('access-control.index');
    Route::prefix('access-control/roles')->middleware('can:roles.manage')->group(function () {
        Route::get('/', \App\Livewire\Roles\Index::class)->name('access-control.roles.index');
        Route::get('/create', \App\Livewire\Roles\Form::class)->name('access-control.roles.create');
        Route::get('/{role}/edit', \App\Livewire\Roles\Form::class)->name('access-control.roles.edit');
    });

    /*
    |--------------------------------------------------------------------------
    | Orders Module (Phase 4)
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->middleware('can:orders.view')->group(function () {
        // Orders Management
        Route::get('/', OrdersIndex::class)->name('orders.index');
        Route::get('/create', OrdersForm::class)->middleware('can:orders.create')->name('orders.create');
        Route::get('/{order}', OrdersShow::class)->name('orders.show');
        Route::get('/{order}/edit', OrdersForm::class)->middleware('can:orders.update')->name('orders.edit');

        // Order Stock Requests (Phase 5)
        Route::get('/{order}/stock-requests', OrderStockRequestsIndex::class)
            ->middleware('can:stock_requests.view')
            ->name('orders.stock-requests');
    });

    // Order Board (Sales/Receptionist view)
    Route::get('order-board', OrdersBoard::class)
        ->middleware('can:orders.view')
        ->name('orders.board');

    // Payments Index
    Route::get('payments', PaymentsIndex::class)
        ->middleware('can:payments.view')
        ->name('payments.index');

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    Route::prefix('invoices')->middleware('can:orders.view')->group(function () {
        $invoiceDocumentData = function (Invoice $invoice): array {
            return [
                'invoice' => $invoice->load(['order.customer', 'order.branch', 'lines', 'branch']),
                'settings' => BusinessSetting::instance(),
                'paymentMethods' => PaymentMethod::forInvoiceDocument(),
            ];
        };

        Route::get('/', InvoicesIndex::class)->name('invoices.index');
        Route::get('/{invoice}', InvoicesShow::class)->name('invoices.show');

        Route::get('/{invoice}/print', function (Invoice $invoice) use ($invoiceDocumentData) {
            if (! auth()->user()->can('view', $invoice)) {
                abort(403);
            }

            return view('invoices.print', $invoiceDocumentData($invoice));
        })->name('invoices.print');

        Route::get('/{invoice}/download', function (Invoice $invoice, InvoicePdfRenderer $pdfRenderer) use ($invoiceDocumentData) {
            if (! auth()->user()->can('view', $invoice)) {
                abort(403);
            }

            $documentData = $invoiceDocumentData($invoice);
            $pdf = $pdfRenderer->render(
                $documentData['invoice'],
                $documentData['settings'],
                $documentData['paymentMethods']
            );

            return response()->streamDownload(
                fn () => print($pdf),
                $invoice->invoice_no.'.pdf',
                ['Content-Type' => 'application/pdf']
            );
        })->name('invoices.download');
    });

    /*
    |--------------------------------------------------------------------------
    | Delivery Notes (Phase 4)
    |--------------------------------------------------------------------------
    */
    Route::prefix('delivery-notes')->group(function () {
        Route::get('/{deliveryNote}', DeliveryNoteShow::class)
            ->middleware('can:delivery_notes.view')
            ->name('delivery-notes.show');

        Route::get('/{deliveryNote}/print', function (\App\Models\DeliveryNote $deliveryNote) {
            // Authorize view
            if (! auth()->user()->can('delivery_notes.view')) {
                abort(403);
            }

            // Branch check for non-global admins
            if (! auth()->user()->isGlobalAdmin() && $deliveryNote->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You cannot view delivery notes from other branches.');
            }

            return view('delivery-notes.print', ['deliveryNote' => $deliveryNote->load('order.customer', 'order.lines', 'deliveredBy')]);
        })->middleware('can:delivery_notes.view')->name('delivery-notes.print');
    });

    /*
    |--------------------------------------------------------------------------
    | Store Module - Stock Requests (Phase 5)
    |--------------------------------------------------------------------------
    */
    Route::prefix('store')->group(function () {
        Route::get('/stock-requests', StoreStockRequestsIndex::class)
            ->middleware('can:stock_requests.view')
            ->name('store.stock-requests.index');

        Route::get('/stock-requests/{stockRequest}', StoreStockRequestShow::class)
            ->middleware('can:stock_requests.view')
            ->name('store.stock-requests.show');
    });

    /*
    |--------------------------------------------------------------------------
    | SMS Module (Phase 6)
    |--------------------------------------------------------------------------
    */
    Route::prefix('sms')->group(function () {
        Route::get('/logs', \App\Livewire\Sms\Logs\Index::class)
            ->middleware('can:sms.logs.view')
            ->name('sms.logs.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Inventory Module (Phase 3)
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventory')->middleware('can:inventory.view')->group(function () {
        // Stock Dashboard
        Route::get('stock', StockIndex::class)->name('inventory.stock');

        // Items Management
        Route::get('items', ItemsIndex::class)->name('inventory.items.index');

        // Categories Management
        Route::get('categories', CategoriesIndex::class)->name('inventory.categories.index');

        // Units Management
        Route::get('units', UnitsIndex::class)->name('inventory.units.index');

        // Transaction History
        Route::get('transactions', TransactionsIndex::class)->name('inventory.transactions.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Capital Allocation (Phase 7)
    |--------------------------------------------------------------------------
    */
    Route::prefix('capital')->middleware('can:capital.view')->group(function () {
        Route::get('/', \App\Livewire\Capital\Index::class)->name('capital.index');
        Route::get('/create', \App\Livewire\Capital\Create::class)
            ->middleware('can:capital.assign')
            ->name('capital.create');
        Route::get('/{allocation}', \App\Livewire\Capital\Show::class)->name('capital.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Procurement Module (Phase 7)
    |--------------------------------------------------------------------------
    */
    Route::prefix('procurement')->middleware('can:procurement.view')->group(function () {
        // Purchase Requests
        Route::get('/requests', \App\Livewire\Procurement\Requests\Index::class)
            ->name('procurement.requests.index');
        Route::get('/requests/create', \App\Livewire\Procurement\Requests\Form::class)
            ->middleware('can:procurement.request.create')
            ->name('procurement.requests.create');
        Route::get('/requests/{purchaseRequest}', \App\Livewire\Procurement\Requests\Show::class)
            ->name('procurement.requests.show');
        Route::get('/requests/{purchaseRequest}/edit', \App\Livewire\Procurement\Requests\Form::class)
            ->middleware('can:procurement.request.create')
            ->name('procurement.requests.edit');

        // Purchase Orders
        Route::get('/purchase-orders', \App\Livewire\Procurement\PurchaseOrders\Index::class)
            ->middleware('can:procurement.po.manage')
            ->name('procurement.pos.index');
        Route::get('/purchase-orders/{purchaseOrder}', \App\Livewire\Procurement\PurchaseOrders\Show::class)
            ->middleware('can:procurement.po.manage')
            ->name('procurement.pos.show');

        // Receiving
        Route::get('/receiving', \App\Livewire\Procurement\Receiving\Index::class)
            ->middleware('can:procurement.receive')
            ->name('procurement.receiving.index');
        Route::get('/receiving/{purchaseOrder}', \App\Livewire\Procurement\Receiving\Show::class)
            ->middleware('can:procurement.receive')
            ->name('procurement.receiving.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Expenses Module (Phase 8)
    |--------------------------------------------------------------------------
    */
    Route::prefix('expenses')->middleware('can:expenses.view')->group(function () {
        Route::get('/', \App\Livewire\Expenses\Index::class)->name('expenses.index');
        Route::get('/create', \App\Livewire\Expenses\Form::class)
            ->middleware('can:expenses.manage')
            ->name('expenses.create');
        Route::get('/categories', \App\Livewire\Expenses\Categories\Index::class)
            ->middleware('can:expenses.categories.manage')
            ->name('expenses.categories.index');
        Route::get('/{expense}', \App\Livewire\Expenses\Show::class)->name('expenses.show');
        Route::get('/{expense}/edit', \App\Livewire\Expenses\Form::class)
            ->middleware('can:expenses.manage')
            ->name('expenses.edit');
    });

    /*
    |--------------------------------------------------------------------------
    | Messages Module (Phase 9)
    |--------------------------------------------------------------------------
    */
    Route::prefix('messages')->middleware('can:messages.use')->group(function () {
        Route::get('/', \App\Livewire\Messages\Index::class)->name('messages.index');
        Route::get('/{conversation}', \App\Livewire\Messages\Show::class)->name('messages.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports Module (Phase 10)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->middleware('can:reports.view')->group(function () {
        Route::get('/', \App\Livewire\Reports\Index::class)->name('reports.index');
        Route::get('/sales', \App\Livewire\Reports\Sales::class)->name('reports.sales');
        Route::get('/orders', \App\Livewire\Reports\Orders::class)->name('reports.orders');
        Route::get('/expenses', \App\Livewire\Reports\Expenses::class)->name('reports.expenses');
        Route::get('/inventory', \App\Livewire\Reports\Inventory::class)->name('reports.inventory');
        Route::get('/capital', \App\Livewire\Reports\Capital::class)->name('reports.capital');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Branch Switching (for testing / future UI)
    |--------------------------------------------------------------------------
    */
    // Beem SMS Configurations (credentials, templates, marketing)
    Route::get('administration/beem-configurations', \App\Livewire\Sms\BeemConfigurations::class)
        ->middleware('can:sms.templates.manage')
        ->name('beem-configurations.index');

    Route::get('administration/settings', AdministrationBusinessSettings::class)
        ->middleware('can:roles.manage')
        ->name('administration.settings');

    Route::prefix('admin')->middleware('can:roles.manage')->group(function () {
        // Set active branch for admin
        Route::post('active-branch/{branch}', function (Branch $branch) {
            $user = auth()->user();

            if (! $user->isGlobalAdmin()) {
                abort(403, 'Only global admins can switch branches.');
            }

            BranchContext::setActiveBranch($branch->id);

            return redirect()->back()->with('status', "Switched to branch: {$branch->name}");
        })->name('admin.set-active-branch');

        // Reset to the default active branch
        Route::post('active-branch', function () {
            $user = auth()->user();

            if (! $user->isGlobalAdmin()) {
                abort(403, 'Only global admins can switch branches.');
            }

            BranchContext::clearActiveBranch();

            return redirect()->back()->with('status', 'Switched to the default branch.');
        })->name('admin.clear-active-branch');
    });
});

require __DIR__.'/settings.php';

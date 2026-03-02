@props([
    'name' => '',
])

@php
    // Map Material Symbols / semantic names to FontAwesome 6 Pro classes
    $iconMap = [
        // Actions
        'add'              => 'fa-duotone fa-plus',
        'add_circle'       => 'fa-duotone fa-circle-plus',
        'check'            => 'fa-duotone fa-check',
        'check_circle'     => 'fa-duotone fa-circle-check',
        'close'            => 'fa-duotone fa-xmark',
        'cancel'           => 'fa-duotone fa-ban',
        'delete'           => 'fa-duotone fa-trash-can',
        'edit'             => 'fa-duotone fa-pen-to-square',
        'send'             => 'fa-duotone fa-paper-plane',
        'print'            => 'fa-duotone fa-print',
        'download'         => 'fa-duotone fa-download',
        'upload'           => 'fa-duotone fa-upload',
        'search'           => 'fa-duotone fa-magnifying-glass',
        'refresh'          => 'fa-duotone fa-arrows-rotate',
        'lock'             => 'fa-duotone fa-lock',

        // Navigation
        'arrow_back'       => 'fa-duotone fa-arrow-left',
        'arrow_forward'    => 'fa-duotone fa-arrow-right',
        'arrow_upward'     => 'fa-duotone fa-arrow-up',
        'expand_more'      => 'fa-duotone fa-chevron-down',
        'expand_less'      => 'fa-duotone fa-chevron-up',
        'chevron_right'    => 'fa-duotone fa-chevron-right',

        // Content / Documents
        'description'      => 'fa-duotone fa-file-lines',
        'assignment'       => 'fa-duotone fa-clipboard-list',
        'assignment_turned_in' => 'fa-duotone fa-clipboard-check',
        'receipt'          => 'fa-duotone fa-receipt',
        'sell'             => 'fa-duotone fa-tag',

        // People
        'person'           => 'fa-duotone fa-user',
        'person_add'       => 'fa-duotone fa-user-plus',
        'group'            => 'fa-duotone fa-users',
        'contacts_product' => 'fa-duotone fa-address-book',

        // Communication
        'chat'             => 'fa-duotone fa-comment',
        'info'             => 'fa-duotone fa-circle-info',
        'warning'          => 'fa-duotone fa-triangle-exclamation',
        'error'            => 'fa-duotone fa-circle-exclamation',

        // Commerce
        'shopping_cart'    => 'fa-duotone fa-cart-shopping',
        'payments'         => 'fa-duotone fa-money-bills',
        'local_shipping'   => 'fa-duotone fa-truck',
        'account_balance'  => 'fa-duotone fa-building-columns',

        // Inventory
        'inventory_2'      => 'fa-duotone fa-boxes-stacked',
        'archive'          => 'fa-duotone fa-box-archive',
        'folder_open'      => 'fa-duotone fa-folder-open',
        'inbox'            => 'fa-duotone fa-inbox',
        'tune'             => 'fa-duotone fa-sliders',

        // Security
        'shield'           => 'fa-duotone fa-shield-halved',
        'visibility'       => 'fa-duotone fa-eye',
    ];

    $faClass = $iconMap[$name] ?? $iconMap[$slot?->toHtml()] ?? 'fa-duotone fa-circle-question';

    // Map Tailwind size classes to FA sizing
    $attrsClass = $attributes->get('class', '');
    $sizeMap = [
        'size-3'  => 'fa-xs',
        'size-4'  => 'fa-sm',
        'size-5'  => 'fa-lg',
        'size-6'  => 'fa-xl',
        'size-8'  => 'fa-2xl',
        'size-10' => 'fa-2xl',
        'size-12' => 'fa-2xl',
    ];
    $faSize = '';
    foreach ($sizeMap as $tw => $fa) {
        if (str_contains($attrsClass, $tw)) {
            $faSize = $fa;
            break;
        }
    }
@endphp
<i
    {{ $attributes->merge(['class' => $faClass . ' ' . $faSize . ' shrink-0 inline-block'])->except('name') }}
    aria-hidden="true"
></i>

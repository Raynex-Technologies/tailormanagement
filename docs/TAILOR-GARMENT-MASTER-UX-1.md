# TAILOR-GARMENT-MASTER-UX-1

## Implemented

Garment Types are now a first-class Order Catalog section. The existing category CRUD supplies the editor for both the index and the new detail workspace. Creation and editing return to that type's workspace. Category names use slug uniqueness validation; no hard-delete action was added.

The index uses aggregate counts for linked measurements, customization groups, and branch-visible commercial offerings. The workspace has Overview, Measurements, Customization, and Catalogue Items sections, with responsive layouts and existing hero, Flux, neutral, accent, primary-action, and status styling.

## Navigation

- Order Catalog → Garment Types: `/order-catalog/garment-types`.
- Type workspace: `/order-catalog/garment-types/{garmentCategory}`.
- Measurements: Order Catalog → Measurements.
- Customizations: Order Catalog → Customization Options; existing group/choice editors accept the current type/group context and provide a return link.
- Catalogue Items: Order Catalog → Catalog Items, or the type workspace's Catalogue Items section.
- Existing `/admin/garment-categories`, `/admin/garment-options`, and `/admin/garment-option-groups` URLs remain supported.

Master administration now uses the Orders module gate. Online bookings, appointments, and availability retain their Bookings module gate. Public booking behavior is unchanged. Garment-only viewers have a sidebar link without being granted general catalogue visibility.

## Garment Type Workspace

- Overview: name, description, audience, status, sort order, existing image, and relationship summaries.
- Measurements: linked definitions, default units, required indicators, display order, active/archive status, and a searchable management panel.
- Customization: groups and their choices, with contextual links to the existing group and option editors.
- Catalogue Items: garment offerings linked to the type, filtered by the actor's branch availability, with code, price, measurement setting, availability, status, and authorized editing. New offerings preselect the active type.

## Behavior Changes

New measurement forms offer active garment types only. Editing also includes previously linked archived types; unrelated edits preserve those associations, and explicit deselection removes them. New archived/invalid associations are rejected server-side.

The type workspace edits the existing `garment_category_measurement_field` pivot. Required and display-order metadata are shared immediately with the Measurement form. Previously linked archived measurement definitions may be retained or removed; newly assigning an archived definition is rejected.

Catalogue items may retain their existing archived type during unrelated edits, but cannot newly select another archived type. Service items continue to clear the relationship.

Measurement-enabled garments without a type display a contextual warning and remain valid. Existing `OrderCatalogAdministrationTest::test_staff_can_create_garment_and_service_with_domain_defaults` depends on this supported combination; no unverified breaking requirement was introduced.

## Business Rules Preserved

- `GarmentCategory` remains the master/type; `OrderCatalogItem` remains the commercial offering.
- Canonical many-to-many applicability is unchanged.
- Required measurements remain advisory during initial order saving.
- Master viewing/mutation, measurement management, and catalogue management retain their distinct permission checks; no roles or grants changed.
- Catalogue branch availability and exclusive-branch management restrictions remain enforced.
- No automatic measurements, catalogue offerings, or arbitrary data are created with a type.
- Historical order snapshots, package composition, inventory, and public booking conversion were not changed.

## Important Files

- `app/Livewire/GarmentOptions/Show.php`
- `app/Livewire/GarmentOptions/CategoriesIndex.php`
- `app/Livewire/GarmentOptions/Index.php`
- `app/Livewire/GarmentOptions/OptionsIndex.php`
- `app/Livewire/OrderCatalog/MeasurementForm.php`
- `app/Livewire/OrderCatalog/ItemForm.php`
- `app/Models/GarmentCategory.php`
- `resources/views/livewire/garment-options/{show,categories-index,category-editor,index,options-index}.blade.php`
- `resources/views/components/orders/catalog-navigation.blade.php`
- `resources/views/livewire/order-catalog/{index,item-form,measurement-form,package-form}.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/OrderCatalog/GarmentMasterWorkspaceTest.php`
- `tests/Feature/Bookings/GarmentCategoriesCrudTest.php`

## Migrations

None. No schema changes or production database writes were required. Verification used the repository's guarded in-memory test database.

## Verification

- Focused PHPUnit: 31 tests, 201 assertions passed across GarmentMasterWorkspaceTest, GarmentCategoriesCrudTest, CustomerMeasurements1AFoundationTest, and OrderCatalogAdministrationTest.
- Vite production build passed.
- Blade compilation passed.
- Relevant PHP syntax checks passed; changed PHP files formatted with Pint.
- Browser/IAB discovery returned no backends. Local Playwright rendered disposable test HTML at desktop, tablet, and mobile widths in light/dark modes. Screenshots were visually reviewed for hierarchy, spacing, navigation wrapping, status styling, and form layout.
- These renders are layout evidence, not live end-to-end acceptance. The exported shell has notification/file-widget initialization errors outside a running Livewire session. Live signed-in navigation/save/upload acceptance remains pending; server interactions and authorization were exercised by Livewire tests. Temporary render helpers and screenshots were removed after inspection.

## Deferred

Historical measurement-template versioning, hard required-value enforcement, public booking measurement redesign/conversion, legacy-column cleanup, and domain/tenant/package redesign remain separate tasks.

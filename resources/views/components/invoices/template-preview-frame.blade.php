@props([
    'template',
    'mode' => 'thumbnail',
])

@php($isModalPreview = $mode === 'modal')

<div
    x-data="{
        scale: 0.3,
        documentWidth: 794,
        documentHeight: 1123,
        offsetX: 0,
        offsetY: 0,
        viewport: null,
        observer: null,
        init() {
            this.viewport = this.$el;
            this.observer = new ResizeObserver(() => this.fitDocument());
            this.observer.observe(this.viewport);
            this.$nextTick(() => this.fitDocument());
        },
        fitDocument() {
            const availableWidth = this.viewport?.clientWidth ?? 0;
            const availableHeight = this.viewport?.clientHeight ?? 0;

            if (availableWidth <= 0 || availableHeight <= 0) {
                return;
            }

            this.scale = Math.min(
                availableWidth / this.documentWidth,
                availableHeight / this.documentHeight,
                1,
            );
            this.offsetX = (availableWidth - (this.documentWidth * this.scale)) / 2;
            this.offsetY = (availableHeight - (this.documentHeight * this.scale)) / 2;
        },
        measureDocument() {
            this.$nextTick(() => {
                const previewDocument = this.$refs.frame.contentDocument;
                const page = previewDocument?.querySelector('.invoice-shell');

                if (!page) {
                    return;
                }

                this.documentWidth = Math.max(794, page.scrollWidth, page.offsetWidth);
                this.documentHeight = Math.max(1123, page.scrollHeight, page.offsetHeight);
                this.fitDocument();

                requestAnimationFrame(() => {
                    this.documentWidth = Math.max(794, page.scrollWidth, page.offsetWidth);
                    this.documentHeight = Math.max(1123, page.scrollHeight, page.offsetHeight);
                    this.fitDocument();
                });
            });
        },
        destroy() {
            this.observer?.disconnect();
        },
    }"
    {{ $attributes->class([
        'relative mx-auto w-full overflow-hidden bg-zinc-200 dark:bg-zinc-950',
        'h-[360px] max-w-sm rounded-md shadow-sm sm:h-[400px] xl:h-[420px]' => ! $isModalPreview,
        'h-[52dvh] min-h-[280px] rounded-xl border border-zinc-200 sm:h-[calc(100dvh-15rem)] sm:min-h-[420px] lg:h-[calc(100dvh-14rem)] dark:border-zinc-700' => $isModalPreview,
    ]) }}
    data-invoice-template-frame="{{ $mode }}"
    @if ($isModalPreview) data-fit-page-preview @else data-invoice-template-thumbnail data-thumbnail-scale-to-fit @endif
>
    <iframe
        x-ref="frame"
        x-on:load="measureDocument()"
        :style="`width: ${documentWidth}px; height: ${documentHeight}px; transform: translate(${offsetX}px, ${offsetY}px) scale(${scale});`"
        class="pointer-events-none absolute left-0 top-0 origin-top-left border-0 bg-white"
        title="{{ $isModalPreview
            ? __('Complete fit-page preview of :template invoice template', ['template' => $template->name])
            : __('Full-page thumbnail of :template invoice template', ['template' => $template->name]) }}"
        loading="lazy"
        sandbox="allow-same-origin"
        scrolling="no"
        tabindex="-1"
        src="{{ route('administration.settings.invoice-template-preview', $template->id) }}"
    ></iframe>
</div>

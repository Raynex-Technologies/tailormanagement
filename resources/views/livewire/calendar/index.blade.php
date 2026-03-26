@once
    <style>
        .fc .fc-toolbar {
            display: none;
        }

        .fc .fc-daygrid-day-number {
            color: #18181b;
            font-weight: 600;
            text-decoration: none;
        }

        .dark .fc .fc-daygrid-day-number {
            color: #f4f4f5;
        }

        .fc-theme-standard .fc-scrollgrid,
        .fc-theme-standard .fc-daygrid-day,
        .fc-theme-standard .fc-daygrid th {
            border-color: rgba(161, 161, 170, 0.35);
        }

        .dark .fc-theme-standard .fc-scrollgrid,
        .dark .fc-theme-standard .fc-daygrid-day,
        .dark .fc-theme-standard .fc-daygrid th {
            border-color: rgba(113, 113, 122, 0.55);
        }

        .fc .fc-timegrid-slot,
        .fc .fc-timegrid-axis,
        .fc .fc-timegrid-divider,
        .fc .fc-timegrid-body,
        .fc .fc-timegrid-body table,
        .fc .fc-timegrid table {
            border-color: transparent !important;
        }

        .fc .fc-timegrid-col {
            border-inline-width: 0 !important;
            border-left-color: transparent !important;
            border-right-color: transparent !important;
        }

        .fc .fc-timegrid-slot {
            height: 6rem;
            border-top-color: rgba(161, 161, 170, 0.35) !important;
        }

        .dark .fc .fc-timegrid-slot {
            border-top-color: rgba(113, 113, 122, 0.55) !important;
        }

        .fc .fc-timegrid-slot-minor {
            border-top-color: transparent !important;
        }

        .fc .fc-timegrid-axis-cushion {
            color: #52525b;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .dark .fc .fc-timegrid-axis-cushion {
            color: #a1a1aa;
        }

        .fc .fc-timegrid .fc-col-header-cell {
            border-color: transparent;
            background: transparent;
            padding: 0.15rem 0.25rem 0.5rem;
        }

        .fc .fc-timegrid .fc-col-header-cell,
        .fc .fc-timegrid .fc-timegrid-axis {
            border-inline-width: 0 !important;
            border-left-color: transparent !important;
            border-right-color: transparent !important;
        }

        .fc .fc-timegrid .fc-col-header-cell-cushion {
            width: 100%;
            padding: 0;
            text-decoration: none;
        }

        .fc-day-header-card {
            min-height: 4.9rem;
            border-radius: 0.95rem;
            background: #e4e4e7;
            border: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.15rem;
            width: 100%;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 120ms ease;
        }

        .fc-day-header-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -16px rgba(15, 23, 42, 0.75);
        }

        .dark .fc-day-header-card {
            background: rgba(63, 63, 70, 0.85);
        }

        .fc-day-header-card.is-today {
            box-shadow: inset 0 0 0 2px #84cc16;
        }

        .dark .fc-day-header-card.is-today {
            box-shadow: inset 0 0 0 2px #a3e635;
        }

        .fc-day-header-weekday {
            font-size: 0.74rem;
            font-weight: 600;
            line-height: 1.1;
            color: #111827;
        }

        .fc-day-header-date {
            font-size: 2rem;
            line-height: 1;
            font-weight: 700;
            color: #111827;
        }

        .dark .fc-day-header-weekday,
        .dark .fc-day-header-date {
            color: #f4f4f5;
        }

        .fc .fc-timegrid-event {
            border-width: 0 !important;
            border-radius: 0.9rem !important;
            box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.55);
            min-height: 3.4rem;
        }

        .fc .fc-timegrid-event .fc-event-main {
            padding: 0.65rem 0.7rem;
        }

        .fc .fc-timegrid-event-harness {
            margin-inline: 0.18rem;
            margin-block: 0.15rem;
        }

        .fc-event-card {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            line-height: 1.2;
        }

        .fc-event-time {
            font-size: 0.7rem;
            font-weight: 600;
            opacity: 0.85;
        }

        .fc-event-title-text {
            font-size: 0.8rem;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .fc-event-subtitle {
            font-size: 0.7rem;
            opacity: 0.85;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .calendar-activity-scroll {
            overflow-x: auto;
        }

        .calendar-activity-scroll .fc {
            min-width: 72rem;
        }

        @media (max-width: 1280px) {
            .calendar-activity-scroll .fc {
                min-width: 64rem;
            }
        }
    </style>

    <script>
        window.tailorCalendarModule = function (config) {
            return {
                    calendar: null,
                    calendarTitle: '',
                    loadError: '',
                    selectedDate: config.selectedDate,
                    appView: config.wireView,
                    locale: config.locale,
                    timezone: config.timezone,
                    showOrderDue: config.showOrderDue,
                    showBookings: config.showBookings,
                    eventsEndpoint: config.eventsEndpoint,
                    fullCalendarBootstrapUrl: config.fullCalendarBootstrapUrl,
                    isSyncingFromCalendar: false,

                    async init() {
                        this.calendarTitle = this.formatFallbackTitle(this.selectedDate);

                        try {
                            await this.ensureFullCalendarBundle();
                            this.mountCalendar();
                        } catch (error) {
                            this.loadError = this.formatClientError('Unable to initialize calendar.', error);
                            console.error('Calendar initialization failed.', error);
                            return;
                        }

                        this.$watch('showOrderDue', () => this.refetchEvents());
                        this.$watch('showBookings', () => this.refetchEvents());
                        this.$watch('selectedDate', (value) => {
                            if (this.isSyncingFromCalendar) {
                                return;
                            }

                            this.syncDateFromWire(value);
                        });
                        this.$watch('appView', (value) => {
                            if (this.isSyncingFromCalendar) {
                                return;
                            }

                            this.syncViewFromWire(value);
                        });
                    },

                    async ensureFullCalendarBundle() {
                        if (window.TailorFullCalendar?.Calendar) {
                            return;
                        }

                        const bootstrapUrl = this.fullCalendarBootstrapUrl;
                        if (bootstrapUrl) {
                            try {
                                if (!window.__tailorFullCalendarBootstrapPromise) {
                                    window.__tailorFullCalendarBootstrapPromise = import(bootstrapUrl)
                                        .catch((error) => {
                                            window.__tailorFullCalendarBootstrapPromise = null;
                                            throw error;
                                        });
                                }

                                await window.__tailorFullCalendarBootstrapPromise;

                                if (window.TailorFullCalendar?.Calendar) {
                                    return;
                                }
                            } catch (error) {
                                console.error('Direct FullCalendar bootstrap import failed.', error);
                            }
                        }

                        await new Promise((resolve, reject) => {
                            if (window.TailorFullCalendar?.Calendar) {
                                resolve();
                                return;
                            }

                            const timeoutId = window.setTimeout(() => {
                                cleanup();
                                reject(new Error('Calendar assets are not loaded.'));
                            }, 7000);

                            const onReady = () => {
                                cleanup();
                                resolve();
                            };

                            const onFailure = () => {
                                cleanup();
                                reject(new Error('Calendar assets failed to load.'));
                            };

                            const cleanup = () => {
                                window.clearTimeout(timeoutId);
                                window.removeEventListener('tailor-fullcalendar:ready', onReady);
                                window.removeEventListener('tailor-fullcalendar:failed', onFailure);
                            };

                            window.addEventListener('tailor-fullcalendar:ready', onReady, { once: true });
                            window.addEventListener('tailor-fullcalendar:failed', onFailure, { once: true });
                        });
                    },

                    mountCalendar() {
                        const fullCalendar = window.TailorFullCalendar;
                        if (!fullCalendar?.Calendar || !this.$refs.calendar) {
                            this.loadError = 'Unable to initialize calendar.';
                            return;
                        }

                        if (this.calendar) {
                            this.calendar.destroy();
                        }

                        this.calendar = new fullCalendar.Calendar(this.$refs.calendar, {
                            plugins: fullCalendar.plugins ?? [],
                            initialView: this.toFullView(this.appView),
                            initialDate: this.selectedDate,
                            locale: this.locale,
                            timeZone: this.timezone,
                            firstDay: 0,
                            height: 500,
                            nowIndicator: true,
                            selectable: true,
                            editable: false,
                            headerToolbar: false,
                            dayMaxEvents: true,
                            allDaySlot: false,
                            slotMinTime: '10:00:00',
                            slotMaxTime: '18:00:00',
                            slotDuration: '01:00:00',
                            slotLabelInterval: '01:00:00',
                            slotLabelFormat: {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false,
                            },
                            scrollTime: '10:00:00',
                            scrollTimeReset: false,
                            eventTimeFormat: {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false,
                            },
                            views: {
                                dayGridMonth: {
                                    dayMaxEventRows: 3,
                                },
                                timeGridRollingWeek: {
                                    type: 'timeGrid',
                                    duration: { days: 7 },
                                    dateIncrement: { days: 7 },
                                    dayMaxEvents: 4,
                                    dayMinWidth: 170,
                                    visibleRange: (currentDate) => this.weeklyVisibleRange(currentDate),
                                },
                                timeGridWeek: {
                                    dayMaxEvents: 4,
                                    dayMinWidth: 170,
                                },
                                timeGridDay: {
                                    dayMaxEvents: 8,
                                },
                            },
                            events: (info, success, failure) => this.loadEvents(info, success, failure),
                            datesSet: (info) => this.handleDatesSet(info),
                            dateClick: (info) => this.handleDateClick(info),
                            eventClick: (info) => this.handleEventClick(info),
                            dayHeaderContent: (arg) => this.renderDayHeader(arg),
                            eventContent: (arg) => this.renderEventContent(arg),
                        });

                        this.calendar.render();
                        this.calendarTitle = this.calendar.view.title;
                    },

                    async loadEvents(info, success, failure) {
                        this.loadError = '';

                        try {
                            const start = info?.startStr ?? (info?.start ? info.start.toISOString() : null);
                            const end = info?.endStr ?? (info?.end ? info.end.toISOString() : null);
                            const params = new URLSearchParams();
                            if (start) {
                                params.set('start', start);
                            }
                            if (end) {
                                params.set('end', end);
                            }
                            params.set('show_order_due', this.showOrderDue ? '1' : '0');
                            params.set('show_bookings', this.showBookings ? '1' : '0');
                            params.set('date', this.selectedDate || '');
                            params.set('view', this.appView || 'weekly');

                            const response = await fetch(`${this.eventsEndpoint}?${params.toString()}`, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error(`Calendar events request failed with status ${response.status}.`);
                            }

                            const payload = await response.json();
                            const events = this.normalizeEvents(payload);
                            success(events);
                        } catch (error) {
                            this.loadError = this.formatClientError('Failed to load calendar events.', error);
                            console.error('Calendar events load failed.', error);
                            failure(error);
                        }
                    },

                    normalizeEvents(payload) {
                        const rawEvents = Array.isArray(payload)
                            ? payload
                            : (payload && typeof payload === 'object' ? Object.values(payload) : []);

                        return rawEvents
                            .map((rawEvent, index) => this.normalizeEvent(rawEvent, index))
                            .filter(Boolean);
                    },

                    normalizeEvent(rawEvent, index) {
                        if (!rawEvent || typeof rawEvent !== 'object') {
                            return null;
                        }

                        const start = typeof rawEvent.start === 'string' ? rawEvent.start : null;
                        if (!start) {
                            return null;
                        }

                        const event = {
                            id: String(rawEvent.id ?? `evt-${index}`),
                            title: (typeof rawEvent.title === 'string' && rawEvent.title.trim() !== '')
                                ? rawEvent.title.trim()
                                : 'Order',
                            start,
                            allDay: Boolean(rawEvent.allDay),
                        };

                        if (typeof rawEvent.end === 'string' && rawEvent.end !== '') {
                            event.end = rawEvent.end;
                        }

                        if (typeof rawEvent.backgroundColor === 'string' && rawEvent.backgroundColor !== '') {
                            event.backgroundColor = rawEvent.backgroundColor;
                        }

                        if (typeof rawEvent.borderColor === 'string' && rawEvent.borderColor !== '') {
                            event.borderColor = rawEvent.borderColor;
                        }

                        if (typeof rawEvent.textColor === 'string' && rawEvent.textColor !== '') {
                            event.textColor = rawEvent.textColor;
                        }

                        event.extendedProps = (rawEvent.extendedProps && typeof rawEvent.extendedProps === 'object')
                            ? rawEvent.extendedProps
                            : {};

                        return event;
                    },

                    handleDatesSet(info) {
                        this.calendarTitle = info.view.title;

                        const activeDate = this.resolveActiveDate(info);
                        const nextView = this.toWireView(info.view.type);
                        const shouldSyncSelectedDate = this.selectedDate !== activeDate;
                        const shouldSyncView = this.appView !== nextView;

                        if (!shouldSyncSelectedDate && !shouldSyncView) {
                            return;
                        }

                        this.isSyncingFromCalendar = true;

                        if (shouldSyncSelectedDate) {
                            this.selectedDate = activeDate;
                        }

                        if (shouldSyncView) {
                            this.appView = nextView;
                        }

                        if (typeof window.queueMicrotask === 'function') {
                            window.queueMicrotask(() => {
                                this.isSyncingFromCalendar = false;
                            });
                        } else {
                            window.setTimeout(() => {
                                this.isSyncingFromCalendar = false;
                            }, 0);
                        }
                    },

                    handleDateClick(info) {
                        const date = this.toYmd(info.date);
                        this.setView('daily', date);
                    },

                    handleEventClick(info) {
                        const url = info.event?.extendedProps?.url;
                        if (!url) {
                            return;
                        }

                        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                            window.Livewire.navigate(url);
                            return;
                        }

                        window.location.href = url;
                    },

                    renderDayHeader(arg) {
                        const viewType = arg?.view?.type ?? '';
                        if (!viewType.startsWith('timeGrid')) {
                            return { text: arg.text };
                        }

                        const weekday = arg.date.toLocaleDateString(this.locale, { weekday: 'long' });
                        const dayNumber = arg.date.toLocaleDateString(this.locale, { day: 'numeric' });
                        const card = document.createElement('button');
                        card.type = 'button';
                        card.className = 'fc-day-header-card';
                        card.setAttribute('aria-label', `Open ${weekday} ${dayNumber}`);
                        card.addEventListener('click', (event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            this.openDayColumn(arg.date);
                        });

                        if (this.sameDay(arg.date, new Date())) {
                            card.classList.add('is-today');
                        }

                        const weekdayEl = document.createElement('div');
                        weekdayEl.className = 'fc-day-header-weekday';
                        weekdayEl.textContent = weekday;

                        const dateEl = document.createElement('div');
                        dateEl.className = 'fc-day-header-date';
                        dateEl.textContent = dayNumber;

                        card.appendChild(weekdayEl);
                        card.appendChild(dateEl);

                        return { domNodes: [card] };
                    },

                    renderEventContent(arg) {
                        const wrap = document.createElement('div');
                        wrap.className = 'fc-event-card';
                        const eventType = arg.event?.extendedProps?.type;

                        if (arg.timeText && eventType !== 'order_due') {
                            const time = document.createElement('div');
                            time.className = 'fc-event-time';
                            time.textContent = arg.timeText;
                            wrap.appendChild(time);
                        }

                        const title = document.createElement('div');
                        title.className = 'fc-event-title-text';
                        title.textContent = arg.event.title || '';
                        wrap.appendChild(title);

                        const subtitle = arg.event.extendedProps?.subtitle;
                        if (subtitle && arg.view.type !== 'dayGridMonth') {
                            const sub = document.createElement('div');
                            sub.className = 'fc-event-subtitle';
                            sub.textContent = subtitle;
                            wrap.appendChild(sub);
                        }

                        return { domNodes: [wrap] };
                    },

                    prev() {
                        if (!this.calendar) {
                            return;
                        }

                        this.calendar.prev();
                    },

                    next() {
                        if (!this.calendar) {
                            return;
                        }

                        this.calendar.next();
                    },

                    today() {
                        if (!this.calendar) {
                            return;
                        }

                        this.calendar.today();
                    },

                    setView(view, focusDate = null) {
                        if (!this.calendar) {
                            return;
                        }

                        const targetView = this.toFullView(view);
                        this.calendar.changeView(targetView, focusDate ?? this.selectedDate);

                        if (focusDate) {
                            this.selectedDate = focusDate;
                        }

                        this.appView = view;
                    },

                    openDayColumn(dateValue) {
                        this.setView('daily', this.toYmd(dateValue));
                    },

                    syncDateFromWire(value) {
                        if (!this.calendar || !value) {
                            return;
                        }

                        const currentDate = this.toYmd(this.calendar.getDate());
                        if (currentDate !== value) {
                            this.calendar.gotoDate(value);
                        }
                    },

                    syncViewFromWire(value) {
                        if (!this.calendar || !value) {
                            return;
                        }

                        const targetView = this.toFullView(value);
                        if (this.calendar.view.type !== targetView) {
                            this.calendar.changeView(targetView);
                        }
                    },

                    refetchEvents() {
                        if (!this.calendar) {
                            return;
                        }

                        this.calendar.refetchEvents();
                    },

                    toYmd(dateValue) {
                        const date = dateValue instanceof Date ? dateValue : new Date(dateValue);
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    },

                    weeklyVisibleRange(currentDate) {
                        const baseDate = currentDate instanceof Date
                            ? new Date(currentDate)
                            : new Date(`${this.selectedDate}T00:00:00`);

                        baseDate.setHours(0, 0, 0, 0);

                        const start = new Date(baseDate);
                        start.setDate(start.getDate() - 1);

                        const end = new Date(start);
                        end.setDate(end.getDate() + 7);

                        return { start, end };
                    },

                    resolveActiveDate(info) {
                        if (this.calendar) {
                            return this.toYmd(this.calendar.getDate());
                        }

                        if (info?.start) {
                            return this.toYmd(info.start);
                        }

                        return this.selectedDate;
                    },

                    sameDay(leftValue, rightValue) {
                        const left = leftValue instanceof Date ? leftValue : new Date(leftValue);
                        const right = rightValue instanceof Date ? rightValue : new Date(rightValue);

                        return left.getFullYear() === right.getFullYear()
                            && left.getMonth() === right.getMonth()
                            && left.getDate() === right.getDate();
                    },

                    toFullView(wireView) {
                        switch (wireView) {
                            case 'daily':
                                return 'timeGridDay';
                            case 'monthly':
                                return 'dayGridMonth';
                            default:
                                return 'timeGridRollingWeek';
                        }
                    },

                    toWireView(fullView) {
                        switch (fullView) {
                            case 'timeGridDay':
                                return 'daily';
                            case 'dayGridMonth':
                                return 'monthly';
                            case 'timeGridRollingWeek':
                                return 'weekly';
                            default:
                                return 'weekly';
                        }
                    },

                    formatFallbackTitle(dateValue) {
                        const date = new Date(`${dateValue}T00:00:00`);
                        return date.toLocaleDateString(undefined, {
                            month: 'long',
                            year: 'numeric',
                        });
                    },

                    formatClientError(prefix, error) {
                        const rawMessage = (error && typeof error.message === 'string')
                            ? error.message.trim()
                            : '';

                        if (!rawMessage) {
                            return prefix;
                        }

                        const safeMessage = rawMessage.length > 120
                            ? `${rawMessage.slice(0, 117)}...`
                            : rawMessage;

                        return `${prefix} ${safeMessage}`;
                    },
                };
        };
    </script>
@endonce

@php
    $sidebar = $this->sidebarMonthData;
    $selectedEvents = $this->selectedDateEvents;
    $nextEvent = $selectedEvents[0] ?? null;
    $access = $this->accessData;
@endphp

<div
    class="space-y-6"
    data-full-calendar-module
    x-data="window.tailorCalendarModule({
        selectedDate: @entangle('selectedDate').live,
        wireView: @entangle('view').live,
        locale: @js($this->calendarConfig['locale']),
        timezone: @js($this->calendarConfig['timezone']),
        showOrderDue: @entangle('showOrderDue').live,
        showBookings: @entangle('showBookings').live,
        eventsEndpoint: @js(route('calendar.events')),
        fullCalendarBootstrapUrl: @js(\Illuminate\Support\Facades\Vite::asset('resources/js/app.js')),
    })"
    x-init="init()"
>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Calendar') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 xl:grid-cols-[19rem_minmax(0,1fr)]">
        <div class="space-y-4 min-w-0">
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 lg:p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $sidebar['month_label'] }}</h2>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            wire:click="previousMonth"
                            class="flex size-7 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            aria-label="{{ __('Previous month') }}"
                        >
                            <i class="fa-duotone fa-chevron-left text-xs"></i>
                        </button>
                        <button
                            type="button"
                            wire:click="nextMonth"
                            class="flex size-7 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            aria-label="{{ __('Next month') }}"
                        >
                            <i class="fa-duotone fa-chevron-right text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="grid gap-1 text-center" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                        @foreach ($sidebar['weekdays'] as $weekday)
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $weekday }}</span>
                        @endforeach
                    </div>

                    <div class="mt-2 space-y-2">
                        @foreach ($sidebar['weeks'] as $week)
                            <div class="grid gap-1.5" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                                @foreach ($week as $day)
                                    @php
                                        $stateStyle = '';

                                        if ($day['has_orders'] && $day['has_bookings']) {
                                            $stateClasses = 'text-zinc-900 dark:text-zinc-100';
                                            $stateStyle = 'background: linear-gradient(135deg, oklch(76.9% 0.188 70.08) 0%, #ddd6fe 100%);';
                                        } elseif ($day['has_orders']) {
                                            $stateClasses = 'text-amber-900 dark:text-amber-100';
                                            $stateStyle = 'background-color: oklch(76.9% 0.188 70.08);';
                                        } elseif ($day['has_bookings']) {
                                            $stateClasses = 'bg-violet-200 text-violet-900 dark:bg-violet-700/55 dark:text-violet-100';
                                        } else {
                                            $stateClasses = 'bg-transparent text-zinc-700 dark:text-zinc-200 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-800/70';
                                        }

                                        if (! $day['in_month']) {
                                            $stateClasses .= ' text-zinc-400 dark:text-zinc-500';
                                        }

                                        if ($day['is_today'] && $day['is_selected']) {
                                            $stateClasses .= ' ring-2 ring-blue-500 dark:ring-blue-400';
                                        } elseif ($day['is_selected']) {
                                            $stateClasses .= ' ring-2 ring-blue-300 dark:ring-blue-500';
                                        } elseif ($day['is_today']) {
                                            $stateClasses .= ' ring-2 ring-lime-500 dark:ring-lime-400';
                                        }
                                    @endphp

                                    <button
                                        type="button"
                                        wire:click="selectDate('{{ $day['date'] }}')"
                                        class="group flex h-10 w-full items-center justify-center"
                                        title="{{ $day['date'] }}"
                                    >
                                        <span
                                            class="relative flex size-8 items-center justify-center rounded-full text-xs font-semibold transition {{ $stateClasses }}"
                                            @if ($stateStyle !== '') style="{{ $stateStyle }}" @endif
                                        >
                                            {{ $day['day'] }}

                                            @if ($day['has_bookings'])
                                                <span class="absolute -bottom-0.5 -left-0.5 size-1.5 rounded-full bg-violet-500"></span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-2xl p-4 shadow-sm border border-teal-200/70 dark:border-teal-800/50" style="background-color: #0f8b8d;">
                <p class="text-xs uppercase tracking-wide text-white/80">{{ __('Selected date') }}</p>

                @if ($nextEvent)
                    <p class="mt-2 text-lg font-semibold text-white">{{ $nextEvent['title'] }}</p>
                    <p class="mt-1 text-sm text-white/85">
                        {{ isset($nextEvent['allDay']) && $nextEvent['allDay'] ? __('All day') : \Illuminate\Support\Str::replace('T', ' ', $nextEvent['start']) }}
                    </p>
                    @if (! empty($nextEvent['extendedProps']['subtitle']))
                        <p class="mt-2 text-xs text-white/75">{{ $nextEvent['extendedProps']['subtitle'] }}</p>
                    @endif
                @else
                    <p class="mt-2 text-lg font-semibold text-white">{{ __('No events selected') }}</p>
                    <p class="mt-1 text-sm text-white/85">{{ __('Choose a date to preview events.') }}</p>
                @endif
            </div>

            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Filters') }}</h3>
                </div>

                <div class="space-y-2.5 text-sm text-zinc-600 dark:text-zinc-300">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="showOrderDue" class="rounded border-zinc-300 text-teal-600 focus:ring-teal-500" />
                        <span>{{ __('Order due dates') }}</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="showBookings" class="rounded border-zinc-300 text-violet-600 focus:ring-violet-500" />
                        <span>{{ __('Booking slots') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="space-y-4 min-w-0">
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="prev()"
                            class="flex size-8 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            aria-label="{{ __('Previous period') }}"
                        >
                            <i class="fa-duotone fa-chevron-left text-xs"></i>
                        </button>

                        <h1 class="text-xl font-bold text-zinc-900 dark:text-white" x-text="calendarTitle"></h1>

                        <button
                            type="button"
                            @click="next()"
                            class="flex size-8 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            aria-label="{{ __('Next period') }}"
                        >
                            <i class="fa-duotone fa-chevron-right text-xs"></i>
                        </button>

                        <button
                            type="button"
                            @click="today()"
                            class="ml-1 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Today') }}
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-xl bg-zinc-100 p-0.5 dark:bg-zinc-900/70">
                            @foreach (['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly')] as $mode => $label)
                                <button
                                    type="button"
                                    @click="setView('{{ $mode }}')"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                                    :class="appView === '{{ $mode }}'
                                        ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white'
                                        : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            disabled
                            title="{{ __('Create event will be enabled with booking management.') }}"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold text-zinc-700 opacity-70 dark:text-zinc-100"
                            style="background: linear-gradient(135deg, #f4d35e 0%, #f7c948 100%);"
                        >
                            <i class="fa-duotone fa-plus text-[10px]"></i>
                            {{ __('Create Event') }}
                        </button>
                    </div>
                </div>
            </div>

            @if ($access['requires_branch_selection'])
                <div class="rounded-2xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-center gap-3">
                    <i class="fa-duotone fa-triangle-exclamation text-amber-500 shrink-0"></i>
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-300">{{ __('Select a branch to view calendar events.') }}</p>
                </div>
            @endif

            @if (! $access['can_view_order_dates'])
                <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-700/70 bg-zinc-50 dark:bg-zinc-900/40 p-4 flex items-center gap-3">
                    <i class="fa-duotone fa-lock text-zinc-500 shrink-0"></i>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('Order due-date events are hidden because your role lacks order access.') }}</p>
                </div>
            @endif

            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <div wire:ignore class="calendar-activity-scroll p-3 sm:p-4">
                    <div x-ref="calendar"></div>
                </div>

                <div x-show="loadError" x-cloak class="px-4 pb-4">
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800/50 dark:bg-red-900/20 dark:text-red-300">
                        <span x-text="loadError"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

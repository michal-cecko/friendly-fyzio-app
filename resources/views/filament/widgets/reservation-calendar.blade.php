@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
    $hasSelection = filled($therapistIds);
@endphp

<x-filament-widgets::widget>
    <div
        class="ff-cal"
        :class="{ 'ff-day-view': view === 'timeGridDay' }"
        x-data="{
            title: '',
            view: @js($this->calendarView ?: 'timeGridWeek'),
            cal: null,
            bind() {
                const el = this.$root.querySelector('.filament-fullcalendar');
                const data = (el && window.Alpine) ? window.Alpine.$data(el) : null;
                if (!data || !data.calendar) return false;
                this.cal = data.calendar;
                const sync = () => {
                    this.title = this.cal.view.title;
                    this.view = this.cal.view.type;
                    this.$wire.calendarView = this.cal.view.type;
                    const d = this.cal.getDate();
                    this.$wire.calendarDate = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                };
                this.cal.on('datesSet', sync);
                sync();
                return true;
            },
            init() {
                let tries = 0;
                const tick = () => { if (this.bind() || tries++ > 120) return; setTimeout(tick, 50); };
                tick();
            },
            today() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--today')); },
            prev() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--prev')); },
            next() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--next')); },
            setView(v) { this.view = v; window.dispatchEvent(new CustomEvent('filament-fullcalendar--view', { detail: { view: v } })); },
        }"
    >
        <div class="ff-toolbar">
            <div class="ff-toolbar-left" x-show="! $wire.listMode">
                <button type="button" class="ff-btn-today" @click="today()">Dnes</button>
                <div class="ff-nav">
                    <button type="button" class="ff-nav-btn" @click="prev()" aria-label="Předchozí">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <button type="button" class="ff-nav-btn" @click="next()" aria-label="Další">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </div>
                <h2 class="ff-title" x-text="title">&nbsp;</h2>
            </div>

            <div class="ff-toolbar-right">
                <div class="ff-views">
                    <button type="button" @click="$wire.set('listMode', false); setView('timeGridWeek')" :class="{ 'ff-view-active': ! $wire.listMode && view === 'timeGridWeek' }">Týden</button>
                    <button type="button" @click="$wire.set('listMode', false); setView('timeGridDay')" :class="{ 'ff-view-active': ! $wire.listMode && view === 'timeGridDay' }">Den</button>
                    <button type="button" wire:click="$set('listMode', true)" :class="{ 'ff-view-active': $wire.listMode }">Seznam</button>
                </div>

                <button type="button" class="ff-select-toggle" x-show="! $wire.listMode" :class="{ 'ff-select-toggle-active': $wire.selectionMode }" wire:click="toggleSelectionMode" title="Hromadný výběr rezervací" aria-label="Hromadný výběr rezervací">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    <span>Vybrat</span>
                </button>

                <x-filament::actions :actions="$this->getCachedHeaderActions()" class="ff-header-actions" />
            </div>
        </div>

        @if (! $listMode && $selectionMode)
            <div class="ff-selectbar">
                <span class="ff-selectbar-count">{{ count($selectedIds) }} vybráno</span>
                <div class="ff-selectbar-actions">
                    {{-- TODO: {{ $this->sendEmailAction }} — deferred until the Mason email-template package lands. --}}
                    {{ $this->cancelSelectedAction }}
                    {{ $this->deleteSelectedAction }}
                    @if ($this->restoreSelectedAction->isVisible())
                        {{ $this->restoreSelectedAction }}
                    @endif
                </div>
                <button type="button" class="ff-selectbar-clear" wire:click="clearSelection" @disabled(count($selectedIds) === 0)>Zrušit výběr</button>
            </div>
        @endif

        <div class="ff-filterbar">
            <div class="ff-filter-row">
                <span class="ff-filter-label">Terapeuté:</span>

                @foreach ($this->therapists() as $therapist)
                    @php
                        $id = $therapist->getKey();
                        $inFilter = in_array($id, $therapistIds, true);
                        $isOn = ! $hasSelection || $inFilter;
                        $accent = $this->therapistColor($id);
                    @endphp
                    <button
                        type="button"
                        wire:click="toggleTherapist('{{ $id }}')"
                        @class(['ff-chip', 'is-muted' => ! $isOn])
                        @style(["border-color: {$accent}" => $isOn])
                    >
                        <span class="ff-chip-avatar" style="background: {{ $accent }}">{{ $this->therapistInitials($therapist->user?->name) }}</span>
                        <span>{{ $therapist->user?->name }}</span>
                        @if ($inFilter)
                            <svg class="ff-chip-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        @endif
                    </button>
                @endforeach

                <button type="button" class="ff-all" wire:click="clearTherapists">Všichni</button>

                <span class="ff-count" x-show="! $wire.listMode">{{ $this->weekCountLabel() }}</span>
            </div>

            <div class="ff-filter-row ff-filter-controls">
                <div class="ff-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Hledat klienta nebo službu…">
                </div>

                <button type="button" class="ff-filters-toggle" :class="{ 'ff-filters-toggle-open': $wire.showFilters }" @click="$wire.showFilters = ! $wire.showFilters" aria-label="Zobrazit nebo skrýt filtry">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                    <span>Filtry</span>
                    @if ($this->activeFilterCount() > 0)
                        <span class="ff-filters-badge">{{ $this->activeFilterCount() }}</span>
                    @endif
                    <svg class="ff-filters-chevron" :class="{ 'ff-rotate': $wire.showFilters }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                @if ($this->hasActiveFilters())
                    <button type="button" class="ff-reset" wire:click="resetFilters" title="Zrušit všechny filtry" aria-label="Zrušit všechny filtry">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    </button>
                @endif
            </div>

            <div class="ff-filter-row ff-filament-filters" x-show="$wire.showFilters" x-cloak>
                {{ $this->filtersForm }}
            </div>
        </div>

        <div x-show="! $wire.listMode" x-effect="if (! $wire.listMode && cal) $nextTick(() => cal.updateSize())">
        <div
            wire:ignore
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-fullcalendar-alpine', 'saade/filament-fullcalendar') }}"
            x-ignore
            x-data="fullcalendar({
                locale: @js($plugin->getLocale()),
                plugins: @js($plugin->getPlugins()),
                schedulerLicenseKey: @js($plugin->getSchedulerLicenseKey()),
                timeZone: @js($plugin->getTimezone()),
                config: @js($this->getConfig()),
                editable: @json($plugin->isEditable()),
                selectable: @json($plugin->isSelectable()),
                eventClassNames: {!! htmlspecialchars($this->eventClassNames(), ENT_COMPAT) !!},
                eventContent: {!! htmlspecialchars($this->eventContent(), ENT_COMPAT) !!},
                eventDidMount: {!! htmlspecialchars($this->eventDidMount(), ENT_COMPAT) !!},
                eventWillUnmount: {!! htmlspecialchars($this->eventWillUnmount(), ENT_COMPAT) !!},
            })"
            class="filament-fullcalendar ff-grid"
        ></div>
        </div>

        @if ($listMode)
            <div class="ff-list">
                {{ $this->table }}
            </div>
        @endif
    </div>

    <x-filament-actions::modals />

    <style>
        .ff-cal { --ff-border: #E5E5E5; --ff-text: #171717; --ff-muted: #737373; --ff-faint: #A3A3A3; --ff-brand: #ED86A3; }

        .ff-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .ff-toolbar-left { display: flex; align-items: center; gap: 12px; }
        .ff-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .ff-btn-today { font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 18px; cursor: pointer; }
        .ff-btn-today:hover { background: #FAFAFA; }
        .ff-nav { display: flex; align-items: center; gap: 4px; }
        .ff-nav-btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-nav-btn:hover { background: #FAFAFA; color: var(--ff-text); }
        .ff-nav-btn svg { width: 18px; height: 18px; }
        .ff-title { font-size: 24px; font-weight: 700; color: var(--ff-text); margin: 0; line-height: 1.2; }

        .ff-views { display: flex; align-items: center; gap: 6px; }
        .ff-views button { font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 18px; cursor: pointer; }
        .ff-views button:hover { background: #FAFAFA; }
        .ff-views button.ff-view-active { background: var(--ff-brand); border-color: var(--ff-brand); color: #fff; }

        .ff-header-actions { display: inline-flex; }

        .ff-select-toggle { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 14px; cursor: pointer; }
        .ff-select-toggle:hover { background: #FAFAFA; }
        .ff-select-toggle svg { width: 16px; height: 16px; }
        .ff-select-toggle-active { background: var(--ff-brand); border-color: var(--ff-brand); color: #fff; }

        .ff-selectbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: #FFF1F4; border: 1px solid #F7C6D4; border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; }
        .ff-selectbar-count { font-size: 14px; font-weight: 700; color: var(--ff-text); }
        .ff-selectbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ff-selectbar-clear { margin-left: auto; font-size: 14px; color: var(--ff-muted); background: transparent; border: none; cursor: pointer; padding: 4px 8px; }
        .ff-selectbar-clear:hover:not(:disabled) { color: var(--ff-text); }
        .ff-selectbar-clear:disabled { opacity: .5; cursor: default; }

        .ff-filterbar { display: flex; flex-direction: column; gap: 12px; background: #fff; border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
        .ff-filter-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ff-filter-controls { gap: 10px; border-top: 1px solid var(--ff-border); padding-top: 12px; align-items: center; }
        .ff-filter-label { font-size: 14px; color: var(--ff-muted); }
        .ff-chip { display: inline-flex; align-items: center; gap: 8px; border: 1.5px solid transparent; border-radius: 999px; padding: 4px 12px 4px 4px; background: #fff; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--ff-text); transition: opacity .15s; }
        .ff-chip-avatar { width: 26px; height: 26px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .ff-chip-x { width: 15px; height: 15px; color: var(--ff-faint); }
        .ff-chip.is-muted { border-color: transparent; color: var(--ff-faint); }
        .ff-chip.is-muted .ff-chip-avatar { opacity: .5; }
        .ff-chip.is-muted:hover { color: var(--ff-muted); }
        .ff-all { font-size: 14px; color: var(--ff-muted); background: transparent; border: none; cursor: pointer; padding: 4px 8px; }
        .ff-all:hover { color: var(--ff-text); }
        .ff-count { margin-left: auto; font-size: 14px; color: var(--ff-faint); white-space: nowrap; }

        .ff-search { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 7px 12px; flex: 1 1 240px; min-width: 180px; }
        .ff-search svg { width: 16px; height: 16px; color: var(--ff-faint); flex-shrink: 0; }
        .ff-search input { border: none; background: transparent; outline: none; font-size: 14px; color: var(--ff-text); width: 100%; }
        .ff-search input::placeholder { color: var(--ff-faint); }

        .ff-filament-filters { width: 100%; }
        .ff-filters-toggle { display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0; font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 12px; cursor: pointer; }
        .ff-filters-toggle:hover { background: #FAFAFA; }
        .ff-filters-toggle svg { width: 16px; height: 16px; color: var(--ff-muted); }
        .ff-filters-toggle-open { border-color: var(--ff-brand); color: var(--ff-brand); }
        .ff-filters-toggle-open svg { color: var(--ff-brand); }
        .ff-filters-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--ff-brand); color: #fff; font-size: 11px; font-weight: 700; }
        .ff-filters-chevron { transition: transform .15s ease; }
        .ff-filters-chevron.ff-rotate { transform: rotate(180deg); }
        .ff-reset { width: 38px; height: 38px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-reset:hover { background: #FAFAFA; color: var(--ff-text); }
        .ff-reset svg { width: 17px; height: 17px; }
        .ff-cal .ff-list { margin-top: 4px; }
        [x-cloak] { display: none !important; }

        /* FullCalendar surface */
        .ff-cal .ff-grid { background: #fff; border: 1px solid var(--ff-border); border-radius: 12px; overflow: hidden; }
        .ff-cal .fc { font-family: inherit; }
        .ff-cal .fc-scrollgrid { border: none; }
        .ff-cal .fc-theme-standard td, .ff-cal .fc-theme-standard th { border-color: var(--ff-border); }
        .ff-cal .fc-scrollgrid-section > td { border: none; }

        .ff-cal .fc-col-header-cell { background: #fff; padding: 14px 0; }
        .ff-cal .fc-col-header-cell-cushion { text-transform: capitalize; color: var(--ff-muted); font-size: 14px; font-weight: 600; text-decoration: none; }
        .ff-cal .fc-day-today { background: #FFF1F4 !important; }
        .ff-cal .fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion { color: #D4678A; font-weight: 700; }
        /* Day view shows a single day, so the "today" highlight is redundant — render it as a regular day. */
        .ff-cal.ff-day-view .fc-day-today { background: transparent !important; }
        .ff-cal.ff-day-view .fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion { color: var(--ff-muted); font-weight: 600; }

        .ff-cal .fc-timegrid-slot { height: 1.4rem; }
        .ff-cal .fc-timegrid-slot-minor { border-top-color: #F5F5F5; }
        .ff-cal .fc-timegrid-slot-label-cushion, .ff-cal .fc-timegrid-axis-cushion { color: var(--ff-faint); font-size: 13px; }
        .ff-cal .fc-timegrid-now-indicator-line { border-color: #F43F5E; }
        .ff-cal .fc-timegrid-now-indicator-arrow { border-color: #F43F5E; background: #F43F5E; }

        /* Events */
        .ff-cal .fc-timegrid-event, .ff-cal .fc-v-event { box-shadow: none; border-radius: 10px; border-width: 1px; border-style: solid; padding: 0; }
        .ff-cal .fc-timegrid-event .fc-event-main { padding: 0; color: var(--ff-text); }
        .ff-cal .fc-event { cursor: pointer; }
        .ff-cal .fc-event.ff-cancelled { opacity: .6; }
        .ff-cal .fc-event.ff-cancelled .ff-event-title { text-decoration: line-through; }
        .ff-cal .fc-event.ff-trashed { opacity: .5; border-style: dashed; }
        .ff-cal .fc-event.ff-selected { outline: 2px solid var(--ff-brand); outline-offset: 1px; box-shadow: 0 0 0 4px rgba(237, 134, 163, .18); }

        .ff-event { display: flex; flex-direction: column; gap: 2px; height: 100%; padding: 7px 9px; overflow: hidden; }
        .ff-event-head { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
        .ff-event-time { font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
        .ff-event-tag { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
        .ff-event-title { font-size: 14px; font-weight: 600; color: var(--ff-text); line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ff-event-sub { font-size: 12px; color: var(--ff-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ff-event-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; padding-top: 4px; }
        .ff-event-avatar { width: 20px; height: 20px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 9px; font-weight: 700; }
        .ff-event-room { font-size: 11px; color: var(--ff-muted); white-space: nowrap; }
    </style>
</x-filament-widgets::widget>

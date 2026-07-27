@php
    use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
    use App\Support\Settings;

    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
    $hasSelection = filled($therapistIds);
    $isTemplate = $this->isTemplateMode();
    // Scoped staff (therapist/lecturer without admin rights) see the whole team's
    // grid, but only their own entries are theirs to open.
    $isScoped = $this->isScopedStaff();
    $currentUserId = auth()->id();
@endphp

<x-filament-widgets::widget>
    <div
        @class(['ff-cal', 'ff-cal-selecting' => $selectionMode])
        x-data="{
            title: '',
            cal: null,
            {{-- Phones and tablets stack the panel above the grid, where it costs a
                 screenful before the calendar starts, so it opens collapsed there. --}}
            sideOpen: window.innerWidth > 1024,
            wrapObserver: null,
            wlBadges: {},
            wlObserver: null,
            bind() {
                const el = this.$root.querySelector('.filament-fullcalendar');
                const data = (el && window.Alpine) ? window.Alpine.$data(el) : null;
                if (!data || !data.calendar) return false;
                this.cal = data.calendar;
                const sync = () => {
                    this.title = this.cal.view.title;
                    const d = this.cal.getDate();
                    this.$wire.calendarDate = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                };
                this.cal.on('datesSet', sync);
                this.syncView();
                sync();
                this.watchWrapWidth();
                this.cal.on('datesSet', () => this.renderWaitlistBadges());
                this.watchWaitlistData();
                return true;
            },
            watchWaitlistData() {
                const el = this.$refs.wlData;
                if (! el || this.wlObserver) return;
                const read = () => {
                    try { this.wlBadges = JSON.parse(el.dataset.badges || '{}') || {}; }
                    catch (e) { this.wlBadges = {}; }
                    this.renderWaitlistBadges();
                };
                this.wlObserver = new MutationObserver(read);
                this.wlObserver.observe(el, { attributes: true, attributeFilter: ['data-badges'] });
                read();
            },
            renderWaitlistBadges() {
                if (! this.cal) return;
                this.$root.querySelectorAll('.ff-wl-badge').forEach((el) => {
                    window.Alpine?.destroyTree(el);
                    el._tippy?.destroy();
                    el.remove();
                });
                this.$root.querySelectorAll('.fc-col-header-cell[data-date]').forEach((cell) => {
                    const info = this.wlBadges[cell.getAttribute('data-date')];
                    if (! info) return;
                    const a = document.createElement('a');
                    a.className = 'ff-wl-badge';
                    a.href = info.url;
                    a.setAttribute('x-tooltip', '{ content: ' + JSON.stringify(info.tooltip) + ', theme: $store.theme }');
                    a.innerHTML = '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'M6 2h12M6 22h12M8 2v4l4 4 4-4V2M8 22v-4l4-4 4 4v4\'></path></svg>';
                    const b = document.createElement('b');
                    b.textContent = info.count;
                    a.appendChild(b);
                    (cell.querySelector('.fc-scrollgrid-sync-inner') || cell).appendChild(a);
                    window.Alpine?.initTree(a);
                });
            },
            watchWrapWidth() {
                const wrap = this.$root.querySelector('.ff-cal-wrap');
                if (!wrap || this.wrapObserver) return;
                let lastWidth = -1;
                this.wrapObserver = new ResizeObserver(() => {
                    const width = wrap.clientWidth;
                    if (width === lastWidth) return;
                    lastWidth = width;
                    requestAnimationFrame(() => this.cal && this.cal.updateSize());
                });
                this.wrapObserver.observe(wrap);
            },
            destroy() {
                this.wrapObserver?.disconnect();
                this.wlObserver?.disconnect();
            },
            init() {
                let tries = 0;
                const tick = () => { if (this.bind() || tries++ > 120) return; setTimeout(tick, 50); };
                tick();
            },
            today() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--today')); },
            prev() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--prev')); },
            next() { window.dispatchEvent(new CustomEvent('filament-fullcalendar--next')); },
            goto(date) { if (this.cal) this.cal.gotoDate(date); },
            toggleSide() { this.sideOpen = ! this.sideOpen; },
            {{-- Seven columns are unreadable on a phone, so the grid drops to a
                 single day there and follows the viewport if it is resized. --}}
            syncView() {
                if (! this.cal) return;
                const want = window.innerWidth <= 768 ? 'timeGridDay' : 'timeGridWeek';
                if (this.cal.view.type !== want) this.cal.changeView(want);
            },
        }"
        @calendar-goto.window="goto($event.detail.date)"
        @resize.window.debounce.200ms="syncView()"
    >
        <div class="ff-toolbar">
            <div class="ff-toolbar-left">
                <div class="ff-mode">
                    <button type="button" wire:click="$set('mode', 'reservations')" :class="{ 'ff-mode-active': $wire.mode === 'reservations' }">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span>Rezervace</span>
                    </button>
                    <button type="button" wire:click="$set('mode', 'template')" :class="{ 'ff-mode-active': $wire.mode === 'template' }">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                        <span>Pracovní doba</span>
                    </button>
                </div>

                @if ($isTemplate)
                    @unless ($this->room ?? null)
                        <label class="ff-tsel">
                            <span>Místnost:</span>
                            <select wire:model.live="templateRoomId">
                                <option value="">Všechny</option>
                                @foreach ($this->roomOptions() as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endunless
                @endif
            </div>

            <div class="ff-toolbar-right">
                @if ($isTemplate)
                    <button type="button" class="ff-select-toggle" :class="{ 'ff-select-toggle-active': $wire.selectionMode }" wire:click="toggleSelectionMode" title="Hromadný výběr" aria-label="Hromadný výběr">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span>Vybrat</span>
                    </button>
                    {{ $this->deleteWorkBlocksRangeAction }}
                    {{ $this->addBlockingAction }}
                    {{ $this->addWorkingHoursAction }}
                @else
                    <a href="{{ ReservationResource::getUrl('index') }}" class="ff-seznam">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        <span>Seznam</span>
                    </a>
                    <button type="button" class="ff-select-toggle" :class="{ 'ff-select-toggle-active': $wire.selectionMode }" wire:click="toggleSelectionMode" title="Hromadný výběr rezervací" aria-label="Hromadný výběr rezervací">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span>Vybrat</span>
                    </button>
                    {{ $this->addOneTimeBlockingAction }}
                    <x-filament::actions :actions="$this->getCachedHeaderActions()" class="ff-header-actions" />
                @endif
            </div>
        </div>

        @if ($selectionMode)
            <div class="ff-selectbar">
                <span class="ff-selectbar-count">{{ count($selectedIds) }} vybráno</span>
                <div class="ff-selectbar-actions">
                    @if ($isTemplate)
                        {{ $this->deleteSelectedTemplateAction }}
                    @else
                        {{ $this->cancelSelectedAction }}
                        @if ($this->restoreSelectedAction->isVisible())
                            {{ $this->restoreSelectedAction }}
                        @endif
                    @endif
                </div>
                <button type="button" class="ff-selectbar-clear" wire:click="clearSelection" @disabled(count($selectedIds) === 0)>Zrušit výběr</button>
            </div>
        @endif

        {{-- The whole bar folds behind one toggle. Folded, it still shows every
             active filter as a removable chip, so a tidy bar never hides the fact
             that the grid is filtered; unfolded, it adds the unpicked options. --}}
        <div class="ff-filterbar">
            @php
                $pills = $this->activeFilterPills();
            @endphp
            <div class="ff-filter-head">
                <button
                    type="button"
                    class="ff-filters-toggle"
                    :class="{ 'ff-filters-toggle-open': $wire.showFilters }"
                    @click="$wire.showFilters = ! $wire.showFilters"
                    aria-label="Zobrazit nebo skrýt filtry"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                    <span>Filtry</span>
                    @if (count($pills) > 0)
                        <span class="ff-filters-badge">{{ count($pills) }}</span>
                    @endif
                    <svg class="ff-filters-chevron" :class="{ 'ff-rotate': $wire.showFilters }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div class="ff-active" x-show="! $wire.showFilters" x-cloak>
                    @foreach ($pills as $pill)
                        <button
                            type="button"
                            class="ff-pill"
                            wire:click="removeFilter('{{ $pill['type'] }}', @js($pill['key']), @js($pill['value']))"
                            @style(["border-color: {$pill['color']}" => filled($pill['color'])])
                            title="Zrušit filtr: {{ $pill['label'] }}"
                        >
                            @if (filled($pill['color']))
                                <span class="ff-pill-dot" style="background: {{ $pill['color'] }}"></span>
                            @endif
                            <span>{{ $pill['label'] }}</span>
                            <svg class="ff-pill-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    @endforeach

                    @if ($pills === [])
                        <span class="ff-active-empty">Bez filtrů — celý tým a všechny termíny</span>
                    @endif
                </div>

                @unless ($isTemplate)
                    <span class="ff-count">{{ $this->weekCountLabel() }}</span>
                @endunless

                @if ($this->hasActiveFilters())
                    <button type="button" class="ff-reset" wire:click="resetFilters" title="Zrušit všechny filtry" aria-label="Zrušit všechny filtry">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    </button>
                @endif
            </div>

            <div class="ff-filter-body" x-show="$wire.showFilters" x-cloak>
                <div class="ff-filter-row">
                    <span class="ff-filter-label">Terapeuté:</span>

                    @foreach ($this->therapists() as $therapist)
                        @php
                            $id = $therapist->getKey();
                            $inFilter = in_array($id, $therapistIds, true);
                            $isOn = ! $hasSelection || $inFilter;
                            $accent = $this->therapistColor($id);
                            $isMe = $therapist->user_id === $currentUserId;
                        @endphp
                        <button
                            type="button"
                            wire:click="toggleTherapist('{{ $id }}')"
                            @class(['ff-chip', 'is-muted' => ! $isOn, 'is-me' => $isMe])
                            @style(["border-color: {$accent}" => $isOn])
                            title="{{ $therapist->user?->name }}"
                        >
                            <span class="ff-chip-avatar" style="background: {{ $accent }}">{{ $this->therapistInitials($therapist->user?->name) }}</span>
                            <span>{{ $isMe ? 'Já' : $this->therapistChipName($therapist->user?->name) }}</span>
                            @if ($inFilter)
                                <svg class="ff-chip-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            @endif
                        </button>
                    @endforeach

                    <button type="button" class="ff-all" wire:click="clearTherapists">Všichni</button>

                    @if ($isScoped)
                        <span class="ff-readonly-hint" title="Kalendář ukazuje celý tým. Otevřít a upravit můžete jen své vlastní termíny a svou pracovní dobu.">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <span>Termíny kolegů jsou jen pro čtení</span>
                        </span>
                    @endif

                    <span class="ff-legend">
                        <span class="ff-leg"><span class="ff-leg-sw" style="background:#EEF2FF;border-color:#6366F1"></span>Blokace</span>
                        @unless ($isTemplate)
                            <span class="ff-leg" title="Pauza terapeuta po termínu"><span class="ff-leg-sw" style="background:#F5F5F5;border-color:#A3A3A3"></span>Pauza</span>
                        @endunless
                        @unless ($isTemplate)
                            <label class="ff-leg ff-leg-toggle" title="Zobrazit či skrýt terapie v kalendáři">
                                <input type="checkbox" wire:model.live="showReservations">
                                <span class="ff-leg-sw" style="background:#FFF1F4;border-color:#ED86A3"></span>
                                <span>Terapie</span>
                            </label>
                        @endunless
                        <label class="ff-leg ff-leg-toggle" title="Zobrazit či skrýt lekce kurzů v kalendáři">
                            <input type="checkbox" wire:model.live="showCourses">
                            <span class="ff-leg-sw" style="background:#ECFEFF;border-color:#0891B2"></span>
                            <span>Kurzy</span>
                        </label>
                        <label class="ff-leg ff-leg-toggle" title="Zobrazit či skrýt jednorázové akce v kalendáři">
                            <input type="checkbox" wire:model.live="showLessons">
                            <span class="ff-leg-sw" style="background:#FDF4FF;border-color:#C026D3"></span>
                            <span>Akce</span>
                        </label>
                        @unless ($isTemplate)
                            @if (! $this->room && Settings::dayWaitlistEnabled())
                                <label class="ff-leg ff-leg-toggle" title="Zobrazit či skrýt pořadník na dny">
                                    <input type="checkbox" wire:model.live="showWaitlist">
                                    <span class="ff-leg-sw" style="background:#FFFBEB;border-color:#D97706"></span>
                                    <span>Pořadník</span>
                                </label>
                            @endif
                        @endunless
                    </span>
                </div>

                @unless ($isTemplate)
                    <div class="ff-filter-row ff-filter-controls">
                        <div class="ff-search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Hledat klienta nebo službu…">
                        </div>
                    </div>

                    <div class="ff-filter-row ff-filament-filters">
                        {{ $this->filtersForm }}
                    </div>
                @endunless
            </div>
        </div>

        {{-- The panel toggles client side so its default can follow the viewport
             (collapsed on phones) instead of one server-rendered state for all. --}}
        <div class="ff-body">
            <button type="button" class="ff-side-expand" x-show="! sideOpen" x-cloak @click="toggleSide()" title="Zobrazit kalendář">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                <span>Zobrazit kalendář</span>
            </button>

            <aside class="ff-side" x-show="sideOpen" x-cloak>
                <div class="ff-side-top">
                    <button type="button" class="ff-side-collapse" @click="toggleSide()" title="Skrýt kalendář">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <span>Skrýt kalendář</span>
                    </button>
                </div>

                <div class="ff-mini">
                    <div class="ff-mini-head">
                        <span class="ff-mini-month">{{ $this->sidebarMonthLabel() }}</span>
                        <div class="ff-mini-nav">
                            <button type="button" wire:click="sidebarPrevMonth" aria-label="Předchozí měsíc">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </button>
                            <button type="button" wire:click="sidebarNextMonth" aria-label="Další měsíc">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                        </div>
                    </div>
                    <div class="ff-mini-dow">
                        @foreach (['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'] as $dow)
                            <span>{{ $dow }}</span>
                        @endforeach
                    </div>
                    <div class="ff-mini-grid">
                        @foreach ($this->sidebarMonthGrid() as $week)
                            <div class="ff-mini-week">
                                @foreach ($week as $day)
                                    <button
                                        type="button"
                                        wire:click="goToDate('{{ $day['date'] }}')"
                                        @class([
                                            'ff-mini-day',
                                            'is-out' => ! $day['inMonth'],
                                            'is-today' => $day['isToday'],
                                            'is-selected' => $day['isSelected'],
                                        ])
                                    >{{ $day['day'] }}</button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                @php($summary = $this->daySummary())
                <div class="ff-day">
                    <div class="ff-day-head">
                        <span class="ff-day-title">{{ $this->selectedDate()->isToday() ? 'Dnes' : 'Vybraný den' }}</span>
                        <span class="ff-day-sub">{{ $summary['label'] }}</span>
                    </div>
                    <div class="ff-day-stats">
                        <div class="ff-stat"><span class="ff-stat-label">TERMÍNY</span><span class="ff-stat-value">{{ $summary['count'] }}</span></div>
                        <div class="ff-stat"><span class="ff-stat-label">HODIN</span><span class="ff-stat-value">{{ $summary['hours'] }}</span></div>
                        <div class="ff-stat"><span class="ff-stat-label">VOLNO</span><span class="ff-stat-value ff-stat-sm">{{ $summary['free'] }}</span></div>
                        <div class="ff-stat"><span class="ff-stat-label">VYTÍŽENOST</span><span class="ff-stat-value ff-stat-sm ff-stat-util">{{ $summary['utilization'] }}%</span></div>
                    </div>
                    <div class="ff-day-track"><div class="ff-day-fill" style="width: {{ min(100, $summary['utilization']) }}%"></div></div>
                    <div class="ff-day-caption">Vytíženost dne</div>
                </div>
            </aside>

            <div class="ff-cal-wrap">
                {{-- Dnes / ‹ › / the date they move sit inside the calendar column
                     rather than up in the toolbar: on a phone the toolbar wraps over
                     several rows and the filter bar follows it, which left the date
                     navigation a long way from the grid it drives. --}}
                <div class="ff-datebar">
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

                <div x-ref="wlData" data-badges="{{ json_encode($this->waitlistHeaderBadges()) }}" hidden></div>
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
        </div>
    </div>

    <x-filament-actions::modals />

    <style>
        .ff-cal { --ff-border: #E5E5E5; --ff-text: #171717; --ff-muted: #737373; --ff-faint: #A3A3A3; --ff-brand: #ED86A3; --ff-panel: #fff; --ff-hover: #FAFAFA; }

        .ff-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .ff-toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ff-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ff-datebar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }

        /* Mode toggle (Rezervace / Šablona týdne) */
        .ff-mode { display: inline-flex; align-items: center; gap: 2px; background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 3px; }
        .ff-mode button { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: var(--ff-muted); background: transparent; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; }
        .ff-mode button svg { width: 14px; height: 14px; }
        .ff-mode button:hover { color: var(--ff-text); }
        .ff-mode .ff-mode-active { background: var(--ff-brand); color: #fff; font-weight: 600; }

        /* Template selects */
        .ff-tsel { display: inline-flex; align-items: center; gap: 8px; background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 8px; padding: 5px 10px; font-size: 12px; color: var(--ff-muted); }
        .ff-tsel select { border: none; background: transparent; outline: none; font-size: 13px; font-weight: 600; color: var(--ff-text); cursor: pointer; }

        .ff-btn-today { font-size: 14px; font-weight: 500; color: var(--ff-text); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 18px; cursor: pointer; }
        .ff-btn-today:hover { background: var(--ff-hover); }
        .ff-nav { display: flex; align-items: center; gap: 4px; }
        .ff-nav-btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-nav-btn:hover { background: var(--ff-hover); color: var(--ff-text); }
        .ff-nav-btn svg { width: 18px; height: 18px; }
        .ff-title { font-size: 22px; font-weight: 700; color: var(--ff-text); margin: 0; line-height: 1.2; }

        .ff-header-actions { display: inline-flex; }
        .ff-seznam { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 500; color: var(--ff-text); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 14px; cursor: pointer; text-decoration: none; }
        .ff-seznam:hover { background: var(--ff-hover); }
        .ff-seznam svg { width: 16px; height: 16px; color: var(--ff-muted); }

        .ff-select-toggle { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 500; color: var(--ff-text); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 14px; cursor: pointer; }
        .ff-select-toggle:hover { background: var(--ff-hover); }
        .ff-select-toggle svg { width: 16px; height: 16px; }
        .ff-select-toggle-active { background: var(--ff-brand); border-color: var(--ff-brand); color: #fff; }

        .ff-selectbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: #FFF1F4; border: 1px solid #F7C6D4; border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; }
        .ff-selectbar-count { font-size: 14px; font-weight: 700; color: var(--ff-text); }
        .ff-selectbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ff-selectbar-clear { margin-left: auto; font-size: 14px; color: var(--ff-muted); background: transparent; border: none; cursor: pointer; padding: 4px 8px; }
        .ff-selectbar-clear:hover:not(:disabled) { color: var(--ff-text); }
        .ff-selectbar-clear:disabled { opacity: .5; cursor: default; }

        .ff-filterbar { display: flex; flex-direction: column; gap: 12px; background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
        .ff-filter-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ff-filter-body { display: flex; flex-direction: column; gap: 12px; border-top: 1px solid var(--ff-border); padding-top: 12px; }
        .ff-filter-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ff-filter-controls { gap: 10px; align-items: center; }

        /* Collapsed summary: one removable chip per active filter. */
        .ff-active { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0; flex: 1 1 auto; }
        .ff-active-empty { font-size: 13px; color: var(--ff-faint); }
        .ff-pill { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; border: 1px solid var(--ff-border); border-radius: 999px; padding: 3px 8px 3px 10px; background: var(--ff-panel); cursor: pointer; font-size: 13px; font-weight: 500; color: var(--ff-text); }
        .ff-pill:hover { background: var(--ff-hover); }
        .ff-pill > span:not(.ff-pill-dot) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ff-pill-dot { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; }
        .ff-pill-x { width: 13px; height: 13px; flex-shrink: 0; color: var(--ff-faint); }
        .ff-pill:hover .ff-pill-x { color: var(--ff-text); }
        .ff-filter-label { font-size: 14px; color: var(--ff-muted); }
        .ff-chip { display: inline-flex; align-items: center; gap: 8px; border: 1.5px solid transparent; border-radius: 999px; padding: 4px 12px 4px 4px; background: var(--ff-panel); cursor: pointer; font-size: 14px; font-weight: 500; color: var(--ff-text); transition: opacity .15s; }
        .ff-chip-avatar { width: 26px; height: 26px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .ff-chip-x { width: 15px; height: 15px; color: var(--ff-faint); }
        .ff-chip.is-muted { border-color: transparent; color: var(--ff-faint); }
        .ff-chip.is-muted .ff-chip-avatar { opacity: .5; }
        .ff-chip.is-muted:hover { color: var(--ff-muted); }
        .ff-chip.is-me { font-weight: 700; }
        .ff-readonly-hint { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ff-faint); cursor: default; }
        .ff-readonly-hint svg { width: 13px; height: 13px; }
        .ff-all { font-size: 14px; color: var(--ff-muted); background: transparent; border: none; cursor: pointer; padding: 4px 8px; }
        .ff-all:hover { color: var(--ff-text); }
        /* Wraps rather than running off the row: on a phone the legend is far wider
           than the filter bar, and it has to shrink (min-width) before it can wrap. */
        .ff-legend { margin-left: auto; min-width: 0; display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
        .ff-leg { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ff-muted); }
        .ff-leg-sw { width: 14px; height: 14px; border-radius: 3px; border: 1px solid; }
        .ff-count { font-size: 14px; color: var(--ff-faint); white-space: nowrap; }
        .ff-filter-head .ff-count { margin-left: auto; }

        .ff-search { display: flex; align-items: center; gap: 8px; background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 7px 12px; flex: 1 1 240px; min-width: 180px; }
        .ff-search svg { width: 16px; height: 16px; color: var(--ff-faint); flex-shrink: 0; }
        .ff-search input { border: none; background: transparent; outline: none; font-size: 14px; color: var(--ff-text); width: 100%; }
        .ff-search input::placeholder { color: var(--ff-faint); }

        /* Block, not the row's flex: as a flex item the schema grid was sized
           shrink-to-fit, so it never used more than a fraction of the bar and the
           selects stacked with the rest of the row left empty. */
        .ff-filament-filters { display: block; width: 100%; }
        /* Filament's sm:/lg: column counts are viewport media queries, which say
           nothing about how wide this bar actually is. auto-fit measures the
           container: the five short selects sit on one row when they fit and wrap
           to two when they do not, with no dead column. */
        .ff-filament-filters .fi-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important; gap: 8px 12px !important; }
        .ff-filament-filters .fi-fo-field { row-gap: 4px; }
        .ff-filament-filters .fi-fo-field-label-col { padding-top: 0; }
        .ff-filament-filters .fi-fo-field-label-content { font-size: 13px; color: var(--ff-muted); }
        .ff-filters-toggle { display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0; font-size: 14px; font-weight: 500; color: var(--ff-text); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 12px; cursor: pointer; }
        .ff-filters-toggle:hover { background: var(--ff-hover); }
        .ff-filters-toggle svg { width: 16px; height: 16px; color: var(--ff-muted); }
        .ff-filters-toggle-open { border-color: var(--ff-brand); color: var(--ff-brand); }
        .ff-filters-toggle-open svg { color: var(--ff-brand); }
        .ff-filters-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--ff-brand); color: #fff; font-size: 11px; font-weight: 700; }
        .ff-filters-chevron { transition: transform .15s ease; }
        .ff-filters-chevron.ff-rotate { transform: rotate(180deg); }
        .ff-reset { width: 38px; height: 38px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-reset:hover { background: var(--ff-hover); color: var(--ff-text); }
        .ff-reset svg { width: 17px; height: 17px; }
        [x-cloak] { display: none !important; }

        /* Body: sidebar + calendar */
        .ff-body { display: flex; align-items: stretch; gap: 16px; }
        .ff-cal-wrap { flex: 1 1 auto; min-width: 0; }
        .ff-side { flex: 0 0 288px; width: 288px; display: flex; flex-direction: column; gap: 10px; }
        .ff-side-top { display: flex; justify-content: flex-end; }
        .ff-side-collapse, .ff-side-expand { height: 32px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 0 10px; font-size: 13px; font-weight: 500; color: var(--ff-muted); background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; white-space: nowrap; }
        .ff-side-collapse:hover, .ff-side-expand:hover { background: var(--ff-hover); color: var(--ff-text); }
        .ff-side-collapse svg, .ff-side-expand svg { width: 16px; height: 16px; flex-shrink: 0; }
        .ff-side-expand { flex: 0 0 auto; align-self: flex-start; }

        .ff-mini { background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px; }
        .ff-mini-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .ff-mini-month { font-size: 14px; font-weight: 600; color: var(--ff-text); }
        .ff-mini-nav { display: flex; gap: 2px; }
        .ff-mini-nav button { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: transparent; border: none; border-radius: 6px; cursor: pointer; }
        .ff-mini-nav button:hover { background: var(--ff-hover); color: var(--ff-text); }
        .ff-mini-nav svg { width: 15px; height: 15px; }
        .ff-mini-dow { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px; }
        .ff-mini-dow span { text-align: center; font-size: 10px; font-weight: 600; color: var(--ff-faint); padding: 2px 0; }
        .ff-mini-grid { display: flex; flex-direction: column; gap: 2px; }
        .ff-mini-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
        .ff-mini-day { aspect-ratio: 1; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 500; color: var(--ff-text); background: transparent; border: none; border-radius: 7px; cursor: pointer; }
        .ff-mini-day:hover { background: var(--ff-hover); }
        .ff-mini-day.is-out { color: var(--ff-faint); opacity: .55; }
        .ff-mini-day.is-today { color: var(--ff-brand); font-weight: 700; }
        .ff-mini-day.is-selected { background: var(--ff-brand); color: #fff; font-weight: 700; }

        .ff-day { background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
        .ff-day-head { display: flex; align-items: center; justify-content: space-between; }
        .ff-day-title { font-size: 13px; font-weight: 600; color: var(--ff-text); }
        .ff-day-sub { font-size: 11px; font-weight: 500; color: var(--ff-faint); }
        .ff-day-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .ff-stat { display: flex; flex-direction: column; gap: 1px; background: var(--ff-hover); border-radius: 8px; padding: 6px 8px; }
        .ff-stat-label { font-size: 10px; font-weight: 500; color: var(--ff-muted); }
        .ff-stat-value { font-size: 18px; font-weight: 700; color: var(--ff-text); }
        .ff-stat-value.ff-stat-sm { font-size: 14px; }
        .ff-stat-value.ff-stat-util { color: #16A34A; }
        .ff-day-track { height: 6px; border-radius: 999px; background: var(--ff-border); overflow: hidden; }
        .ff-day-fill { height: 6px; border-radius: 999px; background: var(--ff-brand); }
        .ff-day-caption { font-size: 10px; font-weight: 500; color: var(--ff-faint); }

        /* FullCalendar surface */
        .ff-cal .ff-grid { background: var(--ff-panel); border: 1px solid var(--ff-border); border-radius: 12px; overflow: hidden; }
        .ff-cal .fc { font-family: inherit; }
        .ff-cal .fc-scrollgrid { border: none; }
        .ff-cal .fc-theme-standard td, .ff-cal .fc-theme-standard th { border-color: var(--ff-border); }
        .ff-cal .fc-scrollgrid-section > td { border: none; }

        .ff-cal .fc-col-header-cell { background: var(--ff-panel); padding: 10px 0; }
        .ff-cal .fc-col-header-cell-cushion { text-transform: capitalize; color: var(--ff-muted); font-size: 14px; font-weight: 600; text-decoration: none; }
        .ff-cal .fc-day-today { background: #FFF1F4 !important; }
        .ff-cal .fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion { color: #D4678A; font-weight: 700; }
        .ff-cal .fc-col-header-cell .fc-scrollgrid-sync-inner { display: flex; flex-direction: column; align-items: center; gap: 3px; }
        .ff-wl-badge { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border: 1px solid #FCD34D; border-radius: 999px; background: #FFFBEB; color: #92400E; font-size: 11px; line-height: 1.5; text-decoration: none; cursor: pointer; transition: background .15s; }
        .ff-wl-badge svg { width: 11px; height: 11px; }
        .ff-wl-badge b { font-weight: 700; }
        .ff-wl-badge:hover { background: #FEF3C7; }

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
        .ff-cal .fc-event.ff-blocking { border-style: dashed; }
        .ff-cal .fc-event.ff-course .ff-event-title { color: #155E75; }
        .ff-cal .fc-event.ff-oneoff .ff-event-title { color: #86198F; }
        .ff-leg-toggle { cursor: pointer; gap: 5px; }
        .ff-leg-toggle input { width: 13px; height: 13px; accent-color: var(--ff-brand); cursor: pointer; }
        .ff-cal .fc-event.ff-selected { outline: 2px solid var(--ff-brand); outline-offset: 1px; box-shadow: 0 0 0 4px rgba(237, 134, 163, .18); }
        /* Courses and one-off events cannot be bulk-selected, so they read as inert while selecting. */
        .ff-cal-selecting .fc-event.ff-course, .ff-cal-selecting .fc-event.ff-oneoff { cursor: not-allowed; }
        /* A colleague's work: on the grid for context, not to be touched. Faded so
           the viewer's own cards read first, and inert to clicks. Declared after the
           cancelled/trashed rules so its opacity wins on a colleague's storno. */
        .ff-cal .fc-event.ff-foreign { opacity: .32; cursor: default; }
        .ff-cal .fc-event.ff-foreign:hover { opacity: .62; }
        .ff-cal-selecting .fc-event.ff-foreign { cursor: not-allowed; }

        .ff-event { display: flex; flex-direction: column; gap: 2px; height: 100%; padding: 7px 9px; overflow: hidden; }
        .ff-event-block .ff-event-time { color: #6366F1; }
        .ff-event-block .ff-event-title { color: #3730A3; }
        .ff-event-head { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
        .ff-event-time { font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
        .ff-event-tag { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
        .ff-event-title { font-size: 14px; font-weight: 600; color: var(--ff-text); line-height: 1.25; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden; text-overflow: ellipsis; overflow-wrap: anywhere; }
        .ff-event-sub { font-size: 12px; color: var(--ff-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ff-event-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; padding-top: 4px; }
        .ff-event-avatar { width: 20px; height: 20px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 9px; font-weight: 700; }
        .ff-event-room { font-size: 11px; color: var(--ff-muted); white-space: nowrap; }
        .ff-event-recur { font-size: 11px; font-weight: 700; color: var(--ff-muted); flex-shrink: 0; }

        /* The break hangs off the bottom of its own card, so the two are always
           exactly the same width. The card gives up its bottom radius to meet it
           flush; the strip carries the rounding instead. Its `left/right: -1px`
           covers the card's borders, and no z-index is set on purpose — a later
           event in the column paints over it, which is what should happen when
           something really is booked inside the break. */
        .ff-cal .fc-event.ff-has-break {
            overflow: visible;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }
        .ff-event-break {
            position: absolute;
            top: 100%;
            left: -1px;
            right: -1px;
            display: flex;
            align-items: center;
            padding: 0 9px;
            box-sizing: border-box;
            background: #F5F5F5;
            border: 1px solid #A3A3A3;
            border-top: 0;
            border-radius: 0 0 10px 10px;
            color: #737373;
            overflow: hidden;
        }
        .ff-event-break-label { font-size: 10px; font-weight: 600; letter-spacing: .02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* A month cell is a list of bookings; a break has no height there to hang in. */
        .ff-cal .fc-daygrid-event .ff-event-break { display: none; }

        /* Dark mode (Filament toggles the `dark` class on <html>) */
        .dark .ff-cal {
            --ff-border: #3F3F46;
            --ff-text: #F5F5F5;
            --ff-muted: #A3A3A3;
            --ff-faint: #737373;
            --ff-panel: #18181B;
            --ff-hover: #27272A;
            color-scheme: dark;
        }
        .dark .ff-cal .fc { --fc-border-color: #3F3F46; --fc-page-bg-color: transparent; --fc-neutral-bg-color: transparent; }
        .dark .ff-selectbar { background: rgba(237, 134, 163, .12); border-color: rgba(237, 134, 163, .35); }
        .dark .ff-cal .fc-day-today { background: rgba(237, 134, 163, .08) !important; }
        .dark .ff-cal .fc-timegrid-slot-minor { border-top-color: #27272A; }
        .dark .ff-stat-value.ff-stat-util { color: #4ADE80; }
        .dark .ff-wl-badge { background: rgba(217, 119, 6, .16); border-color: rgba(217, 119, 6, .45); color: #FBBF24; }
        .dark .ff-wl-badge:hover { background: rgba(217, 119, 6, .28); }
        /* Event cards keep their light tint backgrounds (inline per-therapist colors),
           so their inner text stays pinned to dark neutrals instead of --ff-text. */
        .dark .ff-cal .fc-timegrid-event .fc-event-main { color: #171717; }
        .dark .ff-cal .fc-event .ff-event-title { color: #171717; }
        .dark .ff-cal .fc-event .ff-event-sub,
        .dark .ff-cal .fc-event .ff-event-room,
        .dark .ff-cal .fc-event .ff-event-recur { color: #525252; }
        .dark .ff-cal .fc-event .ff-event-break { color: #737373; }

        @media (max-width: 1024px) {
            .ff-body { flex-direction: column; }
            .ff-side { width: 100%; flex-basis: auto; }
        }

        /* Phones: the day stats are a desktop-sidebar luxury — on a narrow screen
           they only push the grid further down. */
        @media (max-width: 640px) {
            .ff-day { display: none; }
            /* Nothing to push against once the row wraps. */
            .ff-legend { margin-left: 0; }
        }
    </style>
</x-filament-widgets::widget>

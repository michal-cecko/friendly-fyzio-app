@php
    use App\Filament\Resources\Reservations\ReservationResource;

    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
    $hasSelection = filled($therapistIds);
    $isTemplate = $this->isTemplateMode();
@endphp

<x-filament-widgets::widget>
    <div
        class="ff-cal"
        x-data="{
            title: '',
            cal: null,
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
            goto(date) { if (this.cal) this.cal.gotoDate(date); },
        }"
        @calendar-goto.window="goto($event.detail.date)"
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
                        <span>Šablona týdne</span>
                    </button>
                </div>

                @unless ($isTemplate)
                    <div class="ff-sep"></div>
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
                @else
                    <div class="ff-sep"></div>
                    <label class="ff-tsel">
                        <span>Typ týdne:</span>
                        <select wire:model.live="templateWeekType">
                            <option value="all">Vše</option>
                            <option value="odd">Lichý (A)</option>
                            <option value="even">Sudý (B)</option>
                        </select>
                    </label>
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
                @endunless
            </div>

            <div class="ff-toolbar-right">
                @if ($isTemplate)
                    <button type="button" class="ff-select-toggle" :class="{ 'ff-select-toggle-active': $wire.selectionMode }" wire:click="toggleSelectionMode" title="Hromadný výběr" aria-label="Hromadný výběr">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span>Vybrat</span>
                    </button>
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
                        {{ $this->deleteSelectedAction }}
                        @if ($this->restoreSelectedAction->isVisible())
                            {{ $this->restoreSelectedAction }}
                        @endif
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

                <span class="ff-legend">
                    @if ($isTemplate)
                        <span class="ff-leg"><span class="ff-leg-sw" style="background:#EEF2FF;border-color:#6366F1"></span>Blokace</span>
                    @else
                        <span class="ff-leg"><span class="ff-leg-sw" style="background:#EEF2FF;border-color:#6366F1"></span>Blokace</span>
                        <span class="ff-count">{{ $this->weekCountLabel() }}</span>
                    @endif
                </span>
            </div>

            @unless ($isTemplate)
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
            @endunless
        </div>

        <div class="ff-body">
            @unless ($isTemplate)
                @if ($sidebarCollapsed)
                    <button type="button" class="ff-side-expand" wire:click="toggleSidebar" title="Zobrazit panel" aria-label="Zobrazit panel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                @else
                    <aside class="ff-side">
                        <div class="ff-side-top">
                            <button type="button" class="ff-side-collapse" wire:click="toggleSidebar" title="Skrýt panel" aria-label="Skrýt panel">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
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
                @endif
            @endunless

            <div class="ff-cal-wrap" x-effect="if (cal) $nextTick(() => cal.updateSize())">
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
        .ff-cal { --ff-border: #E5E5E5; --ff-text: #171717; --ff-muted: #737373; --ff-faint: #A3A3A3; --ff-brand: #ED86A3; }

        .ff-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .ff-toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ff-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ff-sep { width: 1px; height: 24px; background: var(--ff-border); }

        /* Mode toggle (Rezervace / Šablona týdne) */
        .ff-mode { display: inline-flex; align-items: center; gap: 2px; background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 3px; }
        .ff-mode button { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: var(--ff-muted); background: transparent; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; }
        .ff-mode button svg { width: 14px; height: 14px; }
        .ff-mode button:hover { color: var(--ff-text); }
        .ff-mode .ff-mode-active { background: var(--ff-brand); color: #fff; font-weight: 600; }

        /* Template selects */
        .ff-tsel { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--ff-border); border-radius: 8px; padding: 5px 10px; font-size: 12px; color: var(--ff-muted); }
        .ff-tsel select { border: none; background: transparent; outline: none; font-size: 13px; font-weight: 600; color: var(--ff-text); cursor: pointer; }

        .ff-btn-today { font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 18px; cursor: pointer; }
        .ff-btn-today:hover { background: #FAFAFA; }
        .ff-nav { display: flex; align-items: center; gap: 4px; }
        .ff-nav-btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-nav-btn:hover { background: #FAFAFA; color: var(--ff-text); }
        .ff-nav-btn svg { width: 18px; height: 18px; }
        .ff-title { font-size: 22px; font-weight: 700; color: var(--ff-text); margin: 0; line-height: 1.2; }

        .ff-header-actions { display: inline-flex; }
        .ff-seznam { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 500; color: var(--ff-text); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; padding: 8px 14px; cursor: pointer; text-decoration: none; }
        .ff-seznam:hover { background: #FAFAFA; }
        .ff-seznam svg { width: 16px; height: 16px; color: var(--ff-muted); }

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
        .ff-legend { margin-left: auto; display: inline-flex; align-items: center; gap: 12px; }
        .ff-leg { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ff-muted); }
        .ff-leg-sw { width: 14px; height: 14px; border-radius: 3px; border: 1px solid; }
        .ff-count { font-size: 14px; color: var(--ff-faint); white-space: nowrap; }

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
        [x-cloak] { display: none !important; }

        /* Body: sidebar + calendar */
        .ff-body { display: flex; align-items: stretch; gap: 16px; }
        .ff-cal-wrap { flex: 1 1 auto; min-width: 0; }
        .ff-side { flex: 0 0 288px; width: 288px; display: flex; flex-direction: column; gap: 10px; }
        .ff-side-top { display: flex; justify-content: flex-end; }
        .ff-side-collapse, .ff-side-expand { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: #fff; border: 1px solid var(--ff-border); border-radius: 10px; cursor: pointer; }
        .ff-side-collapse:hover, .ff-side-expand:hover { background: #FAFAFA; color: var(--ff-text); }
        .ff-side-collapse svg, .ff-side-expand svg { width: 16px; height: 16px; }
        .ff-side-expand { flex: 0 0 32px; align-self: flex-start; }

        .ff-mini { background: #fff; border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px; }
        .ff-mini-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .ff-mini-month { font-size: 14px; font-weight: 600; color: var(--ff-text); }
        .ff-mini-nav { display: flex; gap: 2px; }
        .ff-mini-nav button { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; color: var(--ff-muted); background: transparent; border: none; border-radius: 6px; cursor: pointer; }
        .ff-mini-nav button:hover { background: #F5F5F5; color: var(--ff-text); }
        .ff-mini-nav svg { width: 15px; height: 15px; }
        .ff-mini-dow { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px; }
        .ff-mini-dow span { text-align: center; font-size: 10px; font-weight: 600; color: var(--ff-faint); padding: 2px 0; }
        .ff-mini-grid { display: flex; flex-direction: column; gap: 2px; }
        .ff-mini-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
        .ff-mini-day { aspect-ratio: 1; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 500; color: var(--ff-text); background: transparent; border: none; border-radius: 7px; cursor: pointer; }
        .ff-mini-day:hover { background: #F5F5F5; }
        .ff-mini-day.is-out { color: var(--ff-faint); opacity: .55; }
        .ff-mini-day.is-today { color: var(--ff-brand); font-weight: 700; }
        .ff-mini-day.is-selected { background: var(--ff-brand); color: #fff; font-weight: 700; }

        .ff-day { background: #fff; border: 1px solid var(--ff-border); border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
        .ff-day-head { display: flex; align-items: center; justify-content: space-between; }
        .ff-day-title { font-size: 13px; font-weight: 600; color: var(--ff-text); }
        .ff-day-sub { font-size: 11px; font-weight: 500; color: var(--ff-faint); }
        .ff-day-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .ff-stat { display: flex; flex-direction: column; gap: 1px; background: #FAFAFA; border-radius: 8px; padding: 6px 8px; }
        .ff-stat-label { font-size: 10px; font-weight: 500; color: var(--ff-muted); }
        .ff-stat-value { font-size: 18px; font-weight: 700; color: var(--ff-text); }
        .ff-stat-value.ff-stat-sm { font-size: 14px; }
        .ff-stat-value.ff-stat-util { color: #16A34A; }
        .ff-day-track { height: 6px; border-radius: 999px; background: #E5E5E5; overflow: hidden; }
        .ff-day-fill { height: 6px; border-radius: 999px; background: var(--ff-brand); }
        .ff-day-caption { font-size: 10px; font-weight: 500; color: var(--ff-faint); }

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
        .ff-cal .fc-event.ff-selected { outline: 2px solid var(--ff-brand); outline-offset: 1px; box-shadow: 0 0 0 4px rgba(237, 134, 163, .18); }

        .ff-event { display: flex; flex-direction: column; gap: 2px; height: 100%; padding: 7px 9px; overflow: hidden; }
        .ff-event-block .ff-event-time { color: #6366F1; }
        .ff-event-block .ff-event-title { color: #3730A3; }
        .ff-event-head { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
        .ff-event-time { font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
        .ff-event-tag { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
        .ff-event-title { font-size: 14px; font-weight: 600; color: var(--ff-text); line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ff-event-sub { font-size: 12px; color: var(--ff-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ff-event-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; padding-top: 4px; }
        .ff-event-avatar { width: 20px; height: 20px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 9px; font-weight: 700; }
        .ff-event-room { font-size: 11px; color: var(--ff-muted); white-space: nowrap; }

        @media (max-width: 1024px) {
            .ff-body { flex-direction: column; }
            .ff-side { width: 100%; flex-basis: auto; }
        }
    </style>
</x-filament-widgets::widget>

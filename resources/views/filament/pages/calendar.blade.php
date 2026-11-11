<x-filament-panels::page>
    <div
        x-data="adminCalendar()"
        x-init="init()"
        x-on:calendar-refresh.window="calendar && calendar.refetchEvents()"
        class="cal-wrap"
    >
        <div wire:ignore x-ref="calendar"></div>
        <p class="cal-hint">Click or drag on a day to add an event · drag events to move them · drag the bottom edge to change length · 📝 = scheduled blog post</p>
    </div>

    <style>
        .cal-wrap { border-radius: .75rem; padding: 1rem; border: 1px solid rgb(0 0 0 / .08); background: #fff; }
        .dark .cal-wrap { background: #18181b; border-color: rgb(255 255 255 / .1); }
        .cal-hint { margin-top: .75rem; font-size: .75rem; opacity: .6; }
        .cal-wrap .fc { --fc-border-color: rgb(127 127 127 / .2); --fc-today-bg-color: rgb(99 102 241 / .08); --fc-neutral-bg-color: rgb(127 127 127 / .06); --fc-page-bg-color: transparent; font-size: .85rem; }
        .cal-wrap .fc .fc-toolbar-title { font-size: 1.25rem; font-weight: 700; }
        .cal-wrap .fc .fc-button { background: transparent; color: inherit; border: 1px solid rgb(127 127 127 / .3); text-transform: capitalize; font-size: .8rem; padding: .35rem .7rem; box-shadow: none !important; }
        .cal-wrap .fc .fc-button:hover { background: rgb(127 127 127 / .1); }
        .cal-wrap .fc .fc-button-active, .cal-wrap .fc .fc-button-primary:not(:disabled).fc-button-active { background: rgb(99 102 241); border-color: rgb(99 102 241); color: #fff; }
        .cal-wrap .fc-event { cursor: pointer; border-radius: .35rem; padding: 1px 3px; }
        .cal-wrap .fc-daygrid-day-number, .cal-wrap .fc-col-header-cell-cushion { color: inherit; text-decoration: none; }
        .dark .cal-wrap .fc-list-day-cushion { background: rgb(255 255 255 / .05); }
        .dark .cal-wrap .fc-list-event:hover td { background: rgb(255 255 255 / .05); }
        @media (max-width: 640px) { .cal-wrap .fc .fc-toolbar { flex-direction: column; gap: .5rem; } }
    </style>

    <script>
        window.adminCalendar = () => ({
            calendar: null,

            init() {
                const boot = () => {
                    const wire = this.$wire;
                    this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                        initialView: window.innerWidth < 640 ? 'listWeek' : 'dayGridMonth',
                        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth' },
                        firstDay: 1,
                        height: 'auto',
                        nowIndicator: true,
                        selectable: true,
                        editable: true,
                        dayMaxEvents: 4,
                        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                        events: (info, success, failure) => {
                            wire.fetchEvents(info.startStr, info.endStr).then(success).catch(failure);
                        },
                        select: (info) => {
                            wire.mountAction('createEvent', { start: info.startStr, end: info.endStr, allDay: info.allDay });
                            this.calendar.unselect();
                        },
                        eventClick: (info) => {
                            if (info.event.extendedProps.type !== 'event') return; // posts open their own URL
                            info.jsEvent.preventDefault();
                            wire.mountAction('editEvent', { id: info.event.id });
                        },
                        eventDrop: (info) => this.move(info),
                        eventResize: (info) => this.move(info),
                        eventDidMount: (info) => {
                            const p = info.event.extendedProps;
                            info.el.title = info.event.title + (p.location ? ' · ' + p.location : '') + (p.public ? ' · public' : '');
                        },
                    });
                    this.calendar.render();
                };

                if (window.FullCalendar) return boot();
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js';
                s.onload = boot;
                document.head.appendChild(s);
            },

            move(info) {
                const e = info.event;
                this.$wire.moveEvent(parseInt(e.id), e.startStr, e.endStr || null, e.allDay)
                    .catch(() => info.revert());
            },
        });
    </script>
</x-filament-panels::page>

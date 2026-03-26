import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import scrollGridPlugin from '@fullcalendar/scrollgrid';
import timeGridPlugin from '@fullcalendar/timegrid';

window.TailorFullCalendar = {
    Calendar,
    plugins: [dayGridPlugin, timeGridPlugin, scrollGridPlugin, interactionPlugin],
};

window.dispatchEvent(new CustomEvent('tailor-fullcalendar:ready'));

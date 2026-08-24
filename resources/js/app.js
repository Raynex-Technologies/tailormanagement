import './modules/fullcalendar-bootstrap';
import './modules/money-inputs';

document.addEventListener('livewire:navigated', () => {
    if (window.TailorFullCalendar?.Calendar) {
        window.dispatchEvent(new CustomEvent('tailor-fullcalendar:ready'));
    }
});

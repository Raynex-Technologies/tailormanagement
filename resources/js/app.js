import './modules/fullcalendar-bootstrap';

document.addEventListener('livewire:navigated', () => {
    if (window.TailorFullCalendar?.Calendar) {
        window.dispatchEvent(new CustomEvent('tailor-fullcalendar:ready'));
    }
});

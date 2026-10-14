import './styles/app.css';

// On-screen keyboard for Czech letters with diacritics: inserts the letter at the cursor.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-letter]');
    if (!button) {
        return;
    }
    const input = button.closest('form').querySelector('input[name="answer"]');
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? input.value.length;
    input.value = input.value.slice(0, start) + button.dataset.letter + input.value.slice(end);
    input.focus();
    input.setSelectionRange(start + 1, start + 1);
});

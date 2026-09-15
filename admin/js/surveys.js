document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-survey-dialog]');
    const openButton = document.querySelector('[data-open-survey-dialog]');
    const closeButtons = document.querySelectorAll('[data-close-survey-dialog]');
    const questionList = document.querySelector('[data-question-list]');
    const template = document.querySelector('#question-template');
    const addButton = document.querySelector('[data-add-question]');

    const updateQuestions = () => {
        questionList.querySelectorAll('.question-card').forEach((card, index) => {
            card.querySelector('[data-question-number]').textContent = index + 1;
            card.querySelector('[data-remove-question]').hidden = questionList.children.length === 1;
            card.querySelector('input[name^="question_text"]').name = `question_text[${index}]`;
            card.querySelector('select[name^="question_type"]').name = `question_type[${index}]`;
            card.querySelector('textarea[name^="question_choices"]').name = `question_choices[${index}]`;
            card.querySelector('input[name^="question_required"]').name = `question_required[${index}]`;
        });
    };

    const syncChoicesTextarea = (card) => {
        const hidden = card.querySelector('[data-choices-hidden]');
        if (!hidden) return;
        const values = Array.from(card.querySelectorAll('[data-choice-input]'))
            .map((input) => input.value.trim())
            .filter((value) => value !== '');
        hidden.value = values.join('\n');
    };

    const wireChoiceRow = (row, card) => {
        row.querySelector('[data-choice-input]').addEventListener('input', () => syncChoicesTextarea(card));
        row.querySelector('[data-remove-choice]').addEventListener('click', () => {
            const list = card.querySelector('[data-choice-options]');
            if (list.children.length > 1) row.remove();
            syncChoicesTextarea(card);
        });
    };

    const addChoiceRow = (card, focus = true) => {
        const list = card.querySelector('[data-choice-options]');
        const row = document.createElement('div');
        row.className = 'choice-option-row';
        row.setAttribute('data-choice-row', '');
        row.innerHTML = '<input type="text" class="choice-option-input" data-choice-input placeholder="Add a choice">' +
            '<button type="button" class="remove-choice" data-remove-choice aria-label="Remove option">&times;</button>';
        list.appendChild(row);
        wireChoiceRow(row, card);
        if (focus) row.querySelector('input').focus();
        syncChoicesTextarea(card);
    };

    const wireChoices = (card) => {
        const addChoiceButton = card.querySelector('[data-add-choice]');
        if (!addChoiceButton) return;
        card.querySelectorAll('[data-choice-row]').forEach((row) => wireChoiceRow(row, card));
        addChoiceButton.addEventListener('click', () => addChoiceRow(card));
        syncChoicesTextarea(card);
    };

    const addQuestion = () => {
        const card = template.content.firstElementChild.cloneNode(true);
        const type = card.querySelector('[data-question-type]');
        const choices = card.querySelector('[data-choices-field]');

        type.addEventListener('change', () => {
            choices.hidden = type.value !== 'multiple_choice';
        });
        card.querySelector('[data-remove-question]').addEventListener('click', () => {
            card.remove();
            updateQuestions();
        });
        wireChoices(card);
        questionList.appendChild(card);
        updateQuestions();
    };

    openButton.addEventListener('click', () => dialog.showModal());
    closeButtons.forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    addButton.addEventListener('click', addQuestion);
    addQuestion();
    if (new URLSearchParams(window.location.search).get('new') === '1') dialog.showModal();

    const form = document.querySelector('.survey-form');
    const openingDate = form?.querySelector('[name="opens_at"]');
    const closingDate = form?.querySelector('[name="closes_at"]');
    const validateDates = () => {
        if (!openingDate || !closingDate) return;
        const invalid = openingDate.value && closingDate.value && closingDate.value < openingDate.value;
        closingDate.setCustomValidity(invalid ? 'The closing date must be on or after the opening date.' : '');
        closingDate.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    };
    openingDate?.addEventListener('input', validateDates);
    closingDate?.addEventListener('input', validateDates);
    form?.addEventListener('input', (event) => {
        if (event.target.matches('input[required], textarea[required]')) event.target.setAttribute('aria-invalid', event.target.validity.valid ? 'false' : 'true');
    });
    form?.addEventListener('submit', () => {
        questionList.querySelectorAll('.question-card').forEach(syncChoicesTextarea);
    });
});

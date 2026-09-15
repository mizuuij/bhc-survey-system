document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-editor]');
    const openButtons = document.querySelectorAll('[data-open-editor]');
    const closeButtons = document.querySelectorAll('[data-close-editor]');
    const questionList = document.querySelector('[data-question-list]');
    const template = document.querySelector('#question-template');
    const addButton = document.querySelector('[data-add-question]');
    const surveyForm = document.querySelector('[data-survey-form]');

    const reactivateForm = document.querySelector('.reactivate-form');
    if (reactivateForm) {
        const openingDate = reactivateForm.querySelector('[name="opens_at"]');
        const closingDate = reactivateForm.querySelector('[name="closes_at"]');
        const validateReactivationDates = () => {
            if (!openingDate || !closingDate) return true;
            const invalid = openingDate.value && closingDate.value && closingDate.value <= openingDate.value;
            closingDate.setCustomValidity(invalid ? 'The closing date must be later than the opening date.' : '');
            closingDate.setAttribute('aria-invalid', invalid ? 'true' : 'false');
            return !invalid;
        };
        openingDate?.addEventListener('input', validateReactivationDates);
        closingDate?.addEventListener('input', validateReactivationDates);
        reactivateForm.addEventListener('submit', (event) => {
            if (!validateReactivationDates()) {
                event.preventDefault();
                closingDate?.reportValidity();
            }
        });
    }

    if (!questionList || !template || !addButton) return;

    const updateQuestionNames = () => {
        questionList.querySelectorAll('.edit-question').forEach((question, index) => {
            question.querySelector('[data-question-number]').textContent = index + 1;
            question.querySelector('[data-remove-question]').hidden = questionList.children.length === 1;
            question.querySelector('input[name^="question_text"]').name = `question_text[${index}]`;
            question.querySelector('select[name^="question_type"]').name = `question_type[${index}]`;
            question.querySelector('textarea[name^="question_choices"]').name = `question_choices[${index}]`;
            question.querySelector('input[name^="question_required"]').name = `question_required[${index}]`;
        });
    };

    const syncChoicesTextarea = (question) => {
        const hidden = question.querySelector('[data-choices-hidden]');
        if (!hidden) return;
        const values = Array.from(question.querySelectorAll('[data-choice-input]'))
            .map((input) => input.value.trim())
            .filter((value) => value !== '');
        hidden.value = values.join('\n');
    };

    const wireChoiceRow = (row, question) => {
        row.querySelector('[data-choice-input]').addEventListener('input', () => syncChoicesTextarea(question));
        row.querySelector('[data-remove-choice]').addEventListener('click', () => {
            const list = question.querySelector('[data-choice-options]');
            if (list.children.length > 1) row.remove();
            syncChoicesTextarea(question);
        });
    };

    const addChoiceRow = (question, focus = true) => {
        const list = question.querySelector('[data-choice-options]');
        const row = document.createElement('div');
        row.className = 'choice-option-row';
        row.setAttribute('data-choice-row', '');
        row.innerHTML = '<input type="text" class="choice-option-input" data-choice-input placeholder="Add a choice">' +
            '<button type="button" class="remove-choice" data-remove-choice aria-label="Remove option">&times;</button>';
        list.appendChild(row);
        wireChoiceRow(row, question);
        if (focus) row.querySelector('input').focus();
        syncChoicesTextarea(question);
    };

    const wireChoices = (question) => {
        const addChoiceButton = question.querySelector('[data-add-choice]');
        if (!addChoiceButton) return;
        question.querySelectorAll('[data-choice-row]').forEach((row) => wireChoiceRow(row, question));
        addChoiceButton.addEventListener('click', () => addChoiceRow(question));
        syncChoicesTextarea(question);
    };

    const wireQuestion = (question) => {
        const type = question.querySelector('[data-question-type]');
        const choices = question.querySelector('[data-choices-field]');
        type.addEventListener('change', () => {
            choices.hidden = type.value !== 'multiple_choice';
        });
        question.querySelector('[data-remove-question]').addEventListener('click', () => {
            question.remove();
            updateQuestionNames();
        });
        wireChoices(question);
    };

    const addQuestion = () => {
        const question = template.content.firstElementChild.cloneNode(true);
        wireQuestion(question);
        questionList.appendChild(question);
        updateQuestionNames();
    };

    questionList.querySelectorAll('.edit-question').forEach(wireQuestion);
    updateQuestionNames();
    if (editor) {
        openButtons.forEach((button) => button.addEventListener('click', () => { editor.hidden = false; editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); }));
        closeButtons.forEach((button) => button.addEventListener('click', () => { editor.hidden = true; }));
    }
    addButton.addEventListener('click', addQuestion);

    if (surveyForm) {
        surveyForm.addEventListener('submit', () => {
            questionList.querySelectorAll('.edit-question').forEach(syncChoicesTextarea);
        });
    }
});

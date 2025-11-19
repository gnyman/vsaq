// Questionnaire Editor
(function() {
    'use strict';

    // State
    let questionnaire = {
        version: 1,
        questionnaire: []
    };
    let currentEditingItem = null;
    let currentEditingPath = null;
    let templateId = null;
    let previewVisible = true;

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        // Check if editing existing template
        const urlParams = new URLSearchParams(window.location.search);
        templateId = urlParams.get('id');

        if (templateId) {
            loadTemplate(templateId);
        } else {
            render();
        }

        // Event listeners
        document.querySelectorAll('.add-question').forEach(btn => {
            btn.addEventListener('click', () => addQuestion(btn.dataset.type));
        });

        document.getElementById('save-template').addEventListener('click', saveTemplate);
        document.getElementById('import-json').addEventListener('click', importJSON);
        document.getElementById('export-json').addEventListener('click', exportJSON);
        document.getElementById('preview-toggle').addEventListener('click', togglePreview);
        document.getElementById('save-question').addEventListener('click', saveCurrentEdit);
        document.getElementById('cancel-edit').addEventListener('click', closeModal);
        document.getElementById('close-errors').addEventListener('click', closeValidationErrors);
    }

    // Load existing template
    async function loadTemplate(id) {
        try {
            const response = await fetch(`/php-vsaq/api/admin/templates/${id}`);
            const data = await response.json();

            if (data.error) {
                alert('Error loading template: ' + data.error);
                return;
            }

            document.getElementById('template-name').value = data.name;
            document.getElementById('template-description').value = data.description || '';

            const content = JSON.parse(data.content);
            questionnaire = content;
            render();
        } catch (error) {
            alert('Error loading template: ' + error.message);
        }
    }

    // Save template
    async function saveTemplate() {
        const name = document.getElementById('template-name').value.trim();
        const description = document.getElementById('template-description').value.trim();

        if (!name) {
            alert('Please enter a template name');
            return;
        }

        // Validate questionnaire
        const errors = validateQuestionnaire();
        if (errors.length > 0) {
            showValidationErrors(errors);
            return;
        }

        const content = JSON.stringify(questionnaire, null, 2);

        try {
            const url = templateId
                ? `/php-vsaq/api/admin/templates/${templateId}`
                : '/php-vsaq/api/admin/templates';

            const method = templateId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, description, content })
            });

            const result = await response.json();

            if (result.error) {
                alert('Error saving template: ' + result.error);
                return;
            }

            alert('Template saved successfully!');

            // If new template, redirect to edit with ID
            if (!templateId && result.id) {
                window.location.href = `edit.php?id=${result.id}`;
            }
        } catch (error) {
            alert('Error saving template: ' + error.message);
        }
    }

    // Validate questionnaire
    function validateQuestionnaire() {
        const errors = [];
        const usedIds = new Set();

        function validateItem(item, path) {
            // Check for required fields based on type
            if (!item.type) {
                errors.push(`Item at ${path}: Missing type`);
                return;
            }

            // Validate ID if present
            if (item.id) {
                if (usedIds.has(item.id)) {
                    errors.push(`Duplicate ID: ${item.id}`);
                }
                usedIds.add(item.id);

                // ID validation: alphanumeric, underscore, hyphen
                if (!/^[a-zA-Z0-9_-]+$/.test(item.id)) {
                    errors.push(`Invalid ID "${item.id}": Only letters, numbers, underscore, and hyphen allowed`);
                }
            }

            // Type-specific validation
            switch (item.type) {
                case 'line':
                case 'box':
                case 'check':
                    if (!item.id) {
                        errors.push(`${item.type} at ${path}: ID is required`);
                    }
                    break;

                case 'radiogroup':
                case 'checkgroup':
                    if (!item.id) {
                        errors.push(`${item.type} at ${path}: ID is required`);
                    }
                    if (!item.choices || item.choices.length === 0) {
                        errors.push(`${item.type} at ${path}: At least one choice required`);
                    }
                    break;

                case 'yesno':
                    if (!item.id) {
                        errors.push(`${item.type} at ${path}: ID is required`);
                    }
                    break;

                case 'block':
                    if (item.items) {
                        item.items.forEach((child, index) => {
                            validateItem(child, `${path}[${index}]`);
                        });
                    }
                    break;
            }
        }

        questionnaire.questionnaire.forEach((item, index) => {
            validateItem(item, `questionnaire[${index}]`);
        });

        return errors;
    }

    // Show validation errors
    function showValidationErrors(errors) {
        const errorList = document.getElementById('error-list');
        errorList.innerHTML = '';

        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });

        document.getElementById('validation-errors').style.display = 'block';
    }

    function closeValidationErrors() {
        document.getElementById('validation-errors').style.display = 'none';
    }

    // Import JSON
    function importJSON() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';

        input.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const data = JSON.parse(event.target.result);
                    if (!data.questionnaire || !Array.isArray(data.questionnaire)) {
                        throw new Error('Invalid questionnaire format');
                    }
                    questionnaire = data;
                    render();
                } catch (error) {
                    alert('Error parsing JSON: ' + error.message);
                }
            };
            reader.readAsText(file);
        };

        input.click();
    }

    // Export JSON
    function exportJSON() {
        const json = JSON.stringify(questionnaire, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = 'questionnaire.json';
        a.click();

        URL.revokeObjectURL(url);
    }

    // Toggle preview
    function togglePreview() {
        previewVisible = !previewVisible;
        const previewPane = document.getElementById('preview-pane');

        if (previewVisible) {
            previewPane.classList.remove('hidden');
        } else {
            previewPane.classList.add('hidden');
        }
    }

    // Add question
    function addQuestion(type, parentPath = null) {
        const newItem = createDefaultItem(type);
        editItem(newItem, null, parentPath);
    }

    // Create default item for type
    function createDefaultItem(type) {
        const defaults = {
            block: { type: 'block', text: 'New Section', items: [] },
            info: { type: 'info', text: 'Information text here' },
            tip: { type: 'tip', text: 'Warning text', warn: true, severity: 'medium', id: generateId('tip') },
            spacer: { type: 'spacer' },
            line: { type: 'line', id: generateId('q'), text: 'Question text' },
            box: { type: 'box', id: generateId('q'), text: 'Question text' },
            check: { type: 'check', id: generateId('q'), text: 'Checkbox label' },
            radiogroup: { type: 'radiogroup', id: generateId('q'), text: 'Question text', choices: [] },
            checkgroup: { type: 'checkgroup', id: generateId('q'), choices: [] },
            yesno: { type: 'yesno', id: generateId('q'), text: 'Question text', yes: [] },
            radio: { type: 'radio', id: generateId('q'), text: 'Radio label' }
        };

        return JSON.parse(JSON.stringify(defaults[type] || {}));
    }

    // Generate unique ID
    function generateId(prefix) {
        let counter = 1;
        let id;

        do {
            id = `${prefix}_${counter}`;
            counter++;
        } while (idExists(id));

        return id;
    }

    // Check if ID exists
    function idExists(id) {
        const usedIds = new Set();

        function collectIds(item) {
            if (item.id) usedIds.add(item.id);
            if (item.items) item.items.forEach(collectIds);
            if (item.yes) item.yes.forEach(collectIds);
        }

        questionnaire.questionnaire.forEach(collectIds);
        return usedIds.has(id);
    }

    // Edit item
    function editItem(item, path, parentPath = null) {
        currentEditingItem = JSON.parse(JSON.stringify(item));
        currentEditingPath = path;

        const form = document.getElementById('edit-form');
        form.innerHTML = '';

        // Title
        document.getElementById('modal-title').textContent = path !== null
            ? `Edit ${item.type} Question`
            : `Add ${item.type} Question`;

        // Build form based on type
        buildFormForType(item.type, currentEditingItem, form);

        // Store parent path for new items
        if (path === null) {
            form.dataset.parentPath = parentPath;
        }

        // Show modal
        document.getElementById('edit-modal').classList.add('active');
    }

    // Build form for specific question type
    function buildFormForType(type, item, form) {
        // Common fields
        if (type !== 'spacer') {
            if (['line', 'box', 'check', 'radiogroup', 'checkgroup', 'yesno', 'radio', 'tip'].includes(type)) {
                addFormField(form, 'id', 'ID', 'text', item.id || '', true, 'Unique identifier for this question');
            }

            if (['block', 'info', 'tip', 'line', 'box', 'check', 'radiogroup', 'yesno', 'radio'].includes(type)) {
                addFormField(form, 'text', 'Text', 'textarea', item.text || '', true);
            }
        }

        // Type-specific fields
        switch (type) {
            case 'tip':
                addFormField(form, 'severity', 'Severity', 'select', item.severity || 'medium', false, null, [
                    { value: 'critical', label: 'Critical' },
                    { value: 'high', label: 'High' },
                    { value: 'medium', label: 'Medium' }
                ]);
                addFormField(form, 'why', 'Explanation', 'textarea', item.why || '');
                addFormField(form, 'name', 'Display Name', 'text', item.name || '');
                addFormField(form, 'warn', 'Show Warning', 'checkbox', item.warn || false);
                break;

            case 'line':
            case 'box':
            case 'check':
                addFormField(form, 'required', 'Required', 'checkbox', item.required || false);
                addFormField(form, 'cond', 'Condition', 'text', item.cond || '', false, 'e.g. question_id/value');
                break;

            case 'radiogroup':
            case 'checkgroup':
                addChoicesField(form, item.choices || []);
                if (type === 'radiogroup') {
                    addFormField(form, 'defaultChoice', 'Default Choice', 'text', item.defaultChoice || '');
                }
                addFormField(form, 'cond', 'Condition', 'text', item.cond || '', false, 'e.g. question_id/value');
                break;

            case 'block':
                addFormField(form, 'cond', 'Condition', 'text', item.cond || '', false, 'e.g. question_id/value');
                break;
        }
    }

    // Add form field
    function addFormField(form, name, label, type, value, required = false, help = null, options = null) {
        const group = document.createElement('div');
        group.className = 'form-group';

        const labelEl = document.createElement('label');
        labelEl.textContent = label + (required ? ' *' : '');
        labelEl.setAttribute('for', `field-${name}`);
        group.appendChild(labelEl);

        let input;

        if (type === 'textarea') {
            input = document.createElement('textarea');
            input.value = value;
        } else if (type === 'checkbox') {
            input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = value;
        } else if (type === 'select') {
            input = document.createElement('select');
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                if (opt.value === value) option.selected = true;
                input.appendChild(option);
            });
        } else {
            input = document.createElement('input');
            input.type = type;
            input.value = value;
        }

        input.id = `field-${name}`;
        input.dataset.field = name;
        if (required) input.required = true;

        group.appendChild(input);

        if (help) {
            const helpText = document.createElement('div');
            helpText.className = 'form-help';
            helpText.textContent = help;
            group.appendChild(helpText);
        }

        form.appendChild(group);
    }

    // Add choices field (for radiogroup, checkgroup)
    function addChoicesField(form, choices) {
        const group = document.createElement('div');
        group.className = 'form-group';

        const label = document.createElement('label');
        label.textContent = 'Choices *';
        group.appendChild(label);

        const choicesList = document.createElement('div');
        choicesList.className = 'choices-list';
        choicesList.id = 'choices-list';

        choices.forEach((choice, index) => {
            addChoiceItem(choicesList, choice, index);
        });

        group.appendChild(choicesList);

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'add-choice-btn';
        addBtn.textContent = 'Add Choice';
        addBtn.onclick = () => addChoiceItem(choicesList, {}, choicesList.children.length);
        group.appendChild(addBtn);

        form.appendChild(group);
    }

    // Add choice item
    function addChoiceItem(container, choice, index) {
        const item = document.createElement('div');
        item.className = 'choice-item';

        const choiceId = Object.keys(choice)[0] || '';
        const choiceLabel = choice[choiceId] || '';

        const idInput = document.createElement('input');
        idInput.type = 'text';
        idInput.placeholder = 'Choice ID';
        idInput.value = choiceId;
        idInput.dataset.choiceIndex = index;
        idInput.dataset.choiceField = 'id';

        const labelInput = document.createElement('input');
        labelInput.type = 'text';
        labelInput.placeholder = 'Choice Label';
        labelInput.value = choiceLabel;
        labelInput.dataset.choiceIndex = index;
        labelInput.dataset.choiceField = 'label';

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.textContent = 'Remove';
        deleteBtn.onclick = () => item.remove();

        item.appendChild(idInput);
        item.appendChild(labelInput);
        item.appendChild(deleteBtn);

        container.appendChild(item);
    }

    // Save current edit
    function saveCurrentEdit() {
        const form = document.getElementById('edit-form');

        // Collect form data
        form.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;

            if (input.type === 'checkbox') {
                currentEditingItem[field] = input.checked;
            } else {
                const value = input.value.trim();
                if (value) {
                    currentEditingItem[field] = value;
                } else {
                    delete currentEditingItem[field];
                }
            }
        });

        // Collect choices
        const choicesList = document.getElementById('choices-list');
        if (choicesList) {
            const choices = [];
            choicesList.querySelectorAll('.choice-item').forEach(item => {
                const idInput = item.querySelector('[data-choice-field="id"]');
                const labelInput = item.querySelector('[data-choice-field="label"]');

                const id = idInput.value.trim();
                const label = labelInput.value.trim();

                if (id && label) {
                    choices.push({ [id]: label });
                }
            });
            currentEditingItem.choices = choices;
        }

        // Save to questionnaire
        if (currentEditingPath !== null) {
            // Update existing item
            setItemAtPath(questionnaire.questionnaire, currentEditingPath, currentEditingItem);
        } else {
            // Add new item
            const parentPath = form.dataset.parentPath;
            if (parentPath) {
                const parent = getItemAtPath(questionnaire.questionnaire, parentPath);
                if (!parent.items) parent.items = [];
                parent.items.push(currentEditingItem);
            } else {
                questionnaire.questionnaire.push(currentEditingItem);
            }
        }

        closeModal();
        render();
    }

    // Close modal
    function closeModal() {
        document.getElementById('edit-modal').classList.remove('active');
        currentEditingItem = null;
        currentEditingPath = null;
    }

    // Get item at path
    function getItemAtPath(items, path) {
        const parts = path.split('.');
        let current = items;

        for (const part of parts) {
            if (part.includes('[')) {
                const match = part.match(/(\w+)\[(\d+)\]/);
                if (match[1] === 'items' || match[1] === 'yes') {
                    current = current[match[1]][parseInt(match[2])];
                }
            } else {
                current = current[parseInt(part)];
            }
        }

        return current;
    }

    // Set item at path
    function setItemAtPath(items, path, value) {
        const parts = path.split('.');
        const lastPart = parts.pop();
        let current = items;

        for (const part of parts) {
            if (part.includes('[')) {
                const match = part.match(/(\w+)\[(\d+)\]/);
                current = current[match[1]][parseInt(match[2])];
            } else {
                current = current[parseInt(part)];
            }
        }

        const index = parseInt(lastPart);
        current[index] = value;
    }

    // Delete item at path
    function deleteItemAtPath(items, path) {
        const parts = path.split('.');
        const lastPart = parts.pop();
        let current = items;

        for (const part of parts) {
            if (part.includes('[')) {
                const match = part.match(/(\w+)\[(\d+)\]/);
                current = current[match[1]][parseInt(match[2])];
            } else {
                current = current[parseInt(part)];
            }
        }

        const index = parseInt(lastPart);
        current.splice(index, 1);
    }

    // Render questionnaire tree
    function render() {
        const tree = document.getElementById('questionnaire-tree');
        tree.innerHTML = '';

        if (questionnaire.questionnaire.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.innerHTML = '<p>No questions yet. Click a button above to add your first question.</p>';
            tree.appendChild(empty);
        } else {
            questionnaire.questionnaire.forEach((item, index) => {
                renderItem(item, index.toString(), tree);
            });
        }

        renderPreview();
    }

    // Render single item
    function renderItem(item, path, container) {
        const div = document.createElement('div');
        div.className = 'question-item';
        div.draggable = true;
        div.dataset.path = path;

        // Header
        const header = document.createElement('div');
        header.className = 'question-item-header';

        const typeSpan = document.createElement('span');
        typeSpan.className = 'question-type';
        typeSpan.textContent = item.type;

        const actions = document.createElement('div');
        actions.className = 'question-actions';

        const editBtn = document.createElement('button');
        editBtn.textContent = 'Edit';
        editBtn.onclick = () => editItem(item, path);

        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'delete-btn';
        deleteBtn.textContent = 'Delete';
        deleteBtn.onclick = () => {
            if (confirm('Delete this question?')) {
                deleteItemAtPath(questionnaire.questionnaire, path);
                render();
            }
        };

        actions.appendChild(editBtn);
        actions.appendChild(deleteBtn);

        header.appendChild(typeSpan);
        header.appendChild(actions);

        // Content
        const content = document.createElement('div');
        content.className = 'question-content';

        if (item.text) {
            const text = document.createElement('div');
            text.textContent = item.text.substring(0, 100) + (item.text.length > 100 ? '...' : '');
            content.appendChild(text);
        }

        if (item.id) {
            const id = document.createElement('div');
            id.className = 'question-id';
            id.textContent = `ID: ${item.id}`;
            content.appendChild(id);
        }

        div.appendChild(header);
        div.appendChild(content);

        // Nested items
        if (item.items && item.items.length > 0) {
            const nested = document.createElement('div');
            nested.className = 'nested-items';

            item.items.forEach((child, index) => {
                renderItem(child, `${path}.items[${index}]`, nested);
            });

            const addNestedBtn = document.createElement('button');
            addNestedBtn.className = 'btn btn-secondary';
            addNestedBtn.textContent = 'Add nested question';
            addNestedBtn.style.marginTop = '10px';
            addNestedBtn.onclick = () => {
                // Show menu of question types
                const type = prompt('Enter question type: line, box, check, radiogroup, checkgroup, yesno, info, tip, spacer');
                if (type) {
                    addQuestion(type, path);
                }
            };
            nested.appendChild(addNestedBtn);

            div.appendChild(nested);
        }

        container.appendChild(div);

        // Drag and drop
        div.addEventListener('dragstart', handleDragStart);
        div.addEventListener('dragover', handleDragOver);
        div.addEventListener('drop', handleDrop);
        div.addEventListener('dragend', handleDragEnd);
    }

    // Drag and drop handlers
    let draggedPath = null;

    function handleDragStart(e) {
        draggedPath = this.dataset.path;
        this.classList.add('dragging');
    }

    function handleDragOver(e) {
        e.preventDefault();
        return false;
    }

    function handleDrop(e) {
        e.stopPropagation();
        e.preventDefault();

        const targetPath = this.dataset.path;

        if (draggedPath && targetPath && draggedPath !== targetPath) {
            // Simple reorder (only works at same level for now)
            const draggedParts = draggedPath.split('.');
            const targetParts = targetPath.split('.');

            if (draggedParts.length === targetParts.length) {
                const draggedIndex = parseInt(draggedParts[draggedParts.length - 1]);
                const targetIndex = parseInt(targetParts[targetParts.length - 1]);

                const items = questionnaire.questionnaire;
                const [draggedItem] = items.splice(draggedIndex, 1);
                items.splice(targetIndex, 0, draggedItem);

                render();
            }
        }

        return false;
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        draggedPath = null;
    }

    // Render preview (simplified, just show the JSON structure)
    function renderPreview() {
        const preview = document.getElementById('preview-content');
        preview.innerHTML = '<pre>' + JSON.stringify(questionnaire, null, 2) + '</pre>';
    }

})();

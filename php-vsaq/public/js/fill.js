/**
 * VSAQ Fill Form - Server-backed with auto-save and conflict detection
 */

class VSAQFill {
    constructor() {
        this.questionnaire = null;
        this.answers = {};
        this.answerVersions = {};
        this.uniqueLink = null;
        this.isLocked = false;
        this.saveQueue = [];
        this.saveTimeout = null;
        this.conflictFields = new Set();

        this.init();
    }

    async init() {
        // Extract unique link from URL
        const pathParts = window.location.pathname.split('/');
        this.uniqueLink = pathParts[pathParts.length - 1];

        try {
            await this.loadQuestionnaire();
        } catch (error) {
            this.showError(error.message);
        }
    }

    async loadQuestionnaire() {
        try {
            document.getElementById('loading').style.display = 'block';

            const response = await fetch(`/php-vsaq/api/fill/${this.uniqueLink}`);
            if (!response.ok) {
                throw new Error('Questionnaire not found');
            }

            const data = await response.json();

            // Parse template content
            this.questionnaire = JSON.parse(data.template_content);
            this.isLocked = data.is_locked;

            // Load answers
            for (const [questionId, answerData] of Object.entries(data.answers)) {
                this.answers[questionId] = answerData.value;
                this.answerVersions[questionId] = answerData.version;
            }

            // Set questionnaire info
            if (data.questionnaire_name) {
                document.getElementById('questionnaire-title').textContent = data.questionnaire_name;
                document.getElementById('questionnaire-description').textContent = data.questionnaire_description || '';
                document.getElementById('questionnaire-info').style.display = 'block';
            }

            // Show locked banner if submitted
            if (this.isLocked) {
                document.getElementById('locked-banner').style.display = 'block';
            }

            this.renderQuestionnaire();
            document.getElementById('loading').style.display = 'none';

            // Setup submit button
            if (!this.isLocked) {
                document.getElementById('submit-container').style.display = 'block';
                document.getElementById('submit-btn').addEventListener('click', () => this.submitForm());
            }

            // Setup conflict modal
            document.getElementById('conflict-cancel-btn').addEventListener('click', () => this.hideConflictModal());
            document.getElementById('conflict-reload-btn').addEventListener('click', () => window.location.reload());

        } catch (error) {
            console.error('Error loading questionnaire:', error);
            throw error;
        }
    }

    renderQuestionnaire() {
        const container = document.getElementById('questionnaire-content');
        container.innerHTML = '';

        // Render items - handle both "items" and "questionnaire" keys
        const items = this.questionnaire.items || this.questionnaire.questionnaire || [];
        items.forEach(item => {
            const element = this.renderItem(item);
            if (element) {
                container.appendChild(element);
            }
        });

        // Update visibility based on conditions
        this.updateAllVisibility();

        // Show progress
        this.updateProgress();
        document.getElementById('progress-container').style.display = 'block';
    }

    renderItem(item, depth = 0) {
        const div = document.createElement('div');
        div.className = 'questionnaire-item';
        div.dataset.id = item.id || '';
        div.dataset.type = item.type || '';

        if (this.isLocked) {
            div.classList.add('locked');
        }

        if (item.cond) {
            div.dataset.cond = JSON.stringify(item.cond);
        }

        // Render based on type
        switch (item.type) {
            case 'block':
                return this.renderBlock(item, div);
            case 'info':
                return this.renderInfo(item, div);
            case 'tip':
                return this.renderTip(item, div);
            case 'spacer':
                return this.renderSpacer(div);
            case 'line':
                return this.renderLine(item, div);
            case 'box':
                return this.renderBox(item, div);
            case 'check':
                return this.renderCheck(item, div);
            case 'yesno':
                return this.renderYesNo(item, div);
            case 'radio':
                return this.renderRadio(item, div);
            case 'radiogroup':
            case 'checkgroup':
                return this.renderGroup(item, div);
            default:
                console.warn('Unknown item type:', item.type);
                return null;
        }
    }

    // Render methods (same as original vsaq.js but with server save)
    renderBlock(item, div) {
        div.classList.add('block');

        const header = document.createElement('div');
        header.className = 'block-header';
        header.textContent = item.text || '';
        div.appendChild(header);

        if (item.items && item.items.length > 0) {
            const content = document.createElement('div');
            content.className = 'block-content';
            item.items.forEach(subItem => {
                const element = this.renderItem(subItem);
                if (element) {
                    content.appendChild(element);
                }
            });
            div.appendChild(content);
        }

        return div;
    }

    renderInfo(item, div) {
        div.classList.add('info');

        if (item.text) {
            const text = document.createElement('div');
            text.className = 'item-text';
            text.innerHTML = this.formatText(item.text);
            div.appendChild(text);
        }

        return div;
    }

    renderTip(item, div) {
        div.classList.add('tip');

        const severity = item.severity || 'medium';
        div.classList.add(severity);

        if (item.text) {
            const text = document.createElement('div');
            text.className = 'item-text';
            text.innerHTML = this.formatText(item.text);
            div.appendChild(text);
        }

        return div;
    }

    renderSpacer(div) {
        div.classList.add('spacer');
        return div;
    }

    renderLine(item, div) {
        const question = document.createElement('div');
        question.className = 'item-question';
        if (item.required) question.classList.add('required');
        question.textContent = item.text || '';
        div.appendChild(question);

        if (item.placeholder) {
            const placeholder = document.createElement('div');
            placeholder.className = 'item-text';
            placeholder.textContent = item.placeholder;
            div.appendChild(placeholder);
        }

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'item-input';
        input.value = this.answers[item.id] || '';
        input.disabled = this.isLocked;

        if (!this.isLocked) {
            // Debounced save on input
            let inputTimeout;
            input.addEventListener('input', (e) => {
                clearTimeout(inputTimeout);
                inputTimeout = setTimeout(() => {
                    this.handleValueChange(item.id, e.target.value);
                }, 500);
            });
        }

        div.appendChild(input);

        return div;
    }

    renderBox(item, div) {
        const question = document.createElement('div');
        question.className = 'item-question';
        if (item.required) question.classList.add('required');
        question.textContent = item.text || '';
        div.appendChild(question);

        if (item.placeholder) {
            const placeholder = document.createElement('div');
            placeholder.className = 'item-text';
            placeholder.textContent = item.placeholder;
            div.appendChild(placeholder);
        }

        const textarea = document.createElement('textarea');
        textarea.className = 'item-input';
        textarea.value = this.answers[item.id] || '';
        textarea.disabled = this.isLocked;

        if (!this.isLocked) {
            let inputTimeout;
            textarea.addEventListener('input', (e) => {
                clearTimeout(inputTimeout);
                inputTimeout = setTimeout(() => {
                    this.handleValueChange(item.id, e.target.value);
                }, 500);
            });
        }

        div.appendChild(textarea);

        return div;
    }

    renderCheck(item, div) {
        const option = document.createElement('div');
        option.className = 'item-option';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = `check_${item.id}`;
        checkbox.checked = this.answers[item.id] === 'yes' || this.answers[item.id] === true;
        checkbox.disabled = this.isLocked;

        if (!this.isLocked) {
            checkbox.addEventListener('change', (e) => {
                this.handleValueChange(item.id, e.target.checked ? 'yes' : 'no');
            });
        }

        const label = document.createElement('label');
        label.htmlFor = `check_${item.id}`;
        label.textContent = item.text || '';

        option.appendChild(checkbox);
        option.appendChild(label);
        div.appendChild(option);

        return div;
    }

    renderYesNo(item, div) {
        const question = document.createElement('div');
        question.className = 'item-question';
        if (item.required) question.classList.add('required');
        question.textContent = item.text || '';
        div.appendChild(question);

        ['yes', 'no'].forEach(value => {
            const option = document.createElement('div');
            option.className = 'item-option';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = item.id;
            radio.id = `${item.id}_${value}`;
            radio.value = value;
            radio.checked = this.answers[item.id] === value;
            radio.disabled = this.isLocked;

            if (!this.isLocked) {
                radio.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        this.handleValueChange(item.id, value);
                    }
                });
            }

            const label = document.createElement('label');
            label.htmlFor = `${item.id}_${value}`;
            label.textContent = value.charAt(0).toUpperCase() + value.slice(1);

            option.appendChild(radio);
            option.appendChild(label);
            div.appendChild(option);
        });

        // Render nested items if they exist
        if (item.yes && this.answers[item.id] === 'yes') {
            const nested = document.createElement('div');
            nested.className = 'nested-items';
            item.yes.forEach(subItem => {
                const element = this.renderItem(subItem);
                if (element) {
                    nested.appendChild(element);
                }
            });
            div.appendChild(nested);
        }

        if (item.no && this.answers[item.id] === 'no') {
            const nested = document.createElement('div');
            nested.className = 'nested-items';
            item.no.forEach(subItem => {
                const element = this.renderItem(subItem);
                if (element) {
                    nested.appendChild(element);
                }
            });
            div.appendChild(nested);
        }

        return div;
    }

    renderRadio(item, div) {
        const question = document.createElement('div');
        question.className = 'item-question';
        if (item.required) question.classList.add('required');
        question.textContent = item.text || '';
        div.appendChild(question);

        if (item.choices) {
            item.choices.forEach(choice => {
                const option = document.createElement('div');
                option.className = 'item-option';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = item.id;
                radio.id = `${item.id}_${choice.value}`;
                radio.value = choice.value;
                radio.checked = this.answers[item.id] === choice.value;
                radio.disabled = this.isLocked;

                if (!this.isLocked) {
                    radio.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            this.handleValueChange(item.id, choice.value);
                        }
                    });
                }

                const label = document.createElement('label');
                label.htmlFor = `${item.id}_${choice.value}`;
                label.textContent = choice.text || choice.value;

                option.appendChild(radio);
                option.appendChild(label);
                div.appendChild(option);
            });
        }

        return div;
    }

    renderGroup(item, div) {
        if (item.text) {
            const question = document.createElement('div');
            question.className = 'item-question';
            if (item.required) question.classList.add('required');
            question.textContent = item.text;
            div.appendChild(question);
        }

        // Handle "choices" array format: [{"id": "text"}, ...]
        if (item.choices && Array.isArray(item.choices)) {
            item.choices.forEach(choice => {
                for (const [id, text] of Object.entries(choice)) {
                    const subItem = {
                        type: item.type === 'radiogroup' ? 'radio' : 'check',
                        id: id,
                        text: text
                    };

                    if (item.type === 'radiogroup') {
                        subItem.name = item.id || 'group_' + Math.random();
                    }

                    const element = this.renderItem(subItem);
                    if (element) {
                        div.appendChild(element);
                    }
                }
            });
        }
        // Handle "items" array format
        else if (item.items) {
            item.items.forEach(subItem => {
                const element = this.renderItem(subItem);
                if (element) {
                    div.appendChild(element);
                }
            });
        }

        return div;
    }

    async handleValueChange(id, value) {
        this.answers[id] = value;
        this.updateAllVisibility();
        this.updateProgress();

        // Save to server
        await this.saveAnswer(id, value);
    }

    async saveAnswer(questionId, answerValue) {
        try {
            const currentVersion = this.answerVersions[questionId] || 0;

            const response = await fetch(`/php-vsaq/api/fill/${this.uniqueLink}/save`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    question_id: questionId,
                    answer_value: answerValue,
                    version: currentVersion
                })
            });

            const data = await response.json();

            if (data.conflict) {
                // Conflict detected
                this.handleConflict(questionId);
            } else if (data.success) {
                // Update version
                this.answerVersions[questionId] = data.version;
                this.showAutosaveIndicator('Saved');

                // Remove from conflict set if it was there
                this.conflictFields.delete(questionId);
                const element = document.querySelector(`[data-id="${questionId}"]`);
                if (element) {
                    element.classList.remove('conflict');
                }
            }

        } catch (error) {
            console.error('Save error:', error);
            this.showAutosaveIndicator('Save failed', true);
        }
    }

    handleConflict(questionId) {
        // Add to conflict set
        this.conflictFields.add(questionId);

        // Highlight the field
        const element = document.querySelector(`[data-id="${questionId}"]`);
        if (element) {
            element.classList.add('conflict');
        }

        // Show conflict modal
        this.showConflictModal();
    }

    showConflictModal() {
        document.getElementById('conflict-modal').classList.add('show');
    }

    hideConflictModal() {
        document.getElementById('conflict-modal').classList.remove('show');
    }

    updateAllVisibility() {
        const items = document.querySelectorAll('[data-cond]');
        items.forEach(item => {
            try {
                const cond = JSON.parse(item.dataset.cond);
                const visible = this.evaluateCondition(cond);
                if (visible) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            } catch (e) {
                console.error('Error evaluating condition:', e);
            }
        });

        // Re-render nested items in yesno
        const yesnoItems = document.querySelectorAll('[data-type="yesno"]');
        yesnoItems.forEach(itemEl => {
            const id = itemEl.dataset.id;
            const item = this.findItemById(id);
            if (item) {
                const existing = itemEl.querySelector('.nested-items');
                if (existing) {
                    existing.remove();
                }

                if (item.yes && this.answers[id] === 'yes') {
                    const nested = document.createElement('div');
                    nested.className = 'nested-items';
                    item.yes.forEach(subItem => {
                        const element = this.renderItem(subItem);
                        if (element) {
                            nested.appendChild(element);
                        }
                    });
                    itemEl.appendChild(nested);
                }

                if (item.no && this.answers[id] === 'no') {
                    const nested = document.createElement('div');
                    nested.className = 'nested-items';
                    item.no.forEach(subItem => {
                        const element = this.renderItem(subItem);
                        if (element) {
                            nested.appendChild(element);
                        }
                    });
                    itemEl.appendChild(nested);
                }
            }
        });
    }

    evaluateCondition(cond) {
        if (!cond) return true;

        // Handle string condition format: "id/value"
        if (typeof cond === 'string') {
            const parts = cond.split('/');
            if (parts.length === 2) {
                const [id, value] = parts;
                return this.answers[id] === value;
            }
            return false;
        }

        if (cond.and) {
            return cond.and.every(c => this.evaluateCondition(c));
        }

        if (cond.or) {
            return cond.or.some(c => this.evaluateCondition(c));
        }

        if (cond.not) {
            return !this.evaluateCondition(cond.not);
        }

        for (const [key, value] of Object.entries(cond)) {
            if (key !== 'and' && key !== 'or' && key !== 'not') {
                return this.answers[key] === value;
            }
        }

        return true;
    }

    findItemById(id, items = null) {
        if (!items) {
            items = this.questionnaire.items || this.questionnaire.questionnaire || [];
        }

        for (const item of items) {
            if (item.id === id) {
                return item;
            }

            if (item.items) {
                const found = this.findItemById(id, item.items);
                if (found) return found;
            }

            if (item.yes) {
                const found = this.findItemById(id, item.yes);
                if (found) return found;
            }

            if (item.no) {
                const found = this.findItemById(id, item.no);
                if (found) return found;
            }
        }

        return null;
    }

    updateProgress() {
        const allValueItems = this.getAllValueItems();
        const visibleItems = allValueItems.filter(item => {
            const element = document.querySelector(`[data-id="${item.id}"]`);
            return element && !element.classList.contains('hidden');
        });

        const answered = visibleItems.filter(item => {
            const answer = this.answers[item.id];
            return answer !== undefined && answer !== null && answer !== '';
        }).length;

        const total = visibleItems.length;
        const percent = total > 0 ? Math.round((answered / total) * 100) : 0;

        document.getElementById('progress-answered').textContent = answered;
        document.getElementById('progress-total').textContent = total;
        document.getElementById('progress-percent').textContent = percent;
        document.getElementById('progress-bar-fill').style.width = `${percent}%`;
    }

    getAllValueItems(items = null, result = []) {
        if (!items) {
            items = this.questionnaire.items || this.questionnaire.questionnaire || [];
        }

        for (const item of items) {
            if (['line', 'box', 'check', 'yesno', 'radio'].includes(item.type)) {
                result.push(item);
            }

            if (item.items) {
                this.getAllValueItems(item.items, result);
            }

            if (item.yes) {
                this.getAllValueItems(item.yes, result);
            }

            if (item.no) {
                this.getAllValueItems(item.no, result);
            }
        }

        return result;
    }

    showAutosaveIndicator(text, isError = false) {
        const indicator = document.getElementById('autosave-indicator');
        indicator.textContent = text;
        indicator.className = 'autosave-indicator show';

        if (isError) {
            indicator.style.background = 'var(--danger-color)';
        } else {
            indicator.style.background = 'var(--success-color)';
        }

        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }

    async submitForm() {
        if (!confirm('Submit this questionnaire? You will not be able to edit your answers after submission.')) {
            return;
        }

        if (this.conflictFields.size > 0) {
            alert('Please resolve conflicts before submitting.');
            return;
        }

        try {
            const response = await fetch(`/php-vsaq/api/fill/${this.uniqueLink}/submit`, {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error('Failed to submit questionnaire');
            }

            alert('Questionnaire submitted successfully!');
            window.location.reload();

        } catch (error) {
            alert('Error submitting questionnaire: ' + error.message);
        }
    }

    showError(message) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error-state').style.display = 'block';
        document.getElementById('error-message').textContent = message;
    }

    formatText(text) {
        // VSAQ questionnaires can contain HTML markup like <code>, <b>, <ul>, etc.
        // We allow safe HTML tags while still supporting markdown-style formatting
        // The questionnaires are admin-created, so we trust their content
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }
}

// Initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new VSAQFill();
    });
} else {
    new VSAQFill();
}

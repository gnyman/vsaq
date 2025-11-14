/**
 * VSAQ PHP - Client-side JavaScript
 * Simple, dependency-free implementation
 */

class VSAQ {
    constructor() {
        this.questionnaire = null;
        this.answers = {};
        this.currentQuestionnairePath = null;
        this.autosaveTimeout = null;

        this.init();
    }

    init() {
        // Set up event listeners
        document.getElementById('questionnaire-select').addEventListener('change', (e) => {
            this.loadQuestionnaire(e.target.value);
        });

        document.getElementById('import-btn').addEventListener('click', () => {
            this.importAnswers();
        });

        document.getElementById('export-btn').addEventListener('click', () => {
            this.exportAnswers();
        });

        document.getElementById('clear-btn').addEventListener('click', () => {
            if (confirm('Are you sure you want to clear all answers? This cannot be undone.')) {
                this.clearAnswers();
            }
        });

        document.getElementById('import-file-input').addEventListener('change', (e) => {
            this.handleImportFile(e);
        });

        // Check URL parameters
        const params = new URLSearchParams(window.location.search);
        const qpath = params.get('qpath');
        if (qpath) {
            document.getElementById('questionnaire-select').value = qpath;
            this.loadQuestionnaire(qpath);
        }

        // Load saved answers from localStorage
        this.loadFromLocalStorage();
    }

    async loadQuestionnaire(path) {
        if (!path) {
            document.getElementById('questionnaire-content').innerHTML = '';
            document.getElementById('loading').style.display = 'block';
            document.getElementById('questionnaire-info').style.display = 'none';
            document.getElementById('progress-container').style.display = 'none';
            return;
        }

        try {
            document.getElementById('loading').innerHTML = '<p>Loading questionnaire...</p>';
            document.getElementById('loading').style.display = 'block';

            const response = await fetch(`/php-vsaq/api/questionnaire?qpath=${encodeURIComponent(path)}`);
            if (!response.ok) {
                throw new Error('Failed to load questionnaire');
            }

            this.questionnaire = await response.json();
            this.currentQuestionnairePath = path;

            // Load saved answers for this questionnaire
            const savedKey = `vsaq_answers_${path}`;
            const saved = localStorage.getItem(savedKey);
            if (saved) {
                try {
                    this.answers = JSON.parse(saved);
                } catch (e) {
                    this.answers = {};
                }
            } else {
                this.answers = {};
            }

            this.renderQuestionnaire();
            document.getElementById('loading').style.display = 'none';

        } catch (error) {
            console.error('Error loading questionnaire:', error);
            document.getElementById('loading').innerHTML = '<p style="color: var(--danger-color);">Error loading questionnaire. Please try again.</p>';
        }
    }

    renderQuestionnaire() {
        const container = document.getElementById('questionnaire-content');
        container.innerHTML = '';

        // Show questionnaire info
        const infoBox = document.getElementById('questionnaire-info');
        const titleEl = document.getElementById('questionnaire-title');
        const descEl = document.getElementById('questionnaire-description');

        if (this.questionnaire.name) {
            titleEl.textContent = this.questionnaire.name;
            descEl.textContent = this.questionnaire.description || '';
            infoBox.style.display = 'block';
        } else {
            infoBox.style.display = 'none';
        }

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
        input.addEventListener('input', (e) => {
            this.handleValueChange(item.id, e.target.value);
        });
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
        textarea.addEventListener('input', (e) => {
            this.handleValueChange(item.id, e.target.value);
        });
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
        checkbox.addEventListener('change', (e) => {
            this.handleValueChange(item.id, e.target.checked ? 'yes' : 'no');
        });

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
            radio.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.handleValueChange(item.id, value);
                }
            });

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
                radio.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        this.handleValueChange(item.id, choice.value);
                    }
                });

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
                // Each choice is an object like {"id": "text"}
                for (const [id, text] of Object.entries(choice)) {
                    const subItem = {
                        type: item.type === 'radiogroup' ? 'radio' : 'check',
                        id: id,
                        text: text
                    };

                    // For radiogroup, all items share the same name
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

    handleValueChange(id, value) {
        this.answers[id] = value;
        this.saveToLocalStorage();
        this.updateAllVisibility();
        this.updateProgress();
        this.showAutosaveIndicator();
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

        // Re-render items with nested content (like yesno)
        const yesnoItems = document.querySelectorAll('[data-type="yesno"]');
        yesnoItems.forEach(itemEl => {
            const id = itemEl.dataset.id;
            const item = this.findItemById(id);
            if (item) {
                // Remove existing nested items
                const existing = itemEl.querySelector('.nested-items');
                if (existing) {
                    existing.remove();
                }

                // Re-render nested items based on current answer
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

        // Handle AND conditions
        if (cond.and) {
            return cond.and.every(c => this.evaluateCondition(c));
        }

        // Handle OR conditions
        if (cond.or) {
            return cond.or.some(c => this.evaluateCondition(c));
        }

        // Handle NOT conditions
        if (cond.not) {
            return !this.evaluateCondition(cond.not);
        }

        // Simple condition: {id: "value"}
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

            // Search in nested items
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
            // Items that have values: line, box, check, yesno, radio
            if (['line', 'box', 'check', 'yesno', 'radio'].includes(item.type)) {
                result.push(item);
            }

            // Recurse into nested items
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

    saveToLocalStorage() {
        if (!this.currentQuestionnairePath) return;

        const key = `vsaq_answers_${this.currentQuestionnairePath}`;
        localStorage.setItem(key, JSON.stringify(this.answers));
    }

    loadFromLocalStorage() {
        // Already handled in loadQuestionnaire
    }

    showAutosaveIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'autosave-indicator show';
        indicator.textContent = 'Saved';
        document.body.appendChild(indicator);

        setTimeout(() => {
            indicator.classList.remove('show');
            setTimeout(() => indicator.remove(), 300);
        }, 2000);
    }

    exportAnswers() {
        if (!this.currentQuestionnairePath) {
            alert('Please load a questionnaire first');
            return;
        }

        const data = {
            questionnaire: this.currentQuestionnairePath,
            questionnaireName: this.questionnaire.name || '',
            timestamp: new Date().toISOString(),
            answers: this.answers
        };

        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `vsaq-answers-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    importAnswers() {
        document.getElementById('import-file-input').click();
    }

    handleImportFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);

                if (!data.answers) {
                    alert('Invalid file format');
                    return;
                }

                // Load the questionnaire if not already loaded
                if (data.questionnaire && data.questionnaire !== this.currentQuestionnairePath) {
                    document.getElementById('questionnaire-select').value = data.questionnaire;
                    this.loadQuestionnaire(data.questionnaire).then(() => {
                        this.answers = data.answers;
                        this.renderQuestionnaire();
                        this.saveToLocalStorage();
                    });
                } else {
                    this.answers = data.answers;
                    this.renderQuestionnaire();
                    this.saveToLocalStorage();
                }

            } catch (error) {
                console.error('Error importing answers:', error);
                alert('Error importing file. Please check the file format.');
            }
        };
        reader.readAsText(file);

        // Reset file input
        event.target.value = '';
    }

    clearAnswers() {
        this.answers = {};
        this.renderQuestionnaire();
        this.saveToLocalStorage();
    }

    formatText(text) {
        // Simple markdown-like formatting
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new VSAQ();
    });
} else {
    new VSAQ();
}

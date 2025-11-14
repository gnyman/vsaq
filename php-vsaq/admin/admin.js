/**
 * VSAQ Admin Interface
 */

class VSAQAdmin {
    constructor() {
        this.currentTab = 'templates';
        this.templates = [];
        this.instances = [];
        this.currentTemplate = null;

        this.init();
    }

    async init() {
        // Check authentication status
        const isAuth = await this.checkAuth();

        if (isAuth) {
            this.showDashboard();
        } else {
            this.showLogin();
        }
    }

    showLogin() {
        document.getElementById('login-screen').style.display = 'flex';
        document.getElementById('dashboard').style.display = 'none';

        // Login button
        document.getElementById('login-btn').addEventListener('click', () => this.login());

        // Register button
        document.getElementById('register-btn').addEventListener('click', () => this.register());

        // Enter key on username
        document.getElementById('username').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.login();
            }
        });
    }

    showDashboard() {
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('dashboard').style.display = 'block';

        this.setupDashboard();
        this.loadTemplates();
        this.loadInstances();
    }

    setupDashboard() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                this.switchTab(tab);
            });
        });

        // Logout
        document.getElementById('logout-btn').addEventListener('click', () => this.logout());

        // Create template
        document.getElementById('create-template-btn').addEventListener('click', () => this.openTemplateModal());

        // Create instance
        document.getElementById('create-instance-btn').addEventListener('click', () => this.openInstanceModal());

        // Template modal
        document.querySelector('#template-modal .modal-close').addEventListener('click', () => this.closeTemplateModal());
        document.getElementById('template-cancel-btn').addEventListener('click', () => this.closeTemplateModal());
        document.getElementById('template-save-btn').addEventListener('click', () => this.saveTemplate());

        // Instance modal
        document.querySelector('#instance-modal .modal-close').addEventListener('click', () => this.closeInstanceModal());
        document.getElementById('instance-cancel-btn').addEventListener('click', () => this.closeInstanceModal());
        document.getElementById('instance-create-btn').addEventListener('click', () => this.createInstance());
        document.getElementById('copy-link-btn').addEventListener('click', () => this.copyLink());
    }

    switchTab(tab) {
        this.currentTab = tab;

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `tab-${tab}`);
        });
    }

    // ========================================================================
    // AUTHENTICATION
    // ========================================================================

    async checkAuth() {
        try {
            const response = await fetch('/php-vsaq/api/auth/check');
            const data = await response.json();
            return data.authenticated;
        } catch (error) {
            console.error('Auth check failed:', error);
            return false;
        }
    }

    async register() {
        const username = document.getElementById('username').value.trim();

        if (!username) {
            this.showMessage('Please enter a username', 'error');
            return;
        }

        try {
            this.showMessage('Setting up passkey...', 'success');

            // Get registration options
            const optionsResponse = await fetch('/php-vsaq/api/auth/register/options', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ username })
            });

            if (!optionsResponse.ok) {
                throw new Error('Failed to get registration options');
            }

            const options = await optionsResponse.json();

            // Create credential
            const credential = await navigator.credentials.create({
                publicKey: {
                    ...options,
                    challenge: this.base64urlDecode(options.challenge),
                    user: {
                        ...options.user,
                        id: this.base64urlDecode(options.user.id)
                    }
                }
            });

            // Send credential to server
            const verifyResponse = await fetch('/php-vsaq/api/auth/register/verify', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    username,
                    credential: {
                        id: credential.id,
                        rawId: this.arrayBufferToBase64(credential.rawId),
                        response: {
                            clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON),
                            attestationObject: this.arrayBufferToBase64(credential.response.attestationObject),
                            publicKey: credential.response.getPublicKey ? this.arrayBufferToBase64(credential.response.getPublicKey()) : ''
                        },
                        type: credential.type
                    }
                })
            });

            if (!verifyResponse.ok) {
                throw new Error('Registration verification failed');
            }

            this.showMessage('Admin registered successfully! Please login.', 'success');

        } catch (error) {
            console.error('Registration error:', error);
            this.showMessage('Registration failed: ' + error.message, 'error');
        }
    }

    async login() {
        try {
            this.showMessage('Authenticating with passkey...', 'success');

            // Get authentication options
            const optionsResponse = await fetch('/php-vsaq/api/auth/login/options');

            if (!optionsResponse.ok) {
                throw new Error('Failed to get login options');
            }

            const options = await optionsResponse.json();

            // Get credential
            const credential = await navigator.credentials.get({
                publicKey: {
                    ...options,
                    challenge: this.base64urlDecode(options.challenge),
                    allowCredentials: options.allowCredentials.map(cred => ({
                        ...cred,
                        id: typeof cred.id === 'string' ? this.base64urlDecode(cred.id) : cred.id
                    }))
                }
            });

            // Verify with server
            const verifyResponse = await fetch('/php-vsaq/api/auth/login/verify', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    credential: {
                        id: credential.id,
                        rawId: this.arrayBufferToBase64(credential.rawId),
                        response: {
                            clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON),
                            authenticatorData: this.arrayBufferToBase64(credential.response.authenticatorData),
                            signature: this.arrayBufferToBase64(credential.response.signature),
                            userHandle: credential.response.userHandle ? this.arrayBufferToBase64(credential.response.userHandle) : null
                        },
                        type: credential.type
                    }
                })
            });

            if (!verifyResponse.ok) {
                throw new Error('Authentication failed');
            }

            // Success - show dashboard
            this.showDashboard();

        } catch (error) {
            console.error('Login error:', error);
            this.showMessage('Login failed: ' + error.message, 'error');
        }
    }

    async logout() {
        try {
            await fetch('/php-vsaq/api/auth/logout', { method: 'POST' });
            window.location.reload();
        } catch (error) {
            console.error('Logout error:', error);
            window.location.reload();
        }
    }

    showMessage(message, type) {
        const messageEl = document.getElementById('login-message');
        messageEl.textContent = message;
        messageEl.className = `message ${type}`;
        messageEl.style.display = 'block';

        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 5000);
    }

    // ========================================================================
    // TEMPLATES
    // ========================================================================

    async loadTemplates() {
        try {
            const response = await fetch('/php-vsaq/api/admin/templates');
            this.templates = await response.json();
            this.renderTemplates();
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }

    renderTemplates() {
        const container = document.getElementById('templates-list');
        container.innerHTML = '';

        if (this.templates.length === 0) {
            container.innerHTML = '<p class="empty-state">No templates yet. Create your first template to get started.</p>';
            return;
        }

        this.templates.forEach(template => {
            const card = document.createElement('div');
            card.className = 'card';

            const isArchived = template.is_archived == 1;
            const hasSentInstances = false; // Would need to check this

            card.innerHTML = `
                <div class="card-header">
                    <div>
                        <div class="card-title">${this.escapeHtml(template.name)}</div>
                        ${isArchived ? '<span class="badge badge-archived">Archived</span>' : ''}
                    </div>
                </div>
                <div class="card-description">${this.escapeHtml(template.description || 'No description')}</div>
                <div class="card-meta">
                    Created by ${this.escapeHtml(template.created_by_username)} on ${this.formatDate(template.created_at)}
                </div>
                <div class="card-actions">
                    <button class="btn btn-primary" onclick="vsaqAdmin.editTemplate(${template.id})">Edit</button>
                    <button class="btn" onclick="vsaqAdmin.duplicateTemplate(${template.id})">Duplicate</button>
                    <button class="btn" onclick="vsaqAdmin.archiveTemplate(${template.id}, ${!isArchived})">${isArchived ? 'Unarchive' : 'Archive'}</button>
                    ${!hasSentInstances ? `<button class="btn btn-danger" onclick="vsaqAdmin.deleteTemplate(${template.id})">Delete</button>` : ''}
                </div>
            `;

            container.appendChild(card);
        });
    }

    openTemplateModal(templateId = null) {
        this.currentTemplate = templateId;

        const modal = document.getElementById('template-modal');
        const title = document.getElementById('template-modal-title');
        const name = document.getElementById('template-name');
        const description = document.getElementById('template-description');
        const content = document.getElementById('template-content');

        if (templateId) {
            title.textContent = 'Edit Template';
            const template = this.templates.find(t => t.id === templateId);
            if (template) {
                name.value = template.name;
                description.value = template.description || '';
                content.value = this.formatJSON(template.content);
            }
        } else {
            title.textContent = 'Create Template';
            name.value = '';
            description.value = '';
            content.value = JSON.stringify({
                "questionnaire": [
                    {
                        "type": "block",
                        "text": "Example Section",
                        "items": [
                            {
                                "type": "line",
                                "id": "example_field",
                                "text": "Example question?"
                            }
                        ]
                    }
                ]
            }, null, 2);
        }

        modal.classList.add('show');
    }

    closeTemplateModal() {
        document.getElementById('template-modal').classList.remove('show');
        document.getElementById('template-error').style.display = 'none';
    }

    async saveTemplate() {
        const name = document.getElementById('template-name').value.trim();
        const description = document.getElementById('template-description').value.trim();
        const content = document.getElementById('template-content').value.trim();

        if (!name || !content) {
            this.showTemplateError('Name and content are required');
            return;
        }

        // Validate JSON
        try {
            JSON.parse(content);
        } catch (e) {
            this.showTemplateError('Invalid JSON: ' + e.message);
            return;
        }

        try {
            const url = this.currentTemplate
                ? `/php-vsaq/api/admin/templates/${this.currentTemplate}`
                : '/php-vsaq/api/admin/templates';

            const method = this.currentTemplate ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ name, description, content })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to save template');
            }

            this.closeTemplateModal();
            this.loadTemplates();

        } catch (error) {
            this.showTemplateError(error.message);
        }
    }

    async editTemplate(id) {
        this.openTemplateModal(id);
    }

    async duplicateTemplate(id) {
        if (!confirm('Duplicate this template?')) return;

        try {
            const response = await fetch(`/php-vsaq/api/admin/templates/${id}/duplicate`, {
                method: 'POST'
            });

            if (!response.ok) throw new Error('Failed to duplicate template');

            this.loadTemplates();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async archiveTemplate(id, archive) {
        try {
            const response = await fetch(`/php-vsaq/api/admin/templates/${id}/archive`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ archive })
            });

            if (!response.ok) throw new Error('Failed to archive template');

            this.loadTemplates();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async deleteTemplate(id) {
        if (!confirm('Delete this template? This cannot be undone.')) return;

        try {
            const response = await fetch(`/php-vsaq/api/admin/templates/${id}`, {
                method: 'DELETE'
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to delete template');
            }

            this.loadTemplates();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    showTemplateError(message) {
        const errorEl = document.getElementById('template-error');
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    // ========================================================================
    // INSTANCES
    // ========================================================================

    async loadInstances() {
        try {
            const response = await fetch('/php-vsaq/api/admin/instances');
            this.instances = await response.json();
            this.renderInstances();
        } catch (error) {
            console.error('Failed to load instances:', error);
        }
    }

    renderInstances() {
        const container = document.getElementById('instances-list');

        if (this.instances.length === 0) {
            container.innerHTML = '<p class="empty-state">No questionnaires sent yet.</p>';
            return;
        }

        let html = '<table><thead><tr>';
        html += '<th>Template</th>';
        html += '<th>Target</th>';
        html += '<th>Created</th>';
        html += '<th>Status</th>';
        html += '<th>Answers</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        this.instances.forEach(instance => {
            const status = instance.submitted_at ? 'submitted' : 'pending';
            const statusText = instance.submitted_at ? 'Submitted' : 'Pending';

            html += '<tr>';
            html += `<td>${this.escapeHtml(instance.template_name)}</td>`;
            html += `<td>${this.escapeHtml(instance.target_name || instance.target_email || 'N/A')}</td>`;
            html += `<td>${this.formatDate(instance.created_at)}</td>`;
            html += `<td><span class="status-badge status-${status}">${statusText}</span></td>`;
            html += `<td>${instance.answer_count || 0} answers</td>`;
            html += `<td><div class="table-actions">`;
            html += `<button class="btn btn-primary" onclick="vsaqAdmin.viewInstance('${instance.unique_link}')">View</button>`;
            html += `<button class="btn" onclick="vsaqAdmin.copyInstanceLink('${instance.unique_link}')">Copy Link</button>`;
            if (instance.submitted_at) {
                html += `<button class="btn" onclick="vsaqAdmin.unlockInstance(${instance.id})">Unlock</button>`;
            }
            if (!instance.submitted_at) {
                html += `<button class="btn btn-danger" onclick="vsaqAdmin.deleteInstance(${instance.id})">Delete</button>`;
            }
            html += `</div></td>`;
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    async openInstanceModal() {
        const select = document.getElementById('instance-template');
        select.innerHTML = '<option value="">Select a template...</option>';

        this.templates.filter(t => !t.is_archived).forEach(template => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.name;
            select.appendChild(option);
        });

        document.getElementById('instance-target-name').value = '';
        document.getElementById('instance-target-email').value = '';
        document.getElementById('instance-link-result').style.display = 'none';
        document.getElementById('instance-error').style.display = 'none';
        document.getElementById('instance-create-btn').style.display = 'inline-block';

        document.getElementById('instance-modal').classList.add('show');
    }

    closeInstanceModal() {
        document.getElementById('instance-modal').classList.remove('show');
    }

    async createInstance() {
        const templateId = document.getElementById('instance-template').value;
        const targetName = document.getElementById('instance-target-name').value.trim();
        const targetEmail = document.getElementById('instance-target-email').value.trim();

        if (!templateId) {
            this.showInstanceError('Please select a template');
            return;
        }

        try {
            const response = await fetch('/php-vsaq/api/admin/instances', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    template_id: templateId,
                    target_name: targetName,
                    target_email: targetEmail
                })
            });

            if (!response.ok) {
                throw new Error('Failed to create questionnaire');
            }

            const data = await response.json();

            // Show link
            const fullUrl = window.location.origin + data.url;
            document.getElementById('instance-link-url').value = fullUrl;
            document.getElementById('instance-link-result').style.display = 'block';
            document.getElementById('instance-create-btn').style.display = 'none';

            this.loadInstances();

        } catch (error) {
            this.showInstanceError(error.message);
        }
    }

    showInstanceError(message) {
        const errorEl = document.getElementById('instance-error');
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    viewInstance(uniqueLink) {
        window.open(`/php-vsaq/f/${uniqueLink}`, '_blank');
    }

    copyInstanceLink(uniqueLink) {
        const url = `${window.location.origin}/php-vsaq/f/${uniqueLink}`;
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }

    async unlockInstance(id) {
        if (!confirm('Unlock this questionnaire? The recipient will be able to edit their answers again.')) return;

        try {
            const response = await fetch(`/php-vsaq/api/admin/instances/${id}/unlock`, {
                method: 'POST'
            });

            if (!response.ok) throw new Error('Failed to unlock instance');

            this.loadInstances();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async deleteInstance(id) {
        if (!confirm('Delete this questionnaire? This cannot be undone.')) return;

        try {
            const response = await fetch(`/php-vsaq/api/admin/instances/${id}`, {
                method: 'DELETE'
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to delete instance');
            }

            this.loadInstances();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    copyLink() {
        const input = document.getElementById('instance-link-url');
        input.select();
        navigator.clipboard.writeText(input.value);
        alert('Link copied to clipboard!');
    }

    // ========================================================================
    // UTILITIES
    // ========================================================================

    base64urlDecode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const binary = atob(str);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatDate(timestamp) {
        return new Date(timestamp * 1000).toLocaleDateString();
    }

    formatJSON(str) {
        try {
            return JSON.stringify(JSON.parse(str), null, 2);
        } catch {
            return str;
        }
    }
}

// Initialize admin
const vsaqAdmin = new VSAQAdmin();

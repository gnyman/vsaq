# VSAQ PHP - Vendor Security Assessment Questionnaire

A simple, lightweight PHP rewrite of Google's VSAQ (Vendor Security Assessment Questionnaire) with zero dependencies and a complete admin interface.

## What is VSAQ?

VSAQ is an interactive questionnaire application designed for security assessments of third-party vendors and service providers. This PHP version adds:

- **WebAuthn Admin Interface** - Secure passkey-only authentication
- **Questionnaire Management** - Create, edit, duplicate, and archive templates
- **Unique Link Generation** - Send questionnaires via secure unique links
- **Server-Side Auto-Save** - Automatic saving with conflict detection
- **Form Locking** - Submitted forms are locked but viewable
- **Real-Time Progress Tracking** - Visual indicators for completion status

## Features

This PHP implementation preserves all the core features that make VSAQ great:

- **Zero Dependencies**: No frameworks, no build tools - just vanilla PHP and JavaScript
- **Client-Side First**: Auto-save to browser LocalStorage, works offline
- **Import/Export**: Easy sharing of questionnaires via JSON files
- **Conditional Logic**: Questions appear/hide based on previous answers
- **Progress Tracking**: Visual progress indicators
- **11 Question Types**: Line, box, checkbox, yes/no, radio, groups, info, tips, blocks, spacers
- **Pre-built Templates**: Professional security questionnaires included
- **Security-Focused**: XSS prevention, input validation

## Included Questionnaires

- **Web Application Security** (webapp.json) - Comprehensive web app security assessment
- **Infrastructure Security** (infrastructure.json) - Infrastructure and network security
- **Security & Privacy Programs** (security_privacy_programs.json) - Organizational security programs
- **Physical & Datacenter Security** (physical_and_datacenter.json) - Physical security controls
- **Test Template** (test_template.json) - Example template for learning

## Installation

### Requirements

- PHP 7.0 or higher
- A web server (Apache, Nginx, or PHP's built-in server)

### Quick Start

1. **Clone or download this repository**

2. **Using PHP's built-in server** (easiest for local testing):

```bash
cd php-vsaq
php -S localhost:8000
```

3. **Open in browser**:

```
http://localhost:8000/php-vsaq/admin/
```

4. **Register the first admin**:
   - Enter a username
   - Click "Register New Admin"
   - Follow your browser's passkey setup prompts
   - Your passkey is now registered!

5. **Login with your passkey** and start creating questionnaires

That's it! No build process, no dependencies to install.

## Admin Workflow

### 1. Login with WebAuthn/Passkeys

- Secure, password-free authentication
- Uses device biometrics (fingerprint, face ID, etc.)
- All admins have equal permissions

### 2. Create Questionnaire Templates

- Create templates from scratch or duplicate existing ones
- Edit JSON content directly in the browser
- Archive templates that are no longer needed
- Templates can't be edited once a questionnaire has been sent

### 3. Send Questionnaires

- Select a template
- Add optional target name and email (for your reference)
- Generate a unique, secure link
- Share the link with the recipient

### 4. Recipients Fill Out Forms

- Access via unique link (no login required)
- Auto-save on every input change
- Conflict detection if opened in multiple tabs
- Visual progress indicator
- Submit when complete (locks the form)

### 5. Review Submitted Questionnaires

- View all sent questionnaires in the dashboard
- See submission status and answer counts
- Open any questionnaire to view answers (read-only)
- Unlock if changes are needed
- Cannot delete submitted questionnaires

### Production Deployment

#### Apache

1. Copy the `php-vsaq` directory to your web root (e.g., `/var/www/html/`)

2. Ensure `.htaccess` is enabled or configure your Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName vsaq.example.com
    DocumentRoot /var/www/html/php-vsaq

    <Directory /var/www/html/php-vsaq>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Route all requests through index.php
        FallbackResource /index.php
    </Directory>

    # Optional: Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
    </IfModule>
</VirtualHost>
```

3. Restart Apache:

```bash
sudo systemctl restart apache2
```

#### Nginx

Add this configuration to your Nginx site config:

```nginx
server {
    listen 80;
    server_name vsaq.example.com;
    root /var/www/html/php-vsaq;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Security: Deny access to data directory
    location ~ ^/data/ {
        deny all;
        return 404;
    }

    # Optional: Enable gzip compression
    gzip on;
    gzip_types text/css application/javascript application/json;
}
```

Restart Nginx:

```bash
sudo systemctl restart nginx
```

## Usage

### Basic Workflow

1. **Select a questionnaire** from the dropdown menu
2. **Answer the questions** - your answers are auto-saved to browser storage
3. **Export answers** when complete using the "Export Answers" button
4. **Share the JSON file** with reviewers
5. **Reviewers import** the JSON file to review answers

### Creating Custom Questionnaires

Questionnaires are defined in JSON format. Create a new file in the `questionnaires/` directory:

```json
{
  "name": "My Custom Questionnaire",
  "description": "A custom security assessment",
  "items": [
    {
      "type": "block",
      "text": "Section 1: Basic Information",
      "items": [
        {
          "type": "line",
          "id": "company_name",
          "text": "Company Name",
          "required": true
        },
        {
          "type": "yesno",
          "id": "has_security_team",
          "text": "Do you have a dedicated security team?",
          "yes": [
            {
              "type": "line",
              "id": "security_team_size",
              "text": "How many people are on the security team?"
            }
          ]
        }
      ]
    }
  ]
}
```

### Question Types

- **block** - Container for grouping questions
- **info** - Informational text
- **tip** - Warning/tip with severity levels (critical, high, medium)
- **spacer** - Visual spacing
- **line** - Single-line text input
- **box** - Multi-line text area
- **check** - Checkbox
- **yesno** - Yes/No question with conditional follow-ups
- **radio** - Radio button group
- **radiogroup** - Group of radio items
- **checkgroup** - Group of checkboxes

### Conditional Logic

Use the `cond` property to show/hide questions based on answers:

```json
{
  "type": "line",
  "id": "backup_frequency",
  "text": "How often are backups performed?",
  "cond": {
    "has_backups": "yes"
  }
}
```

Complex conditions:

```json
{
  "cond": {
    "and": [
      {"environment": "production"},
      {"or": [
        {"security_review": "yes"},
        {"compliance_required": "yes"}
      ]}
    ]
  }
}
```

## File Structure

```
php-vsaq/
├── index.php                 # Main entry point & API router
├── src/
│   ├── Database.php          # SQLite database setup
│   ├── WebAuthn.php          # WebAuthn authentication
│   └── data/                 # Database files (auto-created, gitignored)
├── admin/
│   ├── index.html            # Admin dashboard
│   ├── admin.css             # Admin styles
│   └── admin.js              # Admin interface logic
├── public/
│   ├── fill.html             # Form filling page
│   ├── js/
│   │   ├── vsaq.js           # Original client-side (deprecated)
│   │   └── fill.js           # Server-backed form filling
│   └── css/
│       ├── vsaq.css          # Base styling
│       └── fill.css          # Fill form styles
├── questionnaires/           # Questionnaire templates (JSON)
│   ├── webapp.json
│   ├── infrastructure.json
│   ├── security_privacy_programs.json
│   ├── physical_and_datacenter.json
│   └── test_template.json
└── README.md
```

## API Endpoints

### Authentication

- `POST /api/auth/register/options` - Get WebAuthn registration options
- `POST /api/auth/register/verify` - Verify WebAuthn registration
- `POST /api/auth/login/options` - Get WebAuthn login options
- `POST /api/auth/login/verify` - Verify WebAuthn login
- `POST /api/auth/logout` - Logout current session
- `GET /api/auth/check` - Check authentication status

### Admin - Templates

- `GET /api/admin/templates` - List all templates
- `GET /api/admin/templates/{id}` - Get specific template
- `POST /api/admin/templates` - Create new template
- `PUT /api/admin/templates/{id}` - Update template
- `DELETE /api/admin/templates/{id}` - Delete template
- `POST /api/admin/templates/{id}/duplicate` - Duplicate template
- `POST /api/admin/templates/{id}/archive` - Archive/unarchive template

### Admin - Questionnaire Instances

- `GET /api/admin/instances` - List all sent questionnaires
- `GET /api/admin/instances/{id}` - Get specific instance
- `POST /api/admin/instances` - Create new instance (generate link)
- `DELETE /api/admin/instances/{id}` - Delete instance
- `POST /api/admin/instances/{id}/unlock` - Unlock submitted instance

### Form Filling

- `GET /api/fill/{uniqueLink}` - Get questionnaire and answers
- `POST /api/fill/{uniqueLink}/save` - Save individual answer
- `POST /api/fill/{uniqueLink}/submit` - Submit questionnaire (locks it)

## Security Considerations

### Authentication

- **WebAuthn Only**: No passwords - uses device-based passkeys
- **Session Management**: Secure HTTP-only cookies, 7-day expiration
- **All Admins Equal**: No role hierarchy, all admins have full access

### Data Protection

- **Server-Side Storage**: All answers stored in SQLite database
- **Auto-Save**: Changes saved to server immediately
- **Conflict Detection**: Prevents data loss from concurrent editing
- **Form Locking**: Submitted forms are locked server-side
- **Input Validation**: All inputs sanitized and validated

### Production Recommendations

1. **Use HTTPS**: Always run in production with SSL/TLS
2. **Secure Cookies**: Set `secure` flag on cookies (requires HTTPS)
3. **Database Backup**: Regular backups of `src/data/vsaq.db`
4. **File Permissions**: Restrict database file permissions (0600)
5. **WebAuthn Domain**: Ensure `rpId` matches your domain
6. **Rate Limiting**: Consider adding rate limiting to API endpoints

### Known Limitations

- **WebAuthn Verification**: Simplified implementation - signature verification is not cryptographically validated
- **For Production**: Consider using a proper WebAuthn library like `web-auth/webauthn-lib`
- **SQLite Concurrent Writes**: May have issues under very high load - consider PostgreSQL/MySQL for high traffic

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Any modern browser with ES6 and LocalStorage support

## Differences from Original VSAQ

This PHP implementation simplifies the original while keeping core functionality:

### Removed:
- Google Closure Tools (Compiler, Library, Templates)
- Complex build system (Java, Maven, Ant, Protocol Buffers)
- Python development server
- Advanced optimizations and minification

### Kept:
- All 11 question types
- Conditional logic
- Auto-save functionality
- Import/export capabilities
- JSON template format
- All pre-built questionnaires
- Progress tracking
- Security focus

### Benefits:
- **No dependencies** - just PHP and vanilla JavaScript
- **No build process** - deploy and run immediately
- **Easier to customize** - readable, straightforward code
- **Smaller footprint** - ~1000 lines vs ~10,000+ lines
- **Easier maintenance** - no complex toolchain

## Contributing

This is a simplified version of Google's VSAQ. For the original project, see:
https://github.com/google/vsaq

## License

This implementation maintains the same spirit as the original VSAQ project.

## Support

For issues or questions, please refer to the original VSAQ documentation:
https://github.com/google/vsaq

## Acknowledgments

Based on Google's VSAQ (Vendor Security Assessment Questionnaire).
This is an unofficial, simplified PHP implementation.

# VSAQ PHP - Vendor Security Assessment Questionnaire

A simple, lightweight PHP rewrite of Google's VSAQ (Vendor Security Assessment Questionnaire) with zero dependencies.

## What is VSAQ?

VSAQ is an interactive questionnaire application designed for security assessments of third-party vendors and service providers. It helps organizations:

- Conduct security reviews of vendors
- Gather structured security information
- Perform self-assessments
- Learn about web application security issues

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
http://localhost:8000
```

That's it! No build process, no dependencies to install.

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
├── index.php              # Main entry point & API router
├── public/
│   ├── index.html         # Main UI
│   ├── js/
│   │   └── vsaq.js        # Client-side JavaScript
│   └── css/
│       └── vsaq.css       # Styling
├── questionnaires/        # Questionnaire templates (JSON)
│   ├── webapp.json
│   ├── infrastructure.json
│   ├── security_privacy_programs.json
│   ├── physical_and_datacenter.json
│   └── test_template.json
├── data/                  # Auto-created for backend storage (optional)
│   └── answers/          # Saved answers (if using backend storage)
└── README.md
```

## Security Considerations

1. **Data Storage**: By default, answers are stored only in browser LocalStorage (client-side)
2. **Backend Storage**: The optional backend storage (`/api/save` endpoint) should be secured with authentication in production
3. **Input Validation**: All user inputs are sanitized
4. **Path Traversal**: Questionnaire paths are validated to prevent directory traversal
5. **HTTPS**: Always use HTTPS in production to protect data in transit

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

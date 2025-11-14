# VSAQ PHP Edition

A PHP remake of Google's VSAQ (Vendor Security Assessment Questionnaire) with an interactive admin panel for creating and managing security questionnaires.

## Features

- **Interactive Questionnaire Editor** with live preview
- **Magic Link System** for passwordless access to questionnaires
- **Six Pre-loaded Sample Questionnaires** including:
  - Web Application Security Assessment
  - Infrastructure Security Assessment
  - Physical and Datacenter Security
  - Security and Privacy Programs
  - Basic Security Questionnaire
  - Employee Onboarding Security
- **Responsive Design** with modern UI
- **Auto-save Functionality** for questionnaire responses
- **Proper HTML Escaping** to prevent XSS attacks
- **SQLite Database** for easy deployment
- **No Authentication Required** for magic links (perfect for vendor assessments)

## Installation

### Requirements

- PHP 8.0 or higher
- PHP SQLite3 extension
- Apache or Nginx web server
- SQLite3

### Setup

1. **Install Dependencies** (Ubuntu/Debian):
   ```bash
   sudo apt-get install -y php php-sqlite3 sqlite3 apache2 libapache2-mod-php
   ```

2. **Deploy the Application**:
   ```bash
   # Copy to web server directory
   sudo ln -sf /path/to/php-vsaq /var/www/html/php-vsaq

   # Set permissions
   sudo chown -R www-data:www-data /path/to/php-vsaq
   sudo chmod -R 755 /path/to/php-vsaq
   sudo chmod 666 /path/to/php-vsaq/vsaq.db
   ```

3. **Seed the Database** (optional, creates sample questionnaires):
   ```bash
   cd /path/to/php-vsaq
   php seed_data.php
   ```

4. **Access the Application**:
   - Public view: `http://yourserver/php-vsaq/public/`
   - Admin panel: `http://yourserver/php-vsaq/admin/`

## Usage

### For Administrators

1. **Creating a Questionnaire**:
   - Go to the admin panel (`/php-vsaq/admin/`)
   - Click "Create New Questionnaire"
   - Enter title, slug, and description
   - Edit the JSON template on the left
   - Use the live preview on the right to see changes
   - Click "Save Questionnaire"

2. **Generating Response Links**:
   - From the admin panel, click "Create Response Link" next to any questionnaire
   - Copy the generated magic link
   - Send the link to the vendor/respondent

3. **Managing Questionnaires**:
   - View all questionnaires in the admin panel
   - Edit, delete, or create response links for each

### For Respondents

1. Open the magic link provided by the administrator
2. Fill out the questionnaire
3. Click "Save Responses" to submit
4. Responses are automatically saved (can return to the link later)

## Demo Questionnaire

After running `seed_data.php`, a demo questionnaire link is created. You can find it in the output:

```
Demo Link: /php-vsaq/public/index.php?link=demo_XXXXXXXXXXXXXXXX
```

This link provides instant access to a sample questionnaire without any authentication!

## Questionnaire JSON Format

Questionnaires are defined using JSON templates. Example:

```json
{
  "questionnaire": [
    {
      "type": "block",
      "text": "Section Title",
      "id": "section_id",
      "items": [
        {
          "type": "line",
          "text": "Question text",
          "id": "question_id",
          "required": true
        },
        {
          "type": "box",
          "text": "Long answer question",
          "id": "long_answer_id"
        },
        {
          "type": "yesno",
          "text": "Yes/No question",
          "id": "yesno_id",
          "required": true
        },
        {
          "type": "check",
          "text": "Checkbox option",
          "id": "checkbox_id"
        },
        {
          "type": "radiogroup",
          "text": "Multiple choice question",
          "id": "radio_id",
          "choices": ["Option 1", "Option 2", "Option 3"],
          "required": true
        },
        {
          "type": "info",
          "text": "Informational text displayed to the user"
        }
      ]
    }
  ]
}
```

### Supported Question Types

- **block**: Container for grouping questions
- **line**: Single-line text input
- **box**: Multi-line textarea
- **yesno**: Yes/No radio buttons
- **check**: Checkbox
- **radio** / **radiogroup**: Multiple choice radio buttons
- **info**: Informational text (not a question)
- **upload**: File upload field

### Question Attributes

- `type`: (required) Question type
- `text`: (required) Question or section text
- `id`: (required for value items) Unique identifier
- `required`: (optional) Whether the field is mandatory
- `placeholder`: (optional) Placeholder text for inputs
- `choices`: (required for radiogroup) Array of choices

## Security Features

- **HTML Escaping**: All user input is properly escaped using `htmlspecialchars()` to prevent XSS
- **SQL Injection Protection**: Uses prepared statements for all database queries
- **No Authentication for Magic Links**: Links are cryptographically secure (64-character hex strings)
- **Read-only after submission**: Responses can be edited using the same magic link

## Directory Structure

```
php-vsaq/
├── admin/              # Admin panel
│   ├── index.php       # Questionnaire list
│   ├── editor.php      # Interactive editor
│   └── preview.php     # AJAX preview endpoint
├── public/             # Public-facing pages
│   ├── index.php       # Questionnaire viewer
│   └── style.css       # Styles
├── inc/                # PHP includes
│   ├── config.php      # Database configuration
│   └── renderer.php    # Questionnaire renderer class
├── schema.sql          # Database schema
├── seed_data.php       # Sample data seeder
├── vsaq.db             # SQLite database (created on first run)
└── README.md           # This file
```

## Differences from Original VSAQ

The original Google VSAQ is a client-side JavaScript application using Google Closure Compiler. This PHP remake:

1. **Server-side rendering**: PHP generates HTML instead of JavaScript
2. **Database-backed**: Uses SQLite instead of localStorage
3. **Magic links**: Passwordless access system for vendors
4. **Interactive editor**: Built-in admin panel with live preview
5. **Simpler deployment**: No complex build process required
6. **Pre-populated samples**: Includes ready-to-use questionnaires

## License

This is a reimplementation inspired by Google's VSAQ project. The original VSAQ templates used here are from the Google VSAQ repository.

## Contributing

Contributions are welcome! Areas for improvement:

- AJAX auto-save functionality
- Export responses as PDF
- Email notifications
- Response analytics
- Multi-language support
- Conditional logic for questions

## Troubleshooting

### Database permission errors

```bash
sudo chmod 666 /path/to/php-vsaq/vsaq.db
sudo chown www-data:www-data /path/to/php-vsaq/vsaq.db
```

### Apache not serving PHP

```bash
sudo a2enmod php8.4
sudo service apache2 restart
```

### Cannot write to database

Make sure the directory containing vsaq.db is writable:
```bash
sudo chmod 775 /path/to/php-vsaq
sudo chown www-data:www-data /path/to/php-vsaq
```

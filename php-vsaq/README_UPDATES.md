# VSAQ PHP - Recent Updates

## Overview
The VSAQ editor and admin interface has been improved with several new features:

1. Sample questionnaire templates
2. Demo questionnaire with magic link
3. Live preview in template editor
4. Fixed HTML rendering in questionnaires

## Features Added

### 1. Sample Questionnaire Templates
Six pre-built questionnaire templates are now available:
- Web Application Security
- Infrastructure Security
- Security and Privacy Programs
- Physical and Datacenter Security
- Test Template (Simple)
- Test Template Extension

### 2. Load Sample Templates
A new "Load Sample Templates" button in the admin interface allows you to:
- Import all 6 sample questionnaires with one click
- Automatically creates a demo questionnaire instance
- Provides a magic link for testing without authentication

### 3. Live Preview in Template Editor
The template editor now includes:
- Split-view editor with JSON on left, preview on right
- Live preview that updates as you type (500ms debounce)
- Toggle to show/hide preview
- Supports all VSAQ question types

### 4. HTML Rendering Fix
Fixed HTML rendering in questionnaires to properly display:
- Code blocks: `<code>...</code>`
- Bold text: `<b>...</b>`
- Lists: `<ul>`, `<ol>`, `<li>`
- Other safe HTML tags

## Usage

### Getting Started
1. Access the admin interface at `/php-vsaq/admin/`
2. Register an admin account (first user only)
3. Click "Load Sample Templates" to populate the database
4. A demo link will be generated automatically

### Using the Demo Link
The demo questionnaire can be accessed without authentication:
- Format: `/php-vsaq/f/demo-XXXXX`
- Pre-filled with "Web Application Security" template
- Great for testing and demonstrations
- No login required

### Creating Templates
1. Click "Create New Template"
2. Enter name and description
3. Edit JSON in the left pane
4. Watch live preview in the right pane
5. Toggle "Show Live Preview" to hide/show preview
6. Click "Save Template"

### Template Editor Tips
- The preview updates automatically after 500ms of typing
- Invalid JSON will show an error message in the preview pane
- Use the checkbox to toggle preview on/off
- The preview supports all VSAQ question types

## API Endpoint Added

### POST /api/admin/populate-samples
Populates the database with sample questionnaires and creates a demo instance.

**Authentication:** Required (admin session)

**Response:**
```json
{
  "success": true,
  "imported": ["Template 1", "Template 2"],
  "skipped": ["Already Existing Template"],
  "errors": [],
  "demo_link": "/php-vsaq/f/demo-abc123"
}
```

## Files Modified

### Backend
- `/php-vsaq/index.php` - Added populate-samples endpoint and handler

### Frontend
- `/php-vsaq/admin/index.html` - Added preview pane and load samples button
- `/php-vsaq/admin/admin.css` - Added styles for editor and preview
- `/php-vsaq/admin/admin.js` - Added preview logic and sample loading
- `/php-vsaq/public/js/fill.js` - Added comments about HTML rendering

## Testing

To test the implementation:
1. Visit `/php-vsaq/admin/`
2. Register as admin (if needed)
3. Click "Load Sample Templates"
4. Copy the demo link from the result
5. Open the demo link in a new tab
6. Fill out the questionnaire to verify rendering

## Notes

- The populate-samples endpoint is idempotent (can be run multiple times)
- Templates with existing names will be skipped
- Only one demo instance per template is created
- The formatText function in fill.js allows HTML passthrough for admin-created content
- All sample questionnaires are sourced from the `/questionnaires/` directory

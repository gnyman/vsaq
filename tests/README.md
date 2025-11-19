# VSAQ Automated Testing

This directory contains automated UI tests for the VSAQ application using Playwright.

## Setup

1. Install dependencies:
```bash
npm install
npx playwright install
```

## Running Tests

Run all tests:
```bash
npm test
```

Run tests in headed mode (see browser):
```bash
npm run test:headed
```

Run tests with debug mode:
```bash
npm run test:debug
```

Run tests with UI mode (interactive):
```bash
npm run test:ui
```

## Test Coverage

### Editor Tests (`editor.spec.js`)

Tests for the visual questionnaire editor:

- **Interface Loading**: Verifies all UI elements load correctly
- **Question Types**: Tests adding all 11 question types:
  - Block (container for grouping)
  - Info (informational text)
  - Tip (warnings with severity levels)
  - Spacer (visual spacing)
  - Line (single-line text input)
  - Box (multi-line text area)
  - Check (single checkbox)
  - Radiogroup (radio button group)
  - Checkgroup (checkbox group)
  - Yesno (yes/no with conditional follow-ups)
  - Radio (single radio button)

- **Validation**:
  - Duplicate ID detection
  - Required field validation
  - ID format validation (alphanumeric, underscore, hyphen only)

- **Editing**:
  - Edit existing questions
  - Delete questions
  - Cancel without saving

- **Features**:
  - Toggle preview pane
  - Export JSON
  - Conditional logic fields

## Prerequisites

Before running tests, ensure:

1. PHP is installed and available
2. The application can run on `http://localhost:8000`
3. SQLite is available (for database)

The Playwright config will automatically start a PHP development server before running tests.

## Continuous Integration

Tests are configured to run with retries in CI environments. Set `CI=true` to enable CI mode.

## Test Results

Test results are saved to:
- `test-results/` - Raw test results
- `playwright-report/` - HTML report (open with `npx playwright show-report`)

## Debugging

To debug a specific test:
```bash
npx playwright test --debug -g "test name pattern"
```

To see traces for failed tests:
```bash
npx playwright show-report
```

## Security Notes

Tests run against a local development server. Do not run tests against production environments.

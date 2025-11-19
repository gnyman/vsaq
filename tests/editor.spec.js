// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Questionnaire Editor', () => {

  test.beforeEach(async ({ page }) => {
    // Navigate to editor
    await page.goto('/admin/edit.php');

    // Wait for editor to load
    await expect(page.locator('h1')).toContainText('Questionnaire Editor');
  });

  test('should load editor interface correctly', async ({ page }) => {
    // Check main elements are present
    await expect(page.locator('#template-name')).toBeVisible();
    await expect(page.locator('#template-description')).toBeVisible();
    await expect(page.locator('#questionnaire-tree')).toBeVisible();
    await expect(page.locator('#preview-pane')).toBeVisible();

    // Check all question type buttons are present
    const questionTypes = [
      'Block', 'Info', 'Tip', 'Spacer', 'Single Line',
      'Text Box', 'Checkbox', 'Radio Group', 'Check Group', 'Yes/No', 'Radio'
    ];

    for (const type of questionTypes) {
      await expect(page.locator('.add-question', { hasText: type })).toBeVisible();
    }
  });

  test('should add a "line" question type', async ({ page }) => {
    // Click "Single Line" button
    await page.click('.add-question[data-type="line"]');

    // Modal should open
    await expect(page.locator('#edit-modal')).toHaveClass(/active/);
    await expect(page.locator('#modal-title')).toContainText('Add line Question');

    // Fill in the form
    await page.fill('#field-id', 'test_question_1');
    await page.fill('#field-text', 'What is your name?');
    await page.check('#field-required');

    // Save question
    await page.click('#save-question');

    // Modal should close
    await expect(page.locator('#edit-modal')).not.toHaveClass(/active/);

    // Question should appear in tree
    await expect(page.locator('.question-item')).toBeVisible();
    await expect(page.locator('.question-type')).toContainText('line');
    await expect(page.locator('.question-id')).toContainText('test_question_1');
  });

  test('should add a "block" question type with nested items', async ({ page }) => {
    // Add block
    await page.click('.add-question[data-type="block"]');
    await expect(page.locator('#edit-modal')).toHaveClass(/active/);

    await page.fill('#field-text', 'Section 1');
    await page.click('#save-question');

    // Block should be added
    await expect(page.locator('.question-type')).toContainText('block');
    await expect(page.locator('.question-content')).toContainText('Section 1');
  });

  test('should add a "radiogroup" question with choices', async ({ page }) => {
    // Add radiogroup
    await page.click('.add-question[data-type="radiogroup"]');
    await expect(page.locator('#edit-modal')).toHaveClass(/active/);

    // Fill in basic info
    await page.fill('#field-id', 'radio_test');
    await page.fill('#field-text', 'Select an option');

    // Add choices
    await page.click('.add-choice-btn');
    await page.fill('[data-choice-index="0"][data-choice-field="id"]', 'option1');
    await page.fill('[data-choice-index="0"][data-choice-field="label"]', 'Option 1');

    await page.click('.add-choice-btn');
    await page.fill('[data-choice-index="1"][data-choice-field="id"]', 'option2');
    await page.fill('[data-choice-index="1"][data-choice-field="label"]', 'Option 2');

    // Save
    await page.click('#save-question');

    // Verify it was added
    await expect(page.locator('.question-id')).toContainText('radio_test');
  });

  test('should add a "tip" question with severity', async ({ page }) => {
    // Add tip
    await page.click('.add-question[data-type="tip"]');
    await expect(page.locator('#edit-modal')).toHaveClass(/active/);

    await page.fill('#field-id', 'warning_1');
    await page.fill('#field-text', 'This is a warning message');
    await page.selectOption('#field-severity', 'critical');
    await page.fill('#field-why', 'This is important');
    await page.check('#field-warn');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('warning_1');
  });

  test('should add a "checkgroup" question with multiple choices', async ({ page }) => {
    // Add checkgroup
    await page.click('.add-question[data-type="checkgroup"]');

    await page.fill('#field-id', 'check_test');

    // Add multiple choices
    await page.click('.add-choice-btn');
    await page.fill('[data-choice-index="0"][data-choice-field="id"]', 'check1');
    await page.fill('[data-choice-index="0"][data-choice-field="label"]', 'Check 1');

    await page.click('.add-choice-btn');
    await page.fill('[data-choice-index="1"][data-choice-field="id"]', 'check2');
    await page.fill('[data-choice-index="1"][data-choice-field="label"]', 'Check 2');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('check_test');
  });

  test('should add a "box" (textarea) question', async ({ page }) => {
    await page.click('.add-question[data-type="box"]');

    await page.fill('#field-id', 'textarea_test');
    await page.fill('#field-text', 'Enter detailed description');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('textarea_test');
  });

  test('should add a "check" (checkbox) question', async ({ page }) => {
    await page.click('.add-question[data-type="check"]');

    await page.fill('#field-id', 'checkbox_test');
    await page.fill('#field-text', 'I agree to terms');
    await page.check('#field-required');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('checkbox_test');
  });

  test('should add an "info" question', async ({ page }) => {
    await page.click('.add-question[data-type="info"]');

    await page.fill('#field-text', 'This is informational text');

    await page.click('#save-question');

    await expect(page.locator('.question-type')).toContainText('info');
    await expect(page.locator('.question-content')).toContainText('This is informational text');
  });

  test('should add a "spacer" question', async ({ page }) => {
    await page.click('.add-question[data-type="spacer"]');

    // Spacer doesn't need any fields
    await page.click('#save-question');

    await expect(page.locator('.question-type')).toContainText('spacer');
  });

  test('should add a "yesno" question', async ({ page }) => {
    await page.click('.add-question[data-type="yesno"]');

    await page.fill('#field-id', 'yesno_test');
    await page.fill('#field-text', 'Do you have this feature?');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('yesno_test');
  });

  test('should add a "radio" question', async ({ page }) => {
    await page.click('.add-question[data-type="radio"]');

    await page.fill('#field-id', 'radio_single');
    await page.fill('#field-text', 'Single radio option');

    await page.click('#save-question');

    await expect(page.locator('.question-id')).toContainText('radio_single');
  });

  test('should detect duplicate IDs', async ({ page }) => {
    // Add first question
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'duplicate_id');
    await page.fill('#field-text', 'First question');
    await page.click('#save-question');

    // Add second question with same ID
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'duplicate_id');
    await page.fill('#field-text', 'Second question');
    await page.click('#save-question');

    // Try to save template
    await page.fill('#template-name', 'Test Template');
    await page.click('#save-template');

    // Should show validation error
    await expect(page.locator('#validation-errors')).toBeVisible();
    await expect(page.locator('#error-list')).toContainText('Duplicate ID: duplicate_id');
  });

  test('should validate required fields', async ({ page }) => {
    // Add question without required ID
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-text', 'Question without ID');
    // Don't fill ID
    await page.click('#save-question');

    // Try to save template
    await page.fill('#template-name', 'Test Template');
    await page.click('#save-template');

    // Should show validation error
    await expect(page.locator('#validation-errors')).toBeVisible();
  });

  test('should edit existing question', async ({ page }) => {
    // Add a question first
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'edit_test');
    await page.fill('#field-text', 'Original text');
    await page.click('#save-question');

    // Click edit button
    await page.click('.question-item .question-actions button:has-text("Edit")');

    // Modal should open with existing data
    await expect(page.locator('#edit-modal')).toHaveClass(/active/);
    await expect(page.locator('#field-id')).toHaveValue('edit_test');
    await expect(page.locator('#field-text')).toHaveValue('Original text');

    // Change text
    await page.fill('#field-text', 'Modified text');
    await page.click('#save-question');

    // Verify change
    await expect(page.locator('.question-content')).toContainText('Modified text');
  });

  test('should delete question', async ({ page }) => {
    // Add a question
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'delete_test');
    await page.fill('#field-text', 'To be deleted');
    await page.click('#save-question');

    // Verify it exists
    await expect(page.locator('.question-item')).toBeVisible();

    // Delete it
    page.on('dialog', dialog => dialog.accept());
    await page.click('.question-item .delete-btn');

    // Should show empty state
    await expect(page.locator('.empty-state')).toBeVisible();
  });

  test('should toggle preview pane', async ({ page }) => {
    // Preview should be visible initially
    await expect(page.locator('#preview-pane')).toBeVisible();

    // Toggle it off
    await page.click('#preview-toggle');
    await expect(page.locator('#preview-pane')).toHaveClass(/hidden/);

    // Toggle it back on
    await page.click('#preview-toggle');
    await expect(page.locator('#preview-pane')).not.toHaveClass(/hidden/);
  });

  test('should export JSON', async ({ page }) => {
    // Add a question
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'export_test');
    await page.fill('#field-text', 'Export test');
    await page.click('#save-question');

    // Click export button
    const downloadPromise = page.waitForEvent('download');
    await page.click('#export-json');
    const download = await downloadPromise;

    // Verify download happened
    expect(download.suggestedFilename()).toBe('questionnaire.json');
  });

  test('should validate ID format', async ({ page }) => {
    // Add question with invalid ID (spaces, special chars)
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'invalid id!@#');
    await page.fill('#field-text', 'Test question');
    await page.click('#save-question');

    // Try to save
    await page.fill('#template-name', 'Test Template');
    await page.click('#save-template');

    // Should show validation error about invalid ID format
    await expect(page.locator('#validation-errors')).toBeVisible();
    await expect(page.locator('#error-list')).toContainText('Only letters, numbers, underscore, and hyphen allowed');
  });

  test('should cancel edit without saving', async ({ page }) => {
    // Add a question
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'cancel_test');
    await page.fill('#field-text', 'Original');
    await page.click('#save-question');

    // Open edit
    await page.click('.question-item .question-actions button:has-text("Edit")');

    // Make changes but cancel
    await page.fill('#field-text', 'Should not be saved');
    await page.click('#cancel-edit');

    // Verify original text remains
    await expect(page.locator('.question-content')).toContainText('Original');
  });

  test('should show preview of JSON structure', async ({ page }) => {
    // Add a question
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'preview_test');
    await page.fill('#field-text', 'Preview test');
    await page.click('#save-question');

    // Check preview shows JSON
    const preview = page.locator('#preview-content');
    await expect(preview).toContainText('"type": "line"');
    await expect(preview).toContainText('"id": "preview_test"');
  });

  test('should handle conditional logic fields', async ({ page }) => {
    // Add question with condition
    await page.click('.add-question[data-type="line"]');
    await page.fill('#field-id', 'conditional_test');
    await page.fill('#field-text', 'Conditional question');
    await page.fill('#field-cond', 'previous_q/yes');
    await page.click('#save-question');

    // Export and check the JSON contains condition
    const preview = page.locator('#preview-content');
    await expect(preview).toContainText('"cond": "previous_q/yes"');
  });

});

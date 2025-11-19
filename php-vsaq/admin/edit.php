<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire Editor - VSAQ</title>
    <link rel="stylesheet" href="edit.css">
</head>
<body>
    <div class="editor-container">
        <header class="editor-header">
            <div class="header-left">
                <h1>Questionnaire Editor</h1>
                <div class="template-info">
                    <input type="text" id="template-name" placeholder="Template Name" required>
                    <textarea id="template-description" placeholder="Template Description" rows="2"></textarea>
                </div>
            </div>
            <div class="header-actions">
                <button id="import-json" class="btn btn-secondary">Import JSON</button>
                <button id="export-json" class="btn btn-secondary">Export JSON</button>
                <button id="preview-toggle" class="btn btn-secondary">Toggle Preview</button>
                <button id="save-template" class="btn btn-primary">Save Template</button>
                <a href="./" class="btn btn-secondary">Back to Admin</a>
            </div>
        </header>

        <div class="editor-main">
            <div class="editor-pane">
                <div class="toolbar">
                    <h3>Add Question</h3>
                    <div class="question-types">
                        <button class="add-question" data-type="block">Block</button>
                        <button class="add-question" data-type="info">Info</button>
                        <button class="add-question" data-type="tip">Tip</button>
                        <button class="add-question" data-type="spacer">Spacer</button>
                        <button class="add-question" data-type="line">Single Line</button>
                        <button class="add-question" data-type="box">Text Box</button>
                        <button class="add-question" data-type="check">Checkbox</button>
                        <button class="add-question" data-type="radiogroup">Radio Group</button>
                        <button class="add-question" data-type="checkgroup">Check Group</button>
                        <button class="add-question" data-type="yesno">Yes/No</button>
                        <button class="add-question" data-type="radio">Radio</button>
                    </div>
                </div>

                <div class="questionnaire-tree" id="questionnaire-tree">
                    <!-- Question items will be added here -->
                </div>
            </div>

            <div class="preview-pane" id="preview-pane">
                <h3>Live Preview</h3>
                <div id="preview-content" class="preview-content">
                    <!-- Preview will be rendered here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <h2 id="modal-title">Edit Question</h2>
            <div id="edit-form">
                <!-- Form fields will be dynamically inserted here -->
            </div>
            <div class="modal-actions">
                <button id="save-question" class="btn btn-primary">Save</button>
                <button id="cancel-edit" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Validation Errors -->
    <div id="validation-errors" class="validation-errors" style="display: none;">
        <h3>Validation Errors</h3>
        <ul id="error-list"></ul>
        <button id="close-errors" class="btn btn-secondary">Close</button>
    </div>

    <script src="edit.js"></script>
</body>
</html>

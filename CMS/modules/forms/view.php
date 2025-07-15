<!-- File: view.php -->
<div class="content-section" id="forms">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Forms</div>
            <div class="table-actions">
                <button class="btn btn-primary" id="newFormBtn">+ New Form</button>
            </div>
        </div>
        <table class="data-table" id="formsTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Fields</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="form-card" id="formBuilderCard" style="margin-top:20px; display:none;">
        <h3 id="formBuilderTitle" style="margin-bottom:15px;">Add Form</h3>
        <form id="formBuilderForm">
            <input type="hidden" name="id" id="formId">
            <div class="form-group">
                <label class="form-label" for="formName">Form Name</label>
                <input type="text" class="form-input" id="formName" name="name" required>
            </div>
            <div class="builder-container">
                <div id="fieldPalette">
                    <div class="palette-item" data-type="text" role="button" tabindex="0">Text Input</div>
                    <div class="palette-item" data-type="email" role="button" tabindex="0">Email</div>
                    <div class="palette-item" data-type="password" role="button" tabindex="0">Password</div>
                    <div class="palette-item" data-type="number" role="button" tabindex="0">Number</div>
                    <div class="palette-item" data-type="date" role="button" tabindex="0">Date</div>
                    <div class="palette-item" data-type="textarea" role="button" tabindex="0">Textarea</div>
                    <div class="palette-item" data-type="select" role="button" tabindex="0">Select</div>
                    <div class="palette-item" data-type="checkbox" role="button" tabindex="0">Checkbox</div>
                    <div class="palette-item" data-type="radio" role="button" tabindex="0">Radio</div>
                    <div class="palette-item" data-type="file" role="button" tabindex="0">File</div>
                    <div class="palette-item" data-type="submit" role="button" tabindex="0">Submit Button</div>
                </div>
                <div class="builder-columns">
                    <ul id="formPreview" class="field-list" aria-label="Form preview" data-placeholder="Drop fields here"></ul>
                    <div id="fieldSettings" class="field-settings">
                        <p>Select a field to edit</p>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Form</button>
                <button type="button" class="btn btn-secondary" id="cancelFormEdit">Cancel</button>
            </div>
        </form>
    </div>
</div>

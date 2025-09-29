// File: forms.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }

    let currentField = null;
    let currentFormId = null;
    let currentFormName = '';
    let currentSubmissions = [];
    let lastSubmissionTrigger = null;
    let formsCache = [];

    const $drawer = $('#formBuilderDrawer');
    const $form = $('#formBuilderForm');
    const $formId = $('#formId');
    const $formName = $('#formName');
    const $formTitle = $('#formBuilderTitle');
    const $formPreview = $('#formPreview');
    const $cancelFormEdit = $('#cancelFormEdit');
    const $closeFormBuilder = $('#closeFormBuilder');
    const $formsGrid = $('#formsLibrary');
    const $formsEmptyState = $('#formsLibraryEmptyState');

    const FIELD_TYPE_LABELS = {
        text: 'Text input',
        email: 'Email',
        password: 'Password',
        number: 'Number',
        date: 'Date',
        textarea: 'Textarea',
        select: 'Select',
        checkbox: 'Checkbox',
        radio: 'Radio',
        file: 'File upload',
        submit: 'Submit button'
    };

    const FIELD_DEFAULT_LABELS = {
        text: 'Text input',
        email: 'Email address',
        password: 'Password',
        number: 'Number',
        date: 'Date',
        textarea: 'Message',
        select: 'Select an option',
        checkbox: 'Checkbox',
        radio: 'Radio choice',
        file: 'File upload',
        submit: 'Submit'
    };

    function slugifyName(value){
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function ensureUniqueName(base, $input){
        const existing = new Set();
        $('#formPreview .field-name').each(function(){
            if($input && this === $input[0]) return;
            const val = $(this).val().trim();
            if(val){
                existing.add(val);
            }
        });
        let candidate = base || 'field';
        if(!existing.has(candidate)){
            return candidate;
        }
        let counter = 2;
        let suffixless = candidate.replace(/_\d+$/, '');
        if(!suffixless){
            suffixless = 'field';
        }
        candidate = suffixless + '_' + counter;
        while(existing.has(candidate)){
            counter++;
            candidate = suffixless + '_' + counter;
        }
        return candidate;
    }

    function generateAutoName(label, $input){
        const base = slugifyName(label);
        return ensureUniqueName(base || 'field', $input);
    }

    function setManualNameFlag($input, manual){
        if(!$input || !$input.length) return;
        $input.data('manual', manual === true);
        if(manual){
            $input.attr('data-manual', 'true');
        } else {
            $input.removeAttr('data-manual');
        }
    }

    function isManualName($input){
        if(!$input || !$input.length) return false;
        return $input.data('manual') === true;
    }

    function showBuilderAlert(message, tone){
        const $alert = $('#formBuilderAlert');
        if(!$alert.length) return;
        const typeClass = tone === 'success' ? 'form-alert--success' : 'form-alert--error';
        $alert
            .removeClass('form-alert--error form-alert--success')
            .addClass(typeClass)
            .text(message)
            .show();
    }

    function hideBuilderAlert(){
        const $alert = $('#formBuilderAlert');
        if(!$alert.length) return;
        $alert.removeClass('form-alert--error form-alert--success').hide().text('');
    }

    function formatStatValue(value){
        if (value === null || typeof value === 'undefined') {
            return '—';
        }
        if (typeof value === 'number' && Number.isNaN(value)) {
            return '—';
        }
        if (typeof value === 'string') {
            const trimmed = value.trim();
            return trimmed === '' ? '—' : trimmed;
        }
        return value;
    }

    function applyFormStats(stats){
        if (Object.prototype.hasOwnProperty.call(stats, 'totalForms')) {
            $('#formsStatForms').text(formatStatValue(stats.totalForms));
        }
        if (Object.prototype.hasOwnProperty.call(stats, 'activeForms')) {
            $('#formsStatActive').text(formatStatValue(stats.activeForms));
        }
        if (Object.prototype.hasOwnProperty.call(stats, 'totalSubmissions')) {
            $('#formsStatSubmissions').text(formatStatValue(stats.totalSubmissions));
        }
        if (Object.prototype.hasOwnProperty.call(stats, 'recentSubmissions')) {
            $('#formsStatRecent').text(formatStatValue(stats.recentSubmissions));
        }
        if (Object.prototype.hasOwnProperty.call(stats, 'lastSubmission')) {
            $('#formsLastSubmission').text(formatStatValue(stats.lastSubmission));
        }
    }

    function bootstrapStatsFromDataset(){
        const dashboard = $('.forms-dashboard');
        if (!dashboard.length) {
            return;
        }
        applyFormStats({
            totalForms: Number(dashboard.data('total-forms')),
            activeForms: Number(dashboard.data('active-forms')),
            totalSubmissions: Number(dashboard.data('total-submissions')),
            recentSubmissions: Number(dashboard.data('recent-submissions')),
            lastSubmission: dashboard.data('last-submission')
        });
    }

    function getFormCardName($card){
        if(!$card || !$card.length){
            return '';
        }
        const stored = $card.data('formName');
        if(typeof stored === 'string' && stored.trim() !== ''){
            return stored;
        }
        const title = $card.find('.forms-card__title').first().text();
        return title ? title.trim() : '';
    }

    function refreshSubmissionStats(){
        $.getJSON('modules/forms/list_submissions.php', function(data){
            const submissions = Array.isArray(data) ? data : [];
            const activeFormIds = new Set();
            const now = Date.now();
            const THIRTY_DAYS = 30 * 24 * 60 * 60 * 1000;
            let latest = null;
            let recent = 0;

            submissions.forEach(function(entry){
                if (entry && typeof entry === 'object') {
                    if (entry.form_id) {
                        activeFormIds.add(entry.form_id);
                    }
                    const raw = entry.submitted_at || entry.created_at || entry.timestamp;
                    let timestamp = null;
                    if (typeof raw === 'number') {
                        timestamp = raw < 1e12 ? raw * 1000 : raw;
                    } else if (typeof raw === 'string') {
                        const parsed = Date.parse(raw);
                        if (!Number.isNaN(parsed)) {
                            timestamp = parsed;
                        }
                    }
                    if (timestamp !== null) {
                        if (!latest || timestamp > latest) {
                            latest = timestamp;
                        }
                        if (now - timestamp <= THIRTY_DAYS) {
                            recent++;
                        }
                    }
                }
            });

            applyFormStats({
                totalForms: formsCache.length,
                activeForms: activeFormIds.size,
                totalSubmissions: submissions.length,
                recentSubmissions: recent,
                lastSubmission: latest ? formatSubmissionDate(latest) : 'No submissions yet'
            });
        }).fail(function(){
            applyFormStats({
                totalForms: formsCache.length,
                activeForms: '—',
                totalSubmissions: '—',
                recentSubmissions: '—',
                lastSubmission: 'Unavailable'
            });
        });
    }

    function resetSubmissionsCard(message){
        currentFormId = null;
        currentFormName = '';
        currentSubmissions = [];
        closeSubmissionModal();
        const text = message || 'Select a form to view submissions';
        const placeholder = /[.!?]$/.test(text) ? text : text + '.';
        $formsGrid.find('.forms-library-item').removeClass('is-selected');
        $('#selectedFormName').text(text);
        $('#formSubmissionsCount').text('—');
        $('#formSubmissionsList')
            .attr('data-state', 'empty')
            .attr('aria-busy', 'false')
            .html('<div class="forms-submissions-empty">'+escapeHtml(placeholder)+'</div>');
    }

    function formatSubmissionDate(value){
        if(!value) return 'Unknown';

        function formatDate(date){
            if(Number.isNaN(date.getTime())){
                return null;
            }
            try {
                return date.toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            } catch (err) {
                return date.toString();
            }
        }

        const numeric = Number(value);
        if(!Number.isNaN(numeric) && numeric > 0){
            const date = new Date(numeric * (numeric < 1e12 ? 1000 : 1));
            const formatted = formatDate(date);
            if(formatted){
                return formatted;
            }
        }
        const date = new Date(value);
        const formatted = formatDate(date);
        if(formatted){
            return formatted;
        }
        return String(value);
    }

    function normalizeSubmissionValue(value){
        if(value === null || typeof value === 'undefined') return '';
        if(Array.isArray(value)){
            return value.map(v => String(v)).join(', ');
        }
        if(typeof value === 'object'){
            try {
                return JSON.stringify(value);
            } catch (err) {
                return '[object]';
            }
        }
        return String(value);
    }

    function normalizeSubmissionRecord(submission){
        const submittedAt = formatSubmissionDate(submission && (submission.submitted_at || submission.created_at || submission.timestamp));
        const fields = submission && submission.data && typeof submission.data === 'object' ? submission.data : {};
        const meta = submission && submission.meta && typeof submission.meta === 'object' ? Object.assign({}, submission.meta) : {};
        if(submission && submission.source && !meta.source){
            meta.source = submission.source;
        }
        if(submission && submission.id && !meta.id){
            meta.id = submission.id;
        }
        const fieldEntries = Object.keys(fields)
            .sort((a, b) => a.localeCompare(b))
            .map(function(key){
                return { key: key, value: normalizeSubmissionValue(fields[key]) };
            });
        const metaEntries = Object.keys(meta)
            .sort((a, b) => a.localeCompare(b))
            .map(function(key){
                return { key: key, value: normalizeSubmissionValue(meta[key]) };
            });
        const identifierEntry = metaEntries.find(function(entry){
            return entry.key && entry.key.toLowerCase() === 'id';
        });
        const sourceEntry = metaEntries.find(function(entry){
            return entry.key && entry.key.toLowerCase() === 'source';
        });
        return {
            submittedAt: submittedAt,
            fieldEntries: fieldEntries,
            metaEntries: metaEntries,
            identifier: identifierEntry ? identifierEntry.value : (submission && submission.id ? normalizeSubmissionValue(submission.id) : ''),
            source: sourceEntry ? sourceEntry.value : (submission && submission.source ? normalizeSubmissionValue(submission.source) : ''),
            raw: submission || {}
        };
    }

    function loadFormSubmissions(formId, formName){
        currentFormId = formId;
        currentFormName = formName;
        currentSubmissions = [];
        $formsGrid.find('.forms-library-item').removeClass('is-selected');
        $formsGrid.find('.forms-library-item').filter(function(){
            return $(this).data('id') == formId;
        }).addClass('is-selected');
        $('#selectedFormName').text(formName);
        $('#formSubmissionsCount').text('—');
        const $list = $('#formSubmissionsList');
        $list
            .attr('data-state', 'loading')
            .attr('aria-busy', 'true')
            .html('<div class="forms-submissions-empty">Loading submissions...</div>');
        $.getJSON('modules/forms/list_submissions.php', { form_id: formId }, function(data){
            const submissions = Array.isArray(data) ? data : [];
            currentSubmissions = submissions.map(normalizeSubmissionRecord);
            if(!currentSubmissions.length){
                $('#formSubmissionsCount').text('0 submissions');
                $list
                    .attr('data-state', 'empty')
                    .attr('aria-busy', 'false')
                    .html('<div class="forms-submissions-empty">No submissions yet.</div>');
                return;
            }
            const countLabel = currentSubmissions.length === 1 ? '1 submission' : currentSubmissions.length + ' submissions';
            $('#formSubmissionsCount').text(countLabel);
            $list.attr('data-state', 'ready').attr('aria-busy', 'false').empty();
            currentSubmissions.forEach(function(record, index){
                const $card = $('<article class="forms-submission-card" role="listitem"></article>');
                const $header = $('<div class="forms-submission-card__header"></div>');
                $header.append('<span class="forms-submission-card__timestamp">'+escapeHtml(record.submittedAt || 'Unknown')+'</span>');
                const $badges = $('<div class="forms-submission-card__badges"></div>');
                const identifier = record.identifier && String(record.identifier).trim();
                const source = record.source && String(record.source).trim();
                if(identifier){
                    $badges.append('<span class="forms-submission-card__badge forms-submission-card__badge--id">ID '+escapeHtml(identifier)+'</span>');
                }
                if(source){
                    $badges.append('<span class="forms-submission-card__badge">'+escapeHtml(source)+'</span>');
                }
                if(!$badges.children().length){
                    $badges.append('<span class="forms-submission-card__badge forms-submission-card__badge--muted">Submission '+escapeHtml(String(index + 1))+'</span>');
                }
                $header.append($badges);
                $card.append($header);

                const $preview = $('<div class="forms-submission-card__preview"></div>');
                const previewFields = record.fieldEntries.slice(0, 3);
                if(previewFields.length){
                    previewFields.forEach(function(entry){
                        const $field = $('<div class="forms-submission-card__field"></div>');
                        $field.append('<span class="forms-submission-card__field-label">'+escapeHtml(entry.key)+'</span>');
                        $field.append('<span class="forms-submission-card__field-value">'+escapeHtml(entry.value)+'</span>');
                        $preview.append($field);
                    });
                    if(record.fieldEntries.length > 3){
                        const remaining = record.fieldEntries.length - 3;
                        $preview.append('<span class="forms-submission-card__more">+'+escapeHtml(String(remaining))+' more field'+(remaining === 1 ? '' : 's')+'</span>');
                    }
                } else {
                    $preview.append('<div class="forms-submission-card__empty">No submission data provided.</div>');
                }
                $card.append($preview);

                const $actions = $('<div class="forms-submission-card__actions"></div>');
                const $button = $('<button type="button" class="a11y-btn a11y-btn--ghost forms-submission-view"><i class="fa-solid fa-eye" aria-hidden="true"></i><span>View details</span></button>');
                $button.on('click', function(){
                    openSubmissionModal(index, this);
                });
                $actions.append($button);
                $card.append($actions);

                $list.append($card);
            });
        }).fail(function(){
            $('#formSubmissionsCount').text('—');
            $list
                .attr('data-state', 'error')
                .attr('aria-busy', 'false')
                .html('<div class="forms-submissions-empty">Unable to load submissions.</div>');
        });
    }

    function buildModalDescription(record){
        const segments = [];
        if(currentFormName){
            segments.push('Form: ' + currentFormName);
        }
        if(record && record.submittedAt && record.submittedAt !== 'Unknown'){
            segments.push('Submitted ' + record.submittedAt);
        }
        return segments.join(' • ');
    }

    function openSubmissionModal(index, trigger){
        if(!currentSubmissions[index]){
            return;
        }
        const record = currentSubmissions[index];
        lastSubmissionTrigger = trigger ? $(trigger) : null;
        const $modal = $('#submissionDetailModal');
        const $body = $('#submissionModalBody').empty();
        const identifier = record.identifier && String(record.identifier).trim();
        $('#submissionModalTitle').text(identifier ? 'Submission ID ' + identifier : 'Submission details');
        $('#submissionModalDescription').text(buildModalDescription(record));

        if(record.fieldEntries.length){
            const $fieldsSection = $('<section class="forms-submission-modal__section"></section>');
            $fieldsSection.append('<h3 class="forms-submission-modal__section-title">Submitted fields</h3>');
            const $fieldList = $('<dl class="forms-submission-modal__details"></dl>');
            record.fieldEntries.forEach(function(entry){
                const $item = $('<div class="forms-submission-modal__detail"></div>');
                $item.append('<dt>'+escapeHtml(entry.key)+'</dt>');
                $item.append('<dd>'+escapeHtml(entry.value)+'</dd>');
                $fieldList.append($item);
            });
            $fieldsSection.append($fieldList);
            $body.append($fieldsSection);
        }

        if(record.metaEntries.length){
            const $metaSection = $('<section class="forms-submission-modal__section"></section>');
            $metaSection.append('<h3 class="forms-submission-modal__section-title">Metadata</h3>');
            const $metaList = $('<dl class="forms-submission-modal__details"></dl>');
            record.metaEntries.forEach(function(entry){
                const $item = $('<div class="forms-submission-modal__detail"></div>');
                $item.append('<dt>'+escapeHtml(entry.key)+'</dt>');
                $item.append('<dd>'+escapeHtml(entry.value)+'</dd>');
                $metaList.append($item);
            });
            $metaSection.append($metaList);
            $body.append($metaSection);
        }

        if(!$body.children().length){
            $body.append('<div class="forms-submission-modal__empty">No additional information available for this submission.</div>');
        }

        $modal.addClass('active').attr('aria-hidden', 'false');
        setTimeout(function(){
            $('#submissionModalClose').trigger('focus');
        }, 0);
    }

    function closeSubmissionModal(){
        const $modal = $('#submissionDetailModal');
        if(!$modal.hasClass('active')){
            return;
        }
        $modal.removeClass('active').attr('aria-hidden', 'true');
        $('#submissionModalTitle').text('Submission details');
        $('#submissionModalDescription').text('');
        $('#submissionModalBody')
            .empty()
            .append('<div class="forms-submission-modal__empty">Select a form submission to view the collected data.</div>');
        if(lastSubmissionTrigger && lastSubmissionTrigger.length){
            lastSubmissionTrigger.trigger('focus');
        }
        lastSubmissionTrigger = null;
    }

    function updatePreview($li){
        const type = $li.data('type');
        const labelValue = ($li.find('.field-label').val() || '').trim();
        const nameValue = ($li.find('.field-name').val() || '').trim();
        const hasRequiredToggle = $li.find('.field-required').length > 0;
        const required = hasRequiredToggle && $li.find('.field-required').is(':checked') ? ' required' : '';
        const options = ($li.find('.field-options-input').val() || '').trim();
        const labelHtml = labelValue ? escapeHtml(labelValue) : '<span class="field-placeholder">Label</span>';
        const nameAttr = nameValue ? ' name="'+escapeHtml(nameValue)+'"' : '';
        let html = '';
        switch(type){
            case 'textarea':
                html = '<div class="form-group"><label>'+labelHtml+'</label><textarea'+nameAttr+required+'></textarea></div>';
                break;
            case 'select':
                const opts = options.split(',').map(o=>o.trim()).filter(Boolean);
                html = '<div class="form-group"><label>'+labelHtml+'</label><select'+nameAttr+required+'>'+opts.map(o=>'<option>'+escapeHtml(o)+'</option>').join('')+'</select></div>';
                break;
            case 'checkbox':
            case 'radio':
                const optList = options.split(',').map(o=>o.trim()).filter(Boolean);
                if(optList.length){
                    html = '<div class="form-group"><label>'+labelHtml+'</label><div>';
                    optList.forEach(o=>{
                        const optionHtml = escapeHtml(o);
                        const valueAttr = ' value="'+optionHtml+'"';
                        html += '<label><input type="'+type+'"'+nameAttr+valueAttr+'> '+optionHtml+'</label> ';
                    });
                    html += '</div></div>';
                } else {
                    html = '<div class="form-group"><label><input type="'+type+'"'+nameAttr+required+'> '+labelHtml+'</label></div>';
                }
                break;
            case 'submit':
                html = '<div class="form-group"><button type="submit">'+(labelValue ? escapeHtml(labelValue) : 'Submit')+'</button></div>';
                break;
            default:
                const inputType = type === 'date' ? 'date' : type;
                html = '<div class="form-group"><label>'+labelHtml+'</label><input type="'+inputType+'"'+nameAttr+required+'></div>';
                break;
        }
        $li.find('.field-preview').html(html);
    }

    function selectField($li){
        if(currentField && currentField[0] === ($li && $li[0])) return;
        if(currentField){
            currentField.append($('#fieldSettings .field-body').hide());
            currentField.removeClass('selected');
        }
        currentField = $li;
        $('#fieldSettings').empty();
        if($li){
            currentField.addClass('selected');
            $('#fieldSettings').append(currentField.find('.field-body').show());
        } else {
            $('#fieldSettings').html('<p>Select a field in the preview to edit its settings.</p>');
        }
    }

    function focusFormBuilder(){
        if(!$formName.length){
            return;
        }
        try {
            $formName[0].focus({ preventScroll: true });
        } catch (err) {
            $formName[0].focus();
        }
    }

    function clearFormBuilder(){
        if($form.length && $form[0]){
            $form[0].reset();
        }
        $formId.val('');
        $formPreview.empty();
        selectField(null);
        hideBuilderAlert();
    }

    function openFormBuilder(title){
        if(typeof title === 'string' && title){
            $formTitle.text(title);
        }
        hideBuilderAlert();
        $drawer.attr('hidden', false).addClass('is-visible');
        setTimeout(focusFormBuilder, 80);
    }

    function closeFormBuilder(){
        $drawer.attr('hidden', true).removeClass('is-visible');
    }

    function dismissFormBuilder(){
        closeFormBuilder();
        clearFormBuilder();
    }

    function loadForms(){
        $.getJSON('modules/forms/list_forms.php', function(data){
            const forms = Array.isArray(data) ? data : [];
            formsCache = forms;
            applyFormStats({ totalForms: forms.length });
            if($formsGrid.length){
                $formsGrid.attr('aria-busy', 'true').empty().removeAttr('hidden');
            }
            if($formsEmptyState.length){
                $formsEmptyState.attr('hidden', true);
            }

            if(!forms.length){
                if($formsGrid.length){
                    $formsGrid.attr('aria-busy', 'false').attr('hidden', true).empty();
                }
                if($formsEmptyState.length){
                    $formsEmptyState.removeAttr('hidden');
                }
                refreshSubmissionStats();
                resetSubmissionsCard('Create a form to start collecting submissions');
                return;
            }

            forms.forEach(function(f){
                const fieldCount = Array.isArray(f.fields) ? f.fields.length : 0;
                const fieldLabel = fieldCount === 1 ? '1 field' : fieldCount + ' fields';
                const formName = typeof f.name === 'string' && f.name.trim() !== '' ? f.name : 'Untitled form';
                const $card = $('<article class="a11y-page-card forms-card forms-library-item" role="listitem" tabindex="0"></article>');
                $card.attr('data-id', f.id);
                $card.data('formName', formName);

                const $header = $('<div class="forms-card__header"></div>');
                const $heading = $('<div class="forms-card__heading"></div>');
                const $title = $('<h4 class="forms-card__title"></h4>').text(formName);
                const $badge = $('<span class="forms-card__badge" aria-label="'+escapeHtml(fieldLabel)+'"></span>');
                $badge.append('<i class="fas fa-layer-group" aria-hidden="true"></i>');
                $badge.append('<span>'+escapeHtml(fieldLabel)+'</span>');
                $heading.append($title).append($badge);

                const $actions = $('<div class="forms-card__actions" role="group" aria-label="Form actions"></div>');
                const $viewBtn = $('<button type="button" class="a11y-btn a11y-btn--ghost forms-card__action" data-action="view-submissions"></button>');
                $viewBtn.append('<i class="fas fa-inbox" aria-hidden="true"></i>');
                $viewBtn.append('<span>View submissions</span>');
                const $editBtn = $('<button type="button" class="a11y-btn a11y-btn--secondary forms-card__action" data-action="edit-form"></button>');
                $editBtn.append('<i class="fas fa-pen" aria-hidden="true"></i>');
                $editBtn.append('<span>Edit</span>');
                const $deleteBtn = $('<button type="button" class="a11y-btn a11y-btn--ghost forms-card__action forms-card__action--danger" data-action="delete-form"></button>');
                $deleteBtn.append('<i class="fas fa-trash" aria-hidden="true"></i>');
                $deleteBtn.append('<span>Delete</span>');
                $actions.append($viewBtn, $editBtn, $deleteBtn);

                $header.append($heading).append($actions);
                $card.append($header);

                if(f.id){
                    const $meta = $('<div class="forms-card__meta"></div>');
                    const $metaItem = $('<span class="forms-card__meta-item"></span>');
                    $metaItem.append('<i class="fas fa-hashtag" aria-hidden="true"></i>');
                    $metaItem.append('<span>Form ID '+escapeHtml(String(f.id))+'</span>');
                    $meta.append($metaItem);
                    $card.append($meta);
                }

                if($formsGrid.length){
                    $formsGrid.append($card);
                }
            });

            refreshSubmissionStats();

            if($formsGrid.length){
                $formsGrid.attr('aria-busy', 'false');
            }

            const $cards = $formsGrid.length ? $formsGrid.find('.forms-library-item') : $();
            if(currentFormId){
                const $activeCard = $cards.filter(function(){
                    return $(this).data('id') == currentFormId;
                }).first();
                if($activeCard.length){
                    loadFormSubmissions($activeCard.data('id'), getFormCardName($activeCard));
                } else {
                    resetSubmissionsCard();
                    const $fallback = $cards.first();
                    if($fallback.length){
                        loadFormSubmissions($fallback.data('id'), getFormCardName($fallback));
                    }
                }
            } else {
                const $firstCard = $cards.first();
                if($firstCard.length){
                    loadFormSubmissions($firstCard.data('id'), getFormCardName($firstCard));
                }
            }
        });
    }

    function addField(type, field, options){
        field = field || {};
        options = options || {};
        const suppressSelect = options.suppressSelect === true;
        const isNew = options.isNew === true;
        const typeLabel = FIELD_TYPE_LABELS[type] || (type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Field');
        const $li = $('<li class="field-item" data-type="'+type+'"></li>');
        const $bar = $('<div class="field-bar"></div>');
        $bar.append('<span class="drag-handle" aria-hidden="true">&#9776;</span>');
        $bar.append('<span class="field-type">'+escapeHtml(typeLabel)+'</span>');
        $bar.append('<button type="button" class="btn btn-danger btn-sm removeField" aria-label="Remove '+escapeHtml(typeLabel)+'"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i><span class="btn-label">Remove</span></button>');
        const preview = $('<div class="field-preview"></div>');
        const body = $('<div class="field-body"></div>');

        const labelGroup = $('<div class="form-group"></div>');
        labelGroup.append('<label class="form-label">Label</label>');
        const labelInput = $('<input type="text" class="form-input field-label" placeholder="Field label">');
        labelGroup.append(labelInput);
        body.append(labelGroup);

        const nameGroup = $('<div class="form-group"></div>');
        nameGroup.append('<label class="form-label">Name</label>');
        const nameInput = $('<input type="text" class="form-input field-name" placeholder="e.g. email_address">');
        nameGroup.append(nameInput);
        nameGroup.append('<p class="field-help">Used in submissions and embeds. Letters, numbers, underscores, and hyphens work best.</p>');
        body.append(nameGroup);

        let requiredInput = null;
        if(type !== 'submit'){
            const requiredGroup = $('<div class="form-group"></div>');
            requiredGroup.append('<label><input type="checkbox" class="field-required"> Required</label>');
            body.append(requiredGroup);
            requiredInput = requiredGroup.find('.field-required');
        }

        let optionsInput = null;
        if(['select','radio','checkbox'].includes(type)){
            const optionsGroup = $('<div class="form-group field-options"></div>');
            optionsGroup.append('<label class="form-label">Options</label>');
            optionsInput = $('<input type="text" class="form-input field-options-input" placeholder="Option 1, Option 2">');
            optionsGroup.append(optionsInput);
            optionsGroup.append('<p class="field-help">Separate each choice with a comma.</p>');
            body.append(optionsGroup);
        }

        $li.append($bar).append(preview).append(body.hide());

        const initialLabel = (typeof field.label === 'string' && field.label !== '')
            ? field.label
            : (isNew ? (FIELD_DEFAULT_LABELS[type] || '') : (field.label || ''));
        if(initialLabel){
            labelInput.val(initialLabel);
        }

        if(field.name){
            nameInput.val(String(field.name));
            setManualNameFlag(nameInput, true);
        } else {
            const autoName = generateAutoName(labelInput.val() || typeLabel, nameInput);
            nameInput.val(autoName);
            setManualNameFlag(nameInput, false);
        }

        if(requiredInput && field.required){
            requiredInput.prop('checked', true);
        }
        if(optionsInput){
            if(Array.isArray(field.options)){
                optionsInput.val(field.options.join(', '));
            } else if(typeof field.options === 'string'){
                optionsInput.val(field.options);
            }
        }

        labelInput.on('input', function(){
            if(!isManualName(nameInput)){
                const autoName = generateAutoName($(this).val() || typeLabel, nameInput);
                nameInput.val(autoName);
                setManualNameFlag(nameInput, false);
            }
            updatePreview($li);
        });

        labelInput.on('blur', function(){
            if(!isManualName(nameInput)){
                const autoName = generateAutoName($(this).val() || typeLabel, nameInput);
                nameInput.val(autoName);
                setManualNameFlag(nameInput, false);
            }
            updatePreview($li);
        });

        nameInput.on('input', function(){
            const value = $(this).val();
            if(value.trim() === ''){
                setManualNameFlag(nameInput, false);
            } else {
                setManualNameFlag(nameInput, true);
            }
            updatePreview($li);
        });

        nameInput.on('blur', function(){
            if($(this).val().trim() === ''){
                const autoName = generateAutoName(labelInput.val() || typeLabel, nameInput);
                $(this).val(autoName);
                setManualNameFlag(nameInput, false);
                updatePreview($li);
            }
        });

        body.on('input change', 'input, textarea', function(){ updatePreview($li); });

        $li.on('click', function(){ selectField($li); });

        updatePreview($li);
        $formPreview.append($li);
        if(!suppressSelect){
            selectField($li);
        }
        hideBuilderAlert();
    }

    $('#fieldPalette').on('click', '.palette-item', function(e){
        e.preventDefault();
        const type = $(this).data('type');
        if(type){
            addField(type, {}, { isNew: true });
        }
    });

    $('#fieldPalette').on('keydown', '.palette-item', function(e){
        if(e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar'){
            e.preventDefault();
            const type = $(this).data('type');
            if(type){
                addField(type, {}, { isNew: true });
            }
        }
    });

    $('.palette-item').draggable({ helper:'clone', revert:'invalid' });

    $formPreview.sortable({ placeholder:'ui-sortable-placeholder' }).droppable({
        accept:'.palette-item',
        drop:function(e,ui){
            const type = ui.draggable.data('type');
            if(type){
                addField(type, {}, { isNew: true });
            }
        }
    });

    $('#newFormBtn').on('click', function(){
        clearFormBuilder();
        openFormBuilder('Add form');
    });

    $cancelFormEdit.on('click', function(){
        dismissFormBuilder();
    });

    $closeFormBuilder.on('click', function(){
        if(!$drawer.hasClass('is-visible')){
            return;
        }
        dismissFormBuilder();
    });

    $drawer.on('click', function(event){
        if(event.target === this){
            dismissFormBuilder();
        }
    });

    $(document).off('keydown.formsBuilder').on('keydown.formsBuilder', function(event){
        if(event.key === 'Escape' && $drawer.hasClass('is-visible')){
            event.preventDefault();
            dismissFormBuilder();
        }
    });

    $formsGrid.on('click', '[data-action="view-submissions"]', function(event){
        event.preventDefault();
        event.stopPropagation();
        const $card = $(this).closest('.forms-library-item');
        const id = $card.data('id');
        if(id){
            loadFormSubmissions(id, getFormCardName($card));
        }
    });

    $formsGrid.on('click', '.forms-library-item', function(event){
        if($(event.target).closest('button').length){
            return;
        }
        const $card = $(this);
        const id = $card.data('id');
        if(id){
            loadFormSubmissions(id, getFormCardName($card));
        }
    });

    $formsGrid.on('keydown', '.forms-library-item', function(event){
        if(event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar'){
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    $formsGrid.on('click', '[data-action="edit-form"]', function(event){
        event.preventDefault();
        event.stopPropagation();
        const $card = $(this).closest('.forms-library-item');
        const id = $card.data('id');
        if(!id){
            return;
        }
        $.getJSON('modules/forms/list_forms.php', function(forms){
            const list = Array.isArray(forms) ? forms : [];
            formsCache = list;
            const formData = list.find(function(item){
                return item && item.id == id;
            });
            if(!formData){
                return;
            }
            clearFormBuilder();
            $formId.val(formData.id);
            $formName.val(formData.name);
            (formData.fields || []).forEach(function(fd){
                addField(fd.type, fd, { suppressSelect: true });
            });
            const firstField = $('#formPreview > li').first();
            if(firstField.length){
                selectField(firstField);
            } else {
                selectField(null);
            }
            openFormBuilder('Edit form');
        });
    });

    $formsGrid.on('click', '[data-action="delete-form"]', function(event){
        event.preventDefault();
        event.stopPropagation();
        const $card = $(this).closest('.forms-library-item');
        const id = $card.data('id');
        if(!id){
            return;
        }
        confirmModal('Delete this form?').then(function(ok){
            if(ok){
                $.post('modules/forms/delete_form.php', { id: id }, loadForms);
            }
        });
    });

    $('#formBuilderForm').on('submit', function(e){
        e.preventDefault();
        hideBuilderAlert();
        selectField(null);

        const formName = ($('#formName').val() || '').trim();
        if(!formName){
            showBuilderAlert('Give your form a name before saving.');
            $('#formName').focus();
            return;
        }

        const $items = $('#formPreview > li');
        if(!$items.length){
            showBuilderAlert('Add at least one field before saving.');
            return;
        }

        let missingLabels = 0;
        let missingNames = 0;
        const nameCounts = {};
        $items.removeClass('field-error');

        $items.each(function(){
            const $li = $(this);
            const labelVal = ($li.find('.field-label').val() || '').trim();
            const nameVal = ($li.find('.field-name').val() || '').trim();
            if(!labelVal){
                missingLabels++;
                $li.addClass('field-error');
            }
            if(!nameVal){
                missingNames++;
                $li.addClass('field-error');
            } else {
                nameCounts[nameVal] = (nameCounts[nameVal] || 0) + 1;
            }
        });

        const duplicateNames = Object.keys(nameCounts).filter(function(name){
            return nameCounts[name] > 1;
        });

        if(duplicateNames.length){
            $items.each(function(){
                const $li = $(this);
                const nameVal = ($li.find('.field-name').val() || '').trim();
                if(duplicateNames.includes(nameVal)){
                    $li.addClass('field-error');
                }
            });
        }

        const errors = [];
        if(missingLabels){
            errors.push(missingLabels === 1 ? 'One field is missing a label.' : missingLabels + ' fields are missing labels.');
        }
        if(missingNames){
            errors.push(missingNames === 1 ? 'One field needs a field name.' : missingNames + ' fields need field names.');
        }
        if(duplicateNames.length){
            errors.push('Field names must be unique. Duplicate names: ' + duplicateNames.join(', ') + '.');
        }

        if(errors.length){
            showBuilderAlert(errors.join(' '));
            const $firstError = $('#formPreview > li.field-error').first();
            if($firstError.length){
                selectField($firstError);
                try {
                    $firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (err) {
                    // ignore scroll errors
                }
            }
            return;
        }

        const fields = [];
        $items.each(function(){
            const $li = $(this);
            const type = $li.data('type');
            const labelVal = ($li.find('.field-label').val() || '').trim();
            const nameVal = ($li.find('.field-name').val() || '').trim();
            const fieldData = {
                type: type,
                label: labelVal,
                name: nameVal
            };
            if(type !== 'submit'){
                fieldData.required = $li.find('.field-required').is(':checked');
            }
            if(['select','radio','checkbox'].includes(type)){
                const rawOptions = ($li.find('.field-options-input').val() || '')
                    .split(',')
                    .map(function(opt){ return opt.trim(); })
                    .filter(Boolean);
                fieldData.options = rawOptions.join(', ');
            }
            fields.push(fieldData);
        });

        const payload = {
            id: $formId.val(),
            name: formName,
            fields: JSON.stringify(fields)
        };

        $.post('modules/forms/save_form.php', payload)
            .done(function(){
                dismissFormBuilder();
                loadForms();
            })
            .fail(function(){
                showBuilderAlert('Unable to save the form. Please try again.');
            });
    });

    $formPreview.on('click','.removeField',function(e){
        e.stopPropagation();
        const li = $(this).closest('li');
        if(currentField && currentField[0] === li[0]) selectField(null);
        li.remove();
        if(!$('#formPreview > li').length){
            selectField(null);
        }
        hideBuilderAlert();
    });

    $('#fieldSettings').on('input change', '.field-body input, .field-body textarea', function(){
        if(currentField) updatePreview(currentField);
    });

    $('#submissionModalClose').on('click', function(){
        closeSubmissionModal();
    });

    $('#submissionDetailModal').on('click', function(event){
        if(event.target === this){
            closeSubmissionModal();
        }
    });

    $(document).on('keydown.formsSubmissionModal', function(event){
        if(event.key === 'Escape'){
            closeSubmissionModal();
        }
    });

    bootstrapStatsFromDataset();
    resetSubmissionsCard();
    loadForms();
});

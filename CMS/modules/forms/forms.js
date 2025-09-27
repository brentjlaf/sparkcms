// File: forms.js
$(function(){
    function escapeHtml(str){
        return $('<div>').text(str).html();
    }

    let currentField = null;
    let currentFormId = null;
    let hasLoadedInitialForm = false;
    let formsData = [];
    let activeFilter = 'all';
    let searchQuery = '';
    const formsMeta = window.formsFormMeta || {};
    let formsFilterCounts = window.formsFilterCounts || {};
    const formsDashboardStats = window.formsDashboardStats || {};

    function resetSubmissionsCard(message){
        currentFormId = null;
        hasLoadedInitialForm = false;
        const text = message || 'Select a form to view submissions';
        const rowMessage = /[.!?]$/.test(text) ? text : text + '.';
        $('#formsTable tbody tr').removeClass('selected');
        $('#selectedFormName').text(text);
        $('#formSubmissionsTable tbody').html('<tr class="placeholder-row"><td colspan="2">'+escapeHtml(rowMessage)+'</td></tr>');
    }

    function formatSubmissionDate(value){
        if(!value) return 'Unknown';
        const numeric = Number(value);
        if(!Number.isNaN(numeric) && numeric > 0){
            return new Date(numeric * (numeric < 1e12 ? 1000 : 1)).toLocaleString();
        }
        const date = new Date(value);
        if(!Number.isNaN(date.getTime())){
            return date.toLocaleString();
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

    function determineStatus(meta){
        if((meta.submissions || 0) > 0){
            return 'collecting';
        }
        if((meta.fields || 0) > 0){
            return 'ready';
        }
        return 'draft';
    }

    function syncFormMeta(form){
        if(!form || typeof form !== 'object') return null;
        const key = String(form.id || '');
        if(!key) return null;
        const meta = formsMeta[key] || {};
        const fieldsCount = Array.isArray(form.fields) ? form.fields.length : 0;
        meta.fields = fieldsCount;
        if(typeof meta.submissions !== 'number'){
            meta.submissions = 0;
        }
        if(form.name){
            meta.name = form.name;
        } else if(!meta.name){
            meta.name = 'Untitled form';
        }
        if(!meta.lastSubmission){
            meta.lastSubmission = null;
        }
        meta.status = determineStatus(meta);
        formsMeta[key] = meta;
        return meta;
    }

    function formatStatusLabel(status){
        switch(status){
            case 'collecting':
                return 'Collecting responses';
            case 'ready':
                return 'Ready to publish';
            default:
                return 'Draft';
        }
    }

    function matchesFilter(meta){
        const status = meta.status || determineStatus(meta);
        switch(activeFilter){
            case 'collecting':
                return status === 'collecting';
            case 'ready':
                return status === 'ready';
            case 'draft':
                return status === 'draft';
            default:
                return true;
        }
    }

    function matchesSearch(form, meta){
        if(!searchQuery) return true;
        const haystack = [
            form.name || '',
            formatStatusLabel(meta.status || ''),
            String(meta.submissions || ''),
            String(meta.fields || '')
        ].join(' ').toLowerCase();
        return haystack.includes(searchQuery);
    }

    function updateFilterCountsDisplay(){
        $('[data-forms-count]').each(function(){
            const key = $(this).data('forms-count');
            const value = formsFilterCounts && Object.prototype.hasOwnProperty.call(formsFilterCounts, key) ? formsFilterCounts[key] : 0;
            $(this).text(value);
        });
    }

    function recalcFilterCounts(){
        const counts = { all: formsData.length, collecting: 0, ready: 0, draft: 0 };
        formsData.forEach(function(form){
            const meta = syncFormMeta(form);
            if(!meta) return;
            meta.status = determineStatus(meta);
            if(meta.status === 'collecting') counts.collecting++;
            if(meta.status === 'ready') counts.ready++;
            if(meta.status === 'draft') counts.draft++;
        });
        formsFilterCounts = counts;
        window.formsFilterCounts = counts;
        updateFilterCountsDisplay();
    }

    function updateOverviewStats(){
        const totalForms = formsData.length;
        let totalResponses = 0;
        let activeForms = 0;
        let totalFields = 0;
        let latestIso = formsDashboardStats.lastSubmissionIso || null;

        formsData.forEach(function(form){
            const meta = syncFormMeta(form);
            if(!meta) return;
            const submissions = meta.submissions || 0;
            totalResponses += submissions;
            if(submissions > 0) activeForms++;
            totalFields += meta.fields || 0;
            if(meta.lastSubmission){
                const ts = Date.parse(meta.lastSubmission);
                if(!Number.isNaN(ts)){
                    if(!latestIso || ts > Date.parse(latestIso)){
                        latestIso = meta.lastSubmission;
                    }
                }
            }
        });

        const avgFields = totalForms ? totalFields / totalForms : 0;
        const roundedAvg = avgFields ? Math.round(avgFields * 10) / 10 : 0;
        $('#formsStatTotal').text(totalForms);
        $('#formsStatResponses').text(totalResponses);
        $('#formsStatActive').text(activeForms);
        $('#formsStatFields').text(roundedAvg % 1 === 0 ? roundedAvg.toFixed(0) : roundedAvg.toFixed(1));

        const $meta = $('#formsLastSubmissionMeta');
        if($meta.length){
            const label = latestIso ? 'Last submission: ' + formatSubmissionDate(latestIso) : 'Last submission: None yet';
            $meta.html('<i class="fas fa-clock" aria-hidden="true"></i> '+escapeHtml(label));
        }

        formsDashboardStats.totalForms = totalForms;
        formsDashboardStats.totalSubmissions = totalResponses;
        formsDashboardStats.activeForms = activeForms;
        formsDashboardStats.avgFields = roundedAvg;
        formsDashboardStats.lastSubmissionIso = latestIso;
        formsDashboardStats.lastSubmission = latestIso ? formatSubmissionDate(latestIso) : 'No submissions yet';
    }

    function renderFormsTable(){
        const tbody = $('#formsTable tbody').empty();
        let rendered = 0;

        formsData.forEach(function(form){
            const meta = syncFormMeta(form);
            if(!meta) return;
            meta.status = determineStatus(meta);
            if(!matchesFilter(meta) || !matchesSearch(form, meta)){
                return;
            }

            const row = $('<tr></tr>')
                .attr('data-id', form.id)
                .attr('data-status', meta.status)
                .attr('data-fields', meta.fields || 0)
                .attr('data-submissions', meta.submissions || 0)
                .attr('data-name', form.name || '')
                .data('id', form.id)
                .data('name', form.name || '')
                .data('status', meta.status)
                .data('fields', meta.fields || 0)
                .data('submissions', meta.submissions || 0);

            if(currentFormId && Number(form.id) === Number(currentFormId)){
                row.addClass('selected');
            }

            const nameCell = $('<td class="name"></td>');
            nameCell.append('<div class="forms-table-name">'+escapeHtml(form.name || 'Untitled form')+'</div>');
            nameCell.append('<span class="forms-table-status status-'+meta.status+'">'+escapeHtml(formatStatusLabel(meta.status))+'</span>');

            const fieldsCell = $('<td class="count"></td>').text(meta.fields || 0);
            const responsesCell = $('<td class="responses"></td>').text(meta.submissions || 0);
            const lastText = meta.lastSubmission ? formatSubmissionDate(meta.lastSubmission) : 'No submissions yet';
            const lastCell = $('<td class="last"></td>').text(lastText);

            const actionsCell = $('<td class="actions"></td>');
            const actionsWrap = $('<div class="forms-table-actions"></div>');
            actionsWrap.append('<button type="button" class="a11y-btn a11y-btn--secondary viewSubmissions"><i class="fas fa-inbox" aria-hidden="true"></i><span>Submissions</span></button>');
            actionsWrap.append('<button type="button" class="a11y-btn a11y-btn--ghost editForm"><i class="fas fa-pen" aria-hidden="true"></i><span>Edit</span></button>');
            actionsWrap.append('<button type="button" class="a11y-btn a11y-btn--ghost deleteForm"><i class="fas fa-trash" aria-hidden="true"></i><span>Delete</span></button>');
            actionsCell.append(actionsWrap);

            row.append(nameCell, fieldsCell, responsesCell, lastCell, actionsCell);
            tbody.append(row);
            rendered++;
        });

        if(rendered === 0){
            $('#formsTableWrapper').attr('hidden', true);
            $('#formsEmptyState').removeAttr('hidden');
        } else {
            $('#formsTableWrapper').removeAttr('hidden');
            $('#formsEmptyState').attr('hidden', true);
        }
    }

    function loadFormSubmissions(formId, formName){
        currentFormId = formId;
        $('#formsTable tbody tr').removeClass('selected');
        $('#formsTable tbody tr').filter(function(){ return Number($(this).data('id')) === Number(formId); }).addClass('selected');
        $('#selectedFormName').text(formName || 'Form submissions');

        const tbody = $('#formSubmissionsTable tbody').empty();
        tbody.append('<tr class="loading-row"><td colspan="2">Loading submissions...</td></tr>');

        $.getJSON('modules/forms/list_submissions.php', { form_id: formId }).done(function(data){
            tbody.empty();
            const submissions = Array.isArray(data) ? data : [];
            const form = formsData.find(f => Number(f.id) === Number(formId)) || { id: formId, name: formName, fields: [] };
            const meta = syncFormMeta(form) || {};
            meta.submissions = submissions.length;

            if(submissions.length){
                const first = submissions[0];
                const iso = first.submitted_at || first.created_at || first.timestamp || null;
                meta.lastSubmission = iso || null;
            } else {
                meta.lastSubmission = null;
            }

            meta.status = determineStatus(meta);
            formsMeta[String(formId)] = meta;

            if(!submissions.length){
                tbody.append('<tr class="empty-row"><td colspan="2">No submissions yet.</td></tr>');
            } else {
                submissions.forEach(function(submission){
                    const submittedAt = formatSubmissionDate(submission.submitted_at || submission.created_at || submission.timestamp);
                    const fields = submission.data && typeof submission.data === 'object' ? submission.data : {};
                    const metaInfo = submission.meta && typeof submission.meta === 'object' ? Object.assign({}, submission.meta) : {};
                    if(submission.source && !metaInfo.source){
                        metaInfo.source = submission.source;
                    }
                    if(submission.id && !metaInfo.id){
                        metaInfo.id = submission.id;
                    }
                    const fieldKeys = Object.keys(fields).sort();
                    const metaKeys = Object.keys(metaInfo).sort();
                    const details = [];
                    fieldKeys.forEach(function(key){
                        const value = normalizeSubmissionValue(fields[key]);
                        details.push('<div class="submission-detail"><div class="submission-label">'+escapeHtml(key)+'</div><div class="submission-value">'+escapeHtml(value)+'</div></div>');
                    });
                    if(metaKeys.length){
                        details.push('<div class="submission-meta-group"><div class="submission-meta-title">Metadata</div>');
                        metaKeys.forEach(function(key){
                            const value = normalizeSubmissionValue(metaInfo[key]);
                            details.push('<div class="submission-detail"><div class="submission-label">'+escapeHtml(key)+'</div><div class="submission-value">'+escapeHtml(value)+'</div></div>');
                        });
                        details.push('</div>');
                    }
                    if(!details.length){
                        details.push('<div class="submission-detail"><div class="submission-label">Details</div><div class="submission-value"><em>No submission data provided.</em></div></div>');
                    }
                    tbody.append('<tr><td class="submitted">'+escapeHtml(submittedAt)+'</td><td class="details">'+details.join('')+'</td></tr>');
                });
            }

            updateOverviewStats();
            recalcFilterCounts();
            renderFormsTable();
        }).fail(function(){
            tbody.empty().append('<tr class="error-row"><td colspan="2">Unable to load submissions.</td></tr>');
        });

        hasLoadedInitialForm = true;
    }

    function loadForms(){
        $.getJSON('modules/forms/list_forms.php', function(data){
            formsData = Array.isArray(data) ? data : [];
            const seen = new Set(formsData.map(function(form){ return String(form.id || ''); }));
            Object.keys(formsMeta).forEach(function(key){
                if(!seen.has(key)){
                    delete formsMeta[key];
                }
            });

            formsData.forEach(syncFormMeta);
            recalcFilterCounts();
            updateOverviewStats();
            renderFormsTable();

            if(!formsData.length){
                resetSubmissionsCard('Create a form to start collecting submissions');
                return;
            }

            if(currentFormId){
                const exists = formsData.some(function(form){ return Number(form.id) === Number(currentFormId); });
                if(!exists){
                    currentFormId = null;
                    hasLoadedInitialForm = false;
                }
            }

            if(!currentFormId && !hasLoadedInitialForm){
                const firstRow = $('#formsTable tbody tr').first();
                if(firstRow.length){
                    const id = firstRow.data('id');
                    const name = firstRow.data('name') || firstRow.find('.forms-table-name').text();
                    if(id){
                        loadFormSubmissions(id, name);
                    }
                }
            }
        });
    }

    function addField(type, field){
        field = field || {};
        const $li = $('<li class="field-item" data-type="'+type+'"></li>');
        const bar = $('<div class="field-bar"></div>');
        bar.append('<span class="drag-handle" aria-hidden="true">&#9776;</span>');
        bar.append('<span class="field-type">'+escapeHtml(type)+'</span>');
        bar.append('<button type="button" class="a11y-btn a11y-btn--ghost removeField" aria-label="Remove field"><i class="fas fa-times" aria-hidden="true"></i></button>');
        $li.append(bar);

        const preview = $('<div class="field-preview"></div>');
        const body = $('<div class="field-body"></div>');
        body.append('<div class="form-group"><label class="form-label">Label</label><input type="text" class="form-input field-label"></div>');
        body.append('<div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input field-name"></div>');
        if(type !== 'submit'){
            body.append('<div class="form-group"><label><input type="checkbox" class="field-required"> Required</label></div>');
        }
        if(['select','radio','checkbox'].includes(type)){
            body.append('<div class="form-group field-options"><label class="form-label">Options (comma separated)</label><input type="text" class="form-input field-options-input"></div>');
        }
        $li.append(preview).append(body.hide());
        body.on('input change', 'input, textarea', function(){ updatePreview($li); });
        if(field.label) $li.find('.field-label').val(field.label);
        if(field.name) $li.find('.field-name').val(field.name);
        if(field.required && type !== 'submit') $li.find('.field-required').prop('checked', true);
        if(field.options) $li.find('.field-options-input').val(field.options);
        $li.on('click', function(){ selectField($li); });
        updatePreview($li);
        $('#formPreview').append($li);
    }

    function updatePreview($li){
        const type = $li.data('type');
        const label = $li.find('.field-label').val() || '';
        const name = $li.find('.field-name').val() || '';
        const required = $li.find('.field-required').is(':checked') ? ' required' : '';
        const options = $li.find('.field-options-input').val() || '';
        let html = '';
        switch(type){
            case 'textarea':
                html = '<div class="form-group"><label>'+escapeHtml(label)+'</label><textarea name="'+escapeHtml(name)+'"'+required+'></textarea></div>';
                break;
            case 'select': {
                const opts = options.split(',').map(o => o.trim()).filter(Boolean);
                html = '<div class="form-group"><label>'+escapeHtml(label)+'</label><select name="'+escapeHtml(name)+'"'+required+'>'+opts.map(o => '<option>'+escapeHtml(o)+'</option>').join('')+'</select></div>';
                break;
            }
            case 'checkbox':
            case 'radio': {
                const optList = options.split(',').map(o => o.trim()).filter(Boolean);
                if(optList.length){
                    html = '<div class="form-group"><label>'+escapeHtml(label)+'</label><div>';
                    optList.forEach(function(o){
                        html += '<label><input type="'+type+'" name="'+escapeHtml(name)+'" value="'+escapeHtml(o)+'"> '+escapeHtml(o)+'</label> ';
                    });
                    html += '</div></div>';
                } else {
                    html = '<div class="form-group"><label><input type="'+type+'" name="'+escapeHtml(name)+'"'+required+'> '+escapeHtml(label)+'</label></div>';
                }
                break;
            }
            case 'submit':
                html = '<div class="form-group"><button type="submit">'+escapeHtml(label || 'Submit')+'</button></div>';
                break;
            default: {
                const inputType = type === 'date' ? 'date' : type;
                html = '<div class="form-group"><label>'+escapeHtml(label)+'</label><input type="'+inputType+'" name="'+escapeHtml(name)+'"'+required+'></div>';
                break;
            }
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
            $('#fieldSettings').html('<p>Select a field to edit</p>');
        }
    }

    $('.palette-item').draggable({ helper:'clone', revert:'invalid' });

    $('#formPreview').sortable({ placeholder:'ui-sortable-placeholder' }).droppable({
        accept:'.palette-item',
        drop:function(e,ui){ addField(ui.draggable.data('type')); }
    });

    $('#newFormBtn').on('click', function(){
        $('#formBuilderTitle').text('Add form');
        $('#formId').val('');
        $('#formName').val('');
        $('#formPreview').empty();
        selectField(null);
        $('#formBuilderCard').removeAttr('hidden');
    });

    $('#cancelFormEdit').on('click', function(){
        $('#formBuilderCard').attr('hidden', true);
        $('#formBuilderForm')[0].reset();
        $('#formPreview').empty();
        selectField(null);
    });

    $('#formsTable').on('click', '.viewSubmissions', function(){
        const row = $(this).closest('tr');
        const id = row.data('id');
        const name = row.data('name') || row.find('.forms-table-name').text();
        if(id){
            loadFormSubmissions(id, name);
        }
    });

    $('#formsTable').on('click', 'tbody tr', function(e){
        if($(e.target).closest('button').length) return;
        const row = $(this);
        const id = row.data('id');
        if(id){
            const name = row.data('name') || row.find('.forms-table-name').text();
            loadFormSubmissions(id, name);
        }
    });

    $('#formsTable').on('click', '.editForm', function(){
        const id = $(this).closest('tr').data('id');
        const form = formsData.find(function(item){ return Number(item.id) === Number(id); });
        if(!form) return;
        $('#formBuilderTitle').text('Edit form');
        $('#formId').val(form.id);
        $('#formName').val(form.name);
        $('#formPreview').empty();
        (form.fields || []).forEach(function(fd){ addField(fd.type, fd); });
        selectField(null);
        $('#formBuilderCard').removeAttr('hidden');
    });

    $('#formsTable').on('click', '.deleteForm', function(){
        const row = $(this).closest('tr');
        confirmModal('Delete this form?').then(function(ok){
            if(ok){
                $.post('modules/forms/delete_form.php', { id: row.data('id') }, function(){
                    resetSubmissionsCard();
                    loadForms();
                });
            }
        });
    });

    $('#formBuilderForm').on('submit', function(e){
        e.preventDefault();
        selectField(null);
        const fields = [];
        $('#formPreview > li').each(function(){
            const $li = $(this);
            const f = {
                type: $li.data('type'),
                label: $li.find('.field-label').val(),
                name: $li.find('.field-name').val()
            };
            if(f.type !== 'submit'){
                f.required = $li.find('.field-required').is(':checked');
            }
            if(['select','radio','checkbox'].includes(f.type)){
                f.options = $li.find('.field-options-input').val();
            }
            fields.push(f);
        });
        $.post('modules/forms/save_form.php', {
            id: $('#formId').val(),
            name: $('#formName').val(),
            fields: JSON.stringify(fields)
        }, function(){
            $('#formBuilderCard').attr('hidden', true);
            $('#formBuilderForm')[0].reset();
            $('#formPreview').empty();
            selectField(null);
            loadForms();
        });
    });

    $('#formPreview').on('click', '.removeField', function(e){
        e.stopPropagation();
        const li = $(this).closest('li');
        if(currentField && currentField[0] === li[0]) selectField(null);
        li.remove();
    });

    $('#fieldSettings').on('input change', '.field-body input, .field-body textarea', function(){
        if(currentField) updatePreview(currentField);
    });

    $('#formsSearchInput').on('input', function(){
        searchQuery = $(this).val().toLowerCase().trim();
        renderFormsTable();
    });

    $('#forms').on('click', '[data-forms-filter]', function(){
        const btn = $(this);
        const filter = btn.data('forms-filter');
        if(filter === activeFilter) return;
        activeFilter = filter;
        btn.closest('.a11y-filter-group').find('[data-forms-filter]').removeClass('active');
        btn.addClass('active');
        renderFormsTable();
    });

    updateFilterCountsDisplay();
    resetSubmissionsCard();
    loadForms();
});

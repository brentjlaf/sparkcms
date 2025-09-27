// File: forms.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }

    let currentField = null;
    let currentFormId = null;
    let formsCache = [];

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

    function loadFormSubmissions(formId, formName){
        currentFormId = formId;
        $('#formsTable tbody tr').removeClass('selected');
        $('#formsTable tbody tr').filter(function(){ return $(this).data('id') == formId; }).addClass('selected');
        $('#selectedFormName').text(formName);
        const tbody = $('#formSubmissionsTable tbody').empty();
        tbody.append('<tr class="loading-row"><td colspan="2">Loading submissions...</td></tr>');
        $.getJSON('modules/forms/list_submissions.php', { form_id: formId }, function(data){
            tbody.empty();
            const submissions = Array.isArray(data) ? data : [];
            if(!submissions.length){
                tbody.append('<tr class="empty-row"><td colspan="2">No submissions yet.</td></tr>');
                return;
            }
            submissions.forEach(function(submission){
                const submittedAt = formatSubmissionDate(submission.submitted_at || submission.created_at || submission.timestamp);
                const fields = submission.data && typeof submission.data === 'object' ? submission.data : {};
                const meta = submission.meta && typeof submission.meta === 'object' ? Object.assign({}, submission.meta) : {};
                if(submission.source && !meta.source){
                    meta.source = submission.source;
                }
                if(submission.id && !meta.id){
                    meta.id = submission.id;
                }
                const fieldKeys = Object.keys(fields).sort();
                const metaKeys = Object.keys(meta).sort();
                const details = [];
                fieldKeys.forEach(function(key){
                    const value = normalizeSubmissionValue(fields[key]);
                    details.push('<div class="submission-detail"><div class="submission-label">'+escapeHtml(key)+'</div><div class="submission-value">'+escapeHtml(value)+'</div></div>');
                });
                if(metaKeys.length){
                    details.push('<div class="submission-meta-group"><div class="submission-meta-title">Metadata</div>');
                    metaKeys.forEach(function(key){
                        const value = normalizeSubmissionValue(meta[key]);
                        details.push('<div class="submission-detail"><div class="submission-label">'+escapeHtml(key)+'</div><div class="submission-value">'+escapeHtml(value)+'</div></div>');
                    });
                    details.push('</div>');
                }
                if(!details.length){
                    details.push('<div class="submission-detail"><div class="submission-label">Details</div><div class="submission-value"><em>No submission data provided.</em></div></div>');
                }
                tbody.append('<tr><td class="submitted">'+escapeHtml(submittedAt)+'</td><td class="details">'+details.join('')+'</td></tr>');
            });
        }).fail(function(){
            tbody.empty().append('<tr class="error-row"><td colspan="2">Unable to load submissions.</td></tr>');
        });
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
                html = '<div class="form-group"><label>'+label+'</label><textarea name="'+name+'"'+required+'></textarea></div>'; break;
            case 'select':
                const opts = options.split(',').map(o=>o.trim()).filter(Boolean);
                html = '<div class="form-group"><label>'+label+'</label><select name="'+name+'"'+required+'>'+opts.map(o=>'<option>'+o+'</option>').join('')+'</select></div>'; break;
            case 'checkbox':
            case 'radio':
                const optList = options.split(',').map(o=>o.trim()).filter(Boolean);
                if(optList.length){
                    html = '<div class="form-group"><label>'+label+'</label><div>';
                    optList.forEach(o=>{ html += '<label><input type="'+type+'" name="'+name+'" value="'+o+'"> '+o+'</label> '; });
                    html += '</div></div>';
                } else {
                    html = '<div class="form-group"><label><input type="'+type+'" name="'+name+'"'+required+'> '+label+'</label></div>';
                }
                break;
            case 'submit':
                html = '<div class="form-group"><button type="submit">'+(label||'Submit')+'</button></div>'; break;
            default:
                const inputType = type === 'date' ? 'date' : type;
                html = '<div class="form-group"><label>'+label+'</label><input type="'+inputType+'" name="'+name+'"'+required+'></div>'; break;
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

    function loadForms(){
        $.getJSON('modules/forms/list_forms.php', function(data){
            const forms = Array.isArray(data) ? data : [];
            formsCache = forms;
            applyFormStats({ totalForms: forms.length });
            const tbody = $('#formsTable tbody').empty();
            forms.forEach(function(f){
                tbody.append('<tr data-id="'+f.id+'">'+
                    '<td class="name">'+escapeHtml(f.name)+'</td>'+
                    '<td class="count">'+(f.fields?f.fields.length:0)+'</td>'+
                    '<td class="forms-actions">'+
                        '<button type="button" class="a11y-btn a11y-btn--ghost forms-action-btn viewSubmissions">'+
                            '<i class="fas fa-inbox" aria-hidden="true"></i><span>Submissions</span>'+
                        '</button> '+
                        '<button type="button" class="a11y-btn a11y-btn--secondary forms-action-btn editForm">'+
                            '<i class="fas fa-pen" aria-hidden="true"></i><span>Edit</span>'+
                        '</button> '+
                        '<button type="button" class="a11y-btn a11y-btn--ghost forms-action-btn forms-action-delete deleteForm">'+
                            '<i class="fas fa-trash" aria-hidden="true"></i><span>Delete</span>'+
                        '</button>'+
                    '</td>'+
                    '</tr>');
            });
            refreshSubmissionStats();
            if(!forms.length){
                resetSubmissionsCard('Create a form to start collecting submissions');
                return;
            }
            if(currentFormId){
                const activeRow = tbody.find('tr[data-id="'+currentFormId+'"]').first();
                if(activeRow.length){
                    loadFormSubmissions(currentFormId, activeRow.find('.name').text());
                } else {
                    resetSubmissionsCard();
                    const fallbackRow = tbody.find('tr').first();
                    if(fallbackRow.length){
                        loadFormSubmissions(fallbackRow.data('id'), fallbackRow.find('.name').text());
                    }
                }
            } else {
                const firstRow = tbody.find('tr').first();
                if(firstRow.length){
                    loadFormSubmissions(firstRow.data('id'), firstRow.find('.name').text());
                }
            }
        });
    }

    function addField(type, field){
        field = field || {};
        const $li = $('<li class="field-item" data-type="'+type+'"></li>');
        $li.append('<div class="field-bar"><span class="drag-handle">&#9776;</span> <span class="field-type">'+type+'</span> <button type="button" class="btn btn-danger btn-sm removeField">X</button></div>');
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

    $('.palette-item').draggable({ helper:'clone', revert:'invalid' });

    $('#formPreview').sortable({ placeholder:'ui-sortable-placeholder' }).droppable({
        accept:'.palette-item',
        drop:function(e,ui){ addField(ui.draggable.data('type')); }
    });

    $('#newFormBtn').on('click', function(){
        $('#formBuilderTitle').text('Add Form');
        $('#formId').val('');
        $('#formName').val('');
        $('#formPreview').empty();
        $('#formBuilderCard').show();
    });

    $('#cancelFormEdit').on('click', function(){
        $('#formBuilderCard').hide();
        $('#formBuilderForm')[0].reset();
        $('#formPreview').empty();
        selectField(null);
    });

    $('#formsTable').on('click', '.viewSubmissions', function(){
        const row = $(this).closest('tr');
        const id = row.data('id');
        const name = row.find('.name').text();
        if(id){
            loadFormSubmissions(id, name);
        }
    });

    $('#formsTable').on('click', 'tbody tr', function(e){
        if($(e.target).closest('button').length) return;
        const row = $(this);
        const id = row.data('id');
        if(id){
            loadFormSubmissions(id, row.find('.name').text());
        }
    });

    $('#formsTable').on('click', '.editForm', function(){
        const id = $(this).closest('tr').data('id');
        $.getJSON('modules/forms/list_forms.php', function(forms){
            const f = forms.find(x=>x.id==id);
            if(!f) return;
            $('#formBuilderTitle').text('Edit Form');
            $('#formId').val(f.id);
            $('#formName').val(f.name);
            $('#formPreview').empty();
            (f.fields||[]).forEach(fd=>addField(fd.type, fd));
            $('#formBuilderCard').show();
        });
    });

    $('#formsTable').on('click', '.deleteForm', function(){
        const row = $(this).closest('tr');
        confirmModal('Delete this form?').then(ok=>{
            if(ok){
                $.post('modules/forms/delete_form.php',{id:row.data('id')}, loadForms);
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
            if(['select','radio','checkbox'].includes(f.type)) f.options = $li.find('.field-options-input').val();
            fields.push(f);
        });
        $.post('modules/forms/save_form.php',{
            id: $('#formId').val(),
            name: $('#formName').val(),
            fields: JSON.stringify(fields)
        }, function(){
            $('#formBuilderCard').hide();
            $('#formBuilderForm')[0].reset();
            $('#formPreview').empty();
            selectField(null);
            loadForms();
        });
    });

    $('#formPreview').on('click','.removeField',function(e){
        e.stopPropagation();
        const li = $(this).closest('li');
        if(currentField && currentField[0] === li[0]) selectField(null);
        li.remove();
    });

    $('#fieldSettings').on('input change', '.field-body input, .field-body textarea', function(){
        if(currentField) updatePreview(currentField);
    });

    bootstrapStatsFromDataset();
    resetSubmissionsCard();
    loadForms();
});

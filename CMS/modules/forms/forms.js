// File: forms.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }

    let currentField = null;

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
            const tbody = $('#formsTable tbody').empty();
            (data||[]).forEach(f => {
                tbody.append('<tr data-id="'+f.id+'">'+
                    '<td class="name">'+escapeHtml(f.name)+'</td>'+
                    '<td class="count">'+(f.fields?f.fields.length:0)+'</td>'+
                    '<td><button class="btn btn-secondary editForm">Edit</button> '+
                    '<button class="btn btn-danger deleteForm">Delete</button></td>'+
                    '</tr>');
            });
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

    loadForms();
});

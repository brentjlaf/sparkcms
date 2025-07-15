// File: forms.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }

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

    function createPreview(type){
        switch(type){
            case 'text': return $('<input type="text" class="form-input" disabled>');
            case 'email': return $('<input type="email" class="form-input" disabled>');
            case 'password': return $('<input type="password" class="form-input" disabled>');
            case 'number': return $('<input type="number" class="form-input" disabled>');
            case 'date': return $('<input type="date" class="form-input" disabled>');
            case 'textarea': return $('<textarea class="form-textarea" disabled></textarea>');
            case 'select': return $('<select class="form-select" disabled><option>Option</option></select>');
            case 'checkbox': return $('<input type="checkbox" disabled>');
            case 'radio': return $('<input type="radio" disabled>');
            case 'file': return $('<input type="file" disabled>');
            default: return $('<input type="text" disabled>');
        }
    }

    function addField(type, field){
        field = field || {};
        const $li = $('<li class="field-item" data-type="'+type+'"></li>');
        $li.append('<div class="field-bar"><span class="drag-handle">&#9776;</span> <span class="field-type">'+type+'</span> <button type="button" class="btn btn-danger btn-sm removeField">X</button></div>');
        const preview = $('<div class="field-preview"></div>');
        preview.append('<label class="preview-label"></label>');
        preview.append(createPreview(type));
        $li.append(preview);
        const body = $('<div class="field-body"></div>');
        body.append('<div class="form-group"><label class="form-label">Label</label><input type="text" class="form-input field-label"></div>');
        body.append('<div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input field-name"></div>');
        body.append('<div class="form-group"><label><input type="checkbox" class="field-required"> Required</label></div>');
        if(['select','radio','checkbox'].includes(type)){
            body.append('<div class="form-group field-options"><label class="form-label">Options (comma separated)</label><input type="text" class="form-input field-options-input"></div>');
        }
        $li.append(body);

        if(field.label) $li.find('.field-label').val(field.label);
        if(field.name) $li.find('.field-name').val(field.name);
        if(field.required) $li.find('.field-required').prop('checked', true);
        if(field.options) $li.find('.field-options-input').val(field.options);
        $li.find('.preview-label').text(field.label || 'Label');

        attachFieldEvents($li);
        $('#formFields').append($li);
    }

    function attachFieldEvents($li){
        const preview = $li.find('.preview-label');
        $li.find('.field-label').on('input', function(){
            preview.text(this.value || 'Label');
        });
    }

    $('.palette-item').draggable({ helper:'clone', revert:'invalid' });

    $('#formFields').sortable({ placeholder:'ui-sortable-placeholder' }).droppable({
        accept:'.palette-item',
        drop:function(e,ui){ addField(ui.draggable.data('type')); }
    });

    $('#newFormBtn').on('click', function(){
        $('#formBuilderTitle').text('Add Form');
        $('#formId').val('');
        $('#formName').val('');
        $('#formFields').empty();
        $('#formBuilderCard').show();
    });

    $('#cancelFormEdit').on('click', function(){
        $('#formBuilderCard').hide();
        $('#formBuilderForm')[0].reset();
        $('#formFields').empty();
        $('#fieldSettings').empty().hide();
    });

    $('#formsTable').on('click', '.editForm', function(){
        const id = $(this).closest('tr').data('id');
        $.getJSON('modules/forms/list_forms.php', function(forms){
            const f = forms.find(x=>x.id==id);
            if(!f) return;
            $('#formBuilderTitle').text('Edit Form');
            $('#formId').val(f.id);
            $('#formName').val(f.name);
            $('#formFields').empty();
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
        const fields = [];
        $('#formFields > li').each(function(){
            const $li = $(this);
            const f = {
                type: $li.data('type'),
                label: $li.find('.field-label').val(),
                name: $li.find('.field-name').val(),
                required: $li.find('.field-required').is(':checked')
            };
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
            $('#formFields').empty();
            $('#fieldSettings').empty().hide();
            loadForms();
        });
    });

    $('#formFields').on('click','.removeField',function(){
        const li = $(this).closest('li');
        if(li.hasClass('active')){
            $('#fieldSettings').empty().hide();
        }
        li.remove();
    });

    let activeField = null;
    $('#formFields').on('click', '.field-item', function(e){
        if($(e.target).hasClass('removeField') || $(e.target).closest('.field-bar').length && $(e.target).hasClass('drag-handle')) return;
        if(activeField){
            activeField.removeClass('active');
            activeField.append($('#fieldSettings').children().hide());
        }
        activeField = $(this);
        activeField.addClass('active');
        $('#fieldSettings').empty().append(activeField.find('.field-body').show()).show();
    });

    loadForms();
});

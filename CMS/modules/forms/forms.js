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

    function addField(type, field){
        field = field || {};
        const $li = $('<li class="field-item" data-type="'+type+'"></li>');
        $li.append('<div class="field-bar"><span class="drag-handle">&#9776;</span> <span class="field-type">'+type+'</span> <button type="button" class="btn btn-danger btn-sm removeField">X</button></div>');
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
        $('#formFields').append($li);
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
            loadForms();
        });
    });

    $('#formFields').on('click','.removeField',function(){
        $(this).closest('li').remove();
    });

    loadForms();
});

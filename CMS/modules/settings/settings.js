// File: settings.js
$(function(){
    function loadSettings(){
        $.getJSON('modules/settings/list_settings.php', function(data){
            $('#site_name').val(data.site_name || '');
            $('#tagline').val(data.tagline || '');
            $('#admin_email').val(data.admin_email || '');
            if(data.logo){
                $('#logoPreview').attr('src', data.logo).show();
            }else{
                $('#logoPreview').hide();
            }
        });
    }

    $('#logoFile').on('change', function(){
        const file = this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                $('#logoPreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    $('#settingsForm').on('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        $.ajax({
            url: 'modules/settings/save_settings.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(){
                alertModal('Settings saved');
                loadSettings();
            }
        });
    });

    loadSettings();
});

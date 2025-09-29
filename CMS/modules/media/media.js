// File: media.js
$(function(){
    let currentFolder = null;
    let currentImages = [];
    let currentPage = 1;
    let currentOffset = 0;
    let totalImagesCount = 0;
    let totalPages = 1;
    let cropper = null;
    let flipX = 1;
    let flipY = 1;
    let currentFolderMeta = '';
    let sortBy = 'custom';
    let sortOrder = 'asc';
    let viewType = 'medium';
    let itemsPerPage = 12;

    const reservedFolderNames = ['.', '..', 'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];

    const defaultSelectFolderHeading = $('#selectFolderState h3').text();
    const defaultSelectFolderMessage = $('#selectFolderState p').text();
    const defaultEmptyFolderHeading = $('#emptyFolderState h3').text();
    const defaultEmptyFolderMessage = $('#emptyFolderState p').text();

    function showRetryButton(containerSelector, handler){
        const container = $(containerSelector);
        let btn = container.find('button.retry-button');
        if(!btn.length){
            btn = $('<button type="button" class="btn btn-secondary retry-button"><i class="fa-solid fa-rotate-right btn-icon" aria-hidden="true"></i><span class="btn-label">Retry</span></button>');
            container.append(btn);
        }
        btn.off('click').on('click', function(e){
            e.preventDefault();
            handler();
        }).show();
    }

    function hideRetryButton(containerSelector){
        $(containerSelector).find('button.retry-button').hide();
    }

    function disableUpload(){
        $('#uploadBtn').prop('disabled', true).addClass('is-disabled').attr('aria-disabled', 'true');
    }

    function enableUpload(){
        $('#uploadBtn').prop('disabled', false).removeClass('is-disabled').removeAttr('aria-disabled');
    }

    function loadFolders(){
        $.getJSON('modules/media/list_media.php', function(res){
            const list = $('#folderList').empty();
            const media = res.media || [];
            (res.folders || []).forEach(f => {
                const name = typeof f === 'string' ? f : f.name;
                const thumb = f.thumbnail ? f.thumbnail : null;
                const folderMedia = media.filter(m => m.folder === name);
                const count = folderMedia.length;
                const totalBytes = folderMedia.reduce((s,m)=>s+parseInt(m.size||0),0);
                const lastMod = folderMedia.reduce((m,i)=>i.modified_at && i.modified_at>m?i.modified_at:m,0);
                const meta = count+' files • '+formatFileSize(totalBytes)+' • Last edited '+(lastMod?new Date(lastMod*1000).toLocaleDateString():'');
                const item = $('<div class="folder-item" data-folder="'+name+'"></div>');
                if(thumb){
                    item.append('<img class="folder-thumb" src="'+thumb+'" alt="">');
                }
                item.append('<div class="folder-info"><h3>'+name+'</h3><p class="folder-meta">'+meta+'</p></div>');
                item.click(function(){ selectFolder(name); });
                list.append(item);
            });

            // Update stats
            $('#totalFolders').text((res.folders || []).length);
            $('#totalImages').text(media.length);
            const totalBytes = media.reduce((sum, m) => sum + (parseInt(m.size) || 0), 0);
            $('#totalSize').text(formatFileSize(totalBytes));
            $('#mediaStorageSummary').text(formatFileSize(totalBytes) + ' used');

            if(currentFolder){
                $('.folder-item[data-folder="'+currentFolder+'"]').addClass('active');
            } else {
                $('#mediaHeroFolderName').text('No folder selected');
                $('#mediaHeroFolderInfo').text('Select a folder to see file details');
            }
            $('#selectFolderState h3').text(defaultSelectFolderHeading);
            $('#selectFolderState p').text(defaultSelectFolderMessage);
            hideRetryButton('#selectFolderState');
        }).fail(function(jqXHR, textStatus, errorThrown){
            console.error('Failed to load folders:', textStatus, errorThrown);
            currentFolder = null;
            currentImages = [];
            currentFolderMeta = '';
            $('#folderList').empty();
            $('#totalFolders').text('0');
            $('#totalImages').text('0');
            $('#totalSize').text('0');
            $('#mediaStorageSummary').text('0 used');
            $('#folderStats').text('');
            $('#galleryHeader').hide();
            disableUpload();
            renderImages();
            $('#mediaHeroFolderName').text('Unable to load folders');
            $('#mediaHeroFolderInfo').text('Please try again or contact support if the issue continues.');
            $('#selectFolderState h3').text('Unable to load folders');
            $('#selectFolderState p').text('Check your connection and try again.');
            showRetryButton('#selectFolderState', loadFolders);
            alertModal('We couldn\'t load your media folders. Please try again.');
        });
    }

    function selectFolder(name){
        currentFolder = name;
        currentPage = 1;
        currentOffset = 0;
        totalPages = 1;
        totalImagesCount = 0;
        $('.folder-item').removeClass('active');
        $('.folder-item[data-folder="'+name+'"]').addClass('active');
        $('#selectedFolderName').text(name);
        $('#galleryHeader').show();
        $('#renameFolderBtn').show();
        $('#deleteFolderBtn').show();
        $('#mediaHeroFolderName').text(name);
        $('#mediaHeroFolderInfo').text('Loading folder details…');
        $('#uploadBtn').prop('disabled', false).removeClass('is-disabled').removeAttr('aria-disabled');
        loadImages();
    }

    function loadImages(){
        if(!currentFolder){
            currentImages = [];
            totalImagesCount = 0;
            totalPages = 1;
            currentOffset = 0;
            renderImages();
            return;
        }
        const limit = itemsPerPage>0 ? itemsPerPage : 0;
        const offset = limit ? (currentPage-1)*limit : 0;
        const params = {folder: currentFolder, sort: sortBy, order: sortOrder};
        if(limit){
            params.limit = limit;
            params.offset = offset;
        }
        $.getJSON('modules/media/list_media.php', params, function(res){
            currentImages = res.media || [];
            const parsedTotal = parseInt(res.total, 10);
            totalImagesCount = Number.isNaN(parsedTotal) ? currentImages.length : parsedTotal;
            const parsedBytes = parseInt(res.total_size, 10);
            const totalBytes = Number.isNaN(parsedBytes) ? currentImages.reduce((s,m)=>s+parseInt(m.size||0),0) : parsedBytes;
            const parsedLastMod = parseInt(res.last_modified, 10);
            const lastMod = Number.isNaN(parsedLastMod) ? currentImages.reduce((m,i)=>i.modified_at && i.modified_at>m?i.modified_at:m,0) : parsedLastMod;
            const lastEdited = lastMod ? 'Last edited ' + new Date(lastMod*1000).toLocaleDateString() : 'No edits yet';
            const limitPages = limit ? Math.max(1, Math.ceil(totalImagesCount/limit)) : 1;
            if(limit && currentPage>limitPages && limitPages>0){
                currentPage = limitPages;
                loadImages();
                return;
            }
            totalPages = limitPages;
            currentOffset = offset;
            currentFolderMeta = totalImagesCount+' files • '+formatFileSize(totalBytes)+' • '+lastEdited;
            $('#folderStats').text(currentFolderMeta);
            $('#mediaHeroFolderInfo').text(currentFolderMeta);
            $('#emptyFolderState h3').text(defaultEmptyFolderHeading);
            $('#emptyFolderState p').text(defaultEmptyFolderMessage);
            hideRetryButton('#emptyFolderState');
            enableUpload();
            $('#renameFolderBtn').show();
            $('#deleteFolderBtn').show();
            renderImages();
        }).fail(function(jqXHR, textStatus, errorThrown){
            console.error('Failed to load images for folder', currentFolder, textStatus, errorThrown);
            currentImages = [];
            currentFolderMeta = '';
            $('#folderStats').text('');
            $('#galleryHeader').show();
            $('#mediaHeroFolderInfo').text('Unable to load files for this folder. Please try again.');
            $('#emptyFolderState h3').text('Unable to load media');
            $('#emptyFolderState p').text('Check your connection and try again.');
            showRetryButton('#emptyFolderState', loadImages);
            disableUpload();
            renderImages();
            $('#mediaToolbar').hide();
            $('#renameFolderBtn').hide();
            $('#deleteFolderBtn').hide();
            alertModal('We couldn\'t load the media in this folder. Please try again.');
        });
    }

    function updateOrder(){
        const ids = $('#imageGrid .image-card').map(function(){ return $(this).data('id'); }).get();
        $.post('modules/media/update_order.php', {order: JSON.stringify(ids)});
    }

    function getSortedImages(){
        let imgs = currentImages.slice();
        switch(sortBy){
            case 'name':
                imgs.sort((a,b)=>a.name.localeCompare(b.name));
                break;
            case 'date':
                imgs.sort((a,b)=>(a.modified_at||a.uploaded_at||0)-(b.modified_at||b.uploaded_at||0));
                break;
            case 'type':
                imgs.sort((a,b)=>(a.type||'').localeCompare(b.type||''));
                break;
            case 'size':
                imgs.sort((a,b)=>(parseInt(a.size)||0)-(parseInt(b.size)||0));
                break;
            case 'tags':
                imgs.sort((a,b)=>((a.tags||[]).join(',')).localeCompare((b.tags||[]).join(',')));
                break;
            case 'dimensions':
                imgs.sort((a,b)=>((a.width||0)*(a.height||0))-((b.width||0)*(b.height||0)));
                break;
            default:
                imgs.sort((a,b)=>(a.order||0)-(b.order||0));
        }
        if(sortOrder==='desc') imgs.reverse();
        if(itemsPerPage>0){
            const startIndex = Math.max(0, (currentPage-1)*itemsPerPage - currentOffset);
            imgs = imgs.slice(startIndex, startIndex + itemsPerPage);
        }
        return imgs;
    }

    function renderPagination(){
        const pagination = $('#galleryPagination');
        if(!currentFolder || itemsPerPage<=0 || totalPages<=1){
            pagination.hide().empty();
            return;
        }

        pagination.empty().show();

        const createButton = (label, page, disabled, active) => {
            const btn = $('<button type="button" class="pagination-btn">'+label+'</button>');
            if(disabled){
                btn.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-disabled');
            }else{
                btn.attr('data-page', page);
            }
            if(active){
                btn.addClass('is-active');
                btn.attr('aria-current', 'page');
            }
            if(label==='Prev'){
                btn.attr('aria-label', 'Previous page');
            }else if(label==='Next'){
                btn.attr('aria-label', 'Next page');
            }else{
                btn.attr('aria-label', 'Go to page '+label);
            }
            return btn;
        };

        pagination.append(createButton('Prev', currentPage-1, currentPage===1, false));

        const maxButtons = 5;
        let start = Math.max(1, currentPage - Math.floor(maxButtons/2));
        let end = start + maxButtons - 1;
        if(end>totalPages){
            end = totalPages;
            start = Math.max(1, end - maxButtons + 1);
        }

        for(let page=start; page<=end; page++){
            pagination.append(createButton(page, page, false, page===currentPage));
        }

        pagination.append(createButton('Next', currentPage+1, currentPage===totalPages, false));
    }

    function applyViewType(){
        const grid = $('#imageGrid');
        grid.removeClass('view-extra-large view-large view-medium view-small view-details');
        grid.addClass('view-'+viewType);
    }

    function renderImages(){
        const grid = $('#imageGrid');
        const toolbar = $('#mediaToolbar');
        if(!currentFolder){
            toolbar.hide();
            $('#selectFolderState').show();
            grid.hide();
            $('#emptyFolderState').hide();
            $('#folderStats').text('');
            $('#renameFolderBtn').hide();
            $('#deleteFolderBtn').hide();
            $('#mediaHeroFolderName').text('No folder selected');
            $('#mediaHeroFolderInfo').text('Select a folder to see file details');
            $('#uploadBtn').prop('disabled', true).addClass('is-disabled').attr('aria-disabled', 'true');
            return;
        }
        toolbar.show();
        $('#selectFolderState').hide();
        const images = getSortedImages();
        grid.empty();
        if(images.length===0){
            $('#emptyFolderState').show();
            grid.hide();
        }else{
            $('#emptyFolderState').hide();
            grid.show();
            images.forEach(img=>{
                const isImage = img.type === 'images';
                const src = img.thumbnail ? img.thumbnail : img.file;
                let preview = '';
                if(isImage){
                    preview = '<img src="'+src+'" alt="'+img.name+'">';
                }else{
                    const ext = img.file.split('.').pop().toLowerCase();
                    const icons = {
                        pdf: '<i class="fa-solid fa-file-pdf" aria-hidden="true"></i>',
                        doc: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
                        docx: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
                        txt: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
                        mp4: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
                        webm: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
                        mov: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>'
                    };
                    const icon = icons[ext] || '<i class="fa-solid fa-file" aria-hidden="true"></i>';
                    preview = '<div class="file-icon">'+icon+'</div>';
                }
                const card = $('<div class="image-card" data-id="'+img.id+'">\
                        <div class="image-preview">'+preview+'\
                            <div class="image-overlay">\
                                <div>\
                                    <button class="info-btn" data-id="'+img.id+'" aria-label="View info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>\
                                    <button class="edit-btn" data-id="'+img.id+'" aria-label="Edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>\
                                    <button class="remove-btn" data-id="'+img.id+'" aria-label="Remove"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>\
                                </div>\
                            </div>\
                        </div>\
                        <div class="image-info">\
                            <h4>'+img.name+'</h4>\
                            <p>'+formatFileSize(img.size)+'</p>\
                        </div>\
                    </div>');
                grid.append(card);
            });
            if(sortBy === 'custom' && totalPages <= 1){
                grid.sortable({
                    placeholder: 'ui-sortable-placeholder',
                    start: function(e, ui){ ui.placeholder.height(ui.item.height()); },
                    stop: function(){ updateOrder(); }
                });
            }else if(grid.hasClass('ui-sortable')){
                grid.sortable('destroy');
            }
        }
        applyViewType();
        renderPagination();
    }

    function formatFileSize(bytes){
        if(bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes)/Math.log(k));
        return parseFloat((bytes/Math.pow(k,i)).toFixed(2))+' '+sizes[i];
    }

    function updateSizeEstimate(){
        if(!cropper){ $('#sizeEstimate').text(''); return; }
        const canvas = cropper.getCroppedCanvas();
        const format = $('#saveFormat').val() || 'jpeg';
        const mime = 'image/' + (format === 'jpg' ? 'jpeg' : format);
        const quality = format === 'jpeg' ? 0.9 : 1;
        canvas.toBlob(function(blob){
            $('#sizeEstimate').text('Estimated: '+formatFileSize(blob.size));
        }, mime, quality);
    }

    function updateUploadProgress(percent){
        const value = Math.max(0, Math.min(100, Math.round(percent || 0)));
        $('#uploadProgressFill').css('width', value + '%');
        $('#uploadProgressPercent').text(value + '%');
    }

    function startUploadUI(){
        updateUploadProgress(0);
        $('#uploadStatusMessage').text('Uploading files…');
        $('#uploadLoader').show();
    }

    function resetUploadUI(){
        $('#fileInput').val('');
        $('#uploadLoader').hide();
        updateUploadProgress(0);
        $('#uploadStatusMessage').text('');
    }

    function uploadFiles(files){
        if(!currentFolder || !files.length) return;
        const fd = new FormData();
        Array.from(files).forEach(f => fd.append('files[]', f));
        fd.append('folder', currentFolder);
        fd.append('tags','');
        startUploadUI();
        $.ajax({
            url: 'modules/media/upload_media.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function(){
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e){
                    if(e.lengthComputable){
                        const percent = (e.loaded / e.total) * 100;
                        updateUploadProgress(percent);
                    }
                });
                return xhr;
            }
        }).done(function(res){
            const response = res || {};
            if(response.status === 'success'){
                updateUploadProgress(100);
                loadImages();
                loadFolders();
            }
            const errors = Array.isArray(response.errors) ? response.errors : [];
            if(errors.length){
                alertModal(errors.join('\n'));
            } else if(response.status !== 'success'){
                const message = response.message || 'Error uploading files.';
                alertModal(message);
            }
        }).fail(function(jqXHR){
            let message = 'Error uploading files.';
            if(jqXHR.responseJSON){
                const json = jqXHR.responseJSON;
                if(Array.isArray(json.errors) && json.errors.length){
                    message = json.errors.join('\n');
                }else if(json.message){
                    message = json.message;
                }
            }else if(jqXHR.responseText){
                message = jqXHR.responseText;
            }
            alertModal(message);
        }).always(function(){
            resetUploadUI();
        });
    }

    function getCreateFolderMessageElement(){
        let $message = $('#createFolderModal .create-folder-message');
        if(!$message.length){
            $message = $('<p class="create-folder-message" role="status" aria-live="polite" style="display:none;margin-top:8px;"></p>');
            $('#createFolderModal .modal-body').append($message);
        }
        return $message;
    }

    function showCreateFolderMessage(text, type='error'){
        const $message = getCreateFolderMessageElement();
        $message
            .text(text)
            .attr('role', type === 'error' ? 'alert' : 'status')
            .attr('aria-live', type === 'error' ? 'assertive' : 'polite')
            .css({
                color: type === 'error' ? '#c0392b' : '#1e8449',
                'font-weight': '600'
            })
            .show();
    }

    function clearCreateFolderMessage(){
        const $message = $('#createFolderModal .create-folder-message');
        if($message.length){
            $message.text('').hide();
        }
    }

    function createFolder(){
        const $input = $('#newFolderName');
        const name = $input.val().trim();
        $input.val(name);
        clearCreateFolderMessage();

        if(!name){
            showCreateFolderMessage('Please enter a folder name.', 'error');
            $input.focus();
            return;
        }

        const lowerName = name.toLowerCase();
        if(reservedFolderNames.includes(lowerName)){
            showCreateFolderMessage('That folder name is reserved. Please choose another.', 'error');
            $input.focus();
            return;
        }

        if(/[\\/]/.test(name)){
            showCreateFolderMessage('Folder names cannot contain slashes.', 'error');
            $input.focus();
            return;
        }

        $('#confirmCreateBtn').prop('disabled', true);

        $.ajax({
            url: 'modules/media/create_folder.php',
            method: 'POST',
            data: {folder: name},
            dataType: 'json'
        }).done(function(res){
            if(res && res.status === 'success'){
                showCreateFolderMessage(res.message || 'Folder created successfully.', 'success');
                $input.val('');
                loadFolders();
                setTimeout(function(){
                    clearCreateFolderMessage();
                    closeModal('createFolderModal');
                }, 1000);
            }else{
                showCreateFolderMessage((res && res.message) || 'Error creating folder.', 'error');
                $input.focus();
            }
        }).fail(function(){
            showCreateFolderMessage('Error creating folder.', 'error');
            $input.focus();
        }).always(function(){
            $('#confirmCreateBtn').prop('disabled', false);
        });
    }

    function getRenameFolderMessageElement(){
        return $('#renameFolderMessage');
    }

    function showRenameFolderMessage(text, type = 'error'){
        const $message = getRenameFolderMessageElement();
        if(!$message.length) return;
        $message
            .text(text)
            .attr('role', type === 'error' ? 'alert' : 'status')
            .attr('aria-live', type === 'error' ? 'assertive' : 'polite')
            .css({
                color: type === 'error' ? '#c0392b' : '#1e8449',
                'font-weight': '600'
            })
            .show();
    }

    function clearRenameFolderMessage(){
        const $message = getRenameFolderMessageElement();
        if($message.length){
            $message.text('').hide().removeAttr('role').removeAttr('aria-live');
        }
    }

    function renameFolder(){
        if(!currentFolder) return;
        const previousFolder = currentFolder;
        const $modal = $('#renameFolderModal');
        const $input = $('#renameFolderName');
        const $confirm = $('#confirmRenameFolderBtn');
        const $cancel = $('#cancelRenameFolderBtn');
        const restoreSelection = () => {
            currentFolder = previousFolder;
            $('#selectedFolderName').text(previousFolder);
            $('#mediaHeroFolderName').text(previousFolder);
            $('.folder-item').removeClass('active');
            $('.folder-item[data-folder="'+previousFolder+'"]').addClass('active');
        };
        clearRenameFolderMessage();
        $input.val(previousFolder);
        openModal('renameFolderModal');
        $input.focus().select();

        function cleanup(){
            $modal.off('click.renameFolder');
            $input.off('keypress.renameFolder');
            $input.off('input.renameFolder');
            $confirm.off('click.renameFolder').prop('disabled', false);
            $cancel.off('click.renameFolder');
        }

        function closeRenameModal(){
            cleanup();
            closeModal('renameFolderModal');
            clearRenameFolderMessage();
        }

        function cancelRename(){
            closeRenameModal();
            restoreSelection();
        }

        function attemptRename(){
            clearRenameFolderMessage();
            const value = $input.val();
            const trimmedName = value.trim();
            $input.val(trimmedName);

            if(!trimmedName){
                showRenameFolderMessage('Folder name cannot be empty.', 'error');
                $input.focus();
                return;
            }

            if(/[\\/]/.test(trimmedName)){
                showRenameFolderMessage('Folder names cannot contain slashes.', 'error');
                $input.focus();
                return;
            }

            const lowerName = trimmedName.toLowerCase();
            if(reservedFolderNames.includes(lowerName)){
                showRenameFolderMessage('That folder name is reserved. Please choose another.', 'error');
                $input.focus();
                return;
            }

            if(trimmedName === previousFolder){
                showRenameFolderMessage('Folder name is unchanged.', 'error');
                $input.focus();
                return;
            }

            $confirm.prop('disabled', true);

            $.post('modules/media/rename_folder.php',{old:previousFolder,new:trimmedName},function(res){
                if(res.status==='success'){
                    currentFolder = trimmedName;
                    $('#selectedFolderName').text(trimmedName);
                    $('#mediaHeroFolderName').text(trimmedName);
                    loadImages();
                    loadFolders();
                    closeRenameModal();
                }else{
                    const message = res && res.message ? res.message : 'Error renaming folder';
                    showRenameFolderMessage(message, 'error');
                    restoreSelection();
                }
            },'json').fail(function(xhr){
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error renaming folder';
                showRenameFolderMessage(message, 'error');
                restoreSelection();
            }).always(function(){
                $confirm.prop('disabled', false);
            });
        }

        $confirm.off('click.renameFolder').on('click.renameFolder', function(e){
            e.preventDefault();
            attemptRename();
        });

        $cancel.off('click.renameFolder').on('click.renameFolder', function(e){
            e.preventDefault();
            cancelRename();
        });

        $modal.off('click.renameFolder').on('click.renameFolder', function(e){
            if(e.target === this){
                cancelRename();
            }
        });

        $input.off('keypress.renameFolder').on('keypress.renameFolder', function(e){
            if(e.which === 13){
                e.preventDefault();
                attemptRename();
            }
        });

        $input.off('input.renameFolder').on('input.renameFolder', function(){
            clearRenameFolderMessage();
        });
    }

    function deleteFolder(){
        if(!currentFolder) return;
        confirmModal('Delete this folder and all its files?').then(ok => {
            if(!ok) return;
            $.post('modules/media/delete_folder.php',{folder:currentFolder},function(res){
                if(res.status==='success'){
                    currentFolder = null;
                    currentPage = 1;
                    currentOffset = 0;
                    totalPages = 1;
                    totalImagesCount = 0;
                    $('#galleryHeader').hide();
                    loadFolders();
                    loadImages();
                }else{
                    alertModal(res.message||'Error deleting folder');
                }
            },'json');
        });
    }

    function renameImageDirect(id, newName){
        if(!newName) return;
        $.post('modules/media/rename_media.php',{id:id,name:newName},function(res){
            if(res.status==='success'){
                loadImages();
                loadFolders();
            }else{
                alertModal(res.message||'Error renaming file');
            }
        },'json');
    }

    function renameImage(id,name){
        promptModal('Enter new file name', name||'').then(newName => {
            if(!newName || newName===name) return;
            renameImageDirect(id,newName);
        });
    }

    function showImageInfo(id){
        const img = currentImages.find(i=>i.id===id);
        if(!img) return;
        $('#infoImage').attr('src', img.thumbnail?img.thumbnail:img.file);
        $('#edit-name').val(img.name);
        $('#edit-fileName').val(img.name);
        $('#infoType').text(img.type||'');
        $('#infoFile').text(img.name||'');
        $('#infoSize').text(formatFileSize(parseInt(img.size)||0));
        $('#infoDimensions').text((img.width||'?')+' x '+(img.height||'?'));
        $('#infoExt').text(img.file.split('.').pop());
        const d = img.modified_at ? new Date(img.modified_at*1000) : new Date(img.uploaded_at*1000);
        $('#infoDate').text(d.toLocaleString());
        $('#infoFolder').text(img.folder||'');
        $('#imageInfoModal').data('id', id);
        openModal('imageInfoModal');
    }

    function deleteImage(id){
        confirmModal('Delete this image?').then(ok => {
            if(!ok) return;
            $.post('modules/media/delete_media.php',{id:id},function(){
                loadImages();
                loadFolders();
            });
        });
    }

    function openEditor(id){
        const img = currentImages.find(i=>i.id===id);
        if(!img) return;
        $('#imageEditModal').data('id', id);
        openModal('imageEditModal');
        const el = document.getElementById('editImage');
        el.src = img.file;
        if(cropper) cropper.destroy();
        cropper = new Cropper(el, {viewMode:1});
        flipX = 1;
        flipY = 1;
        $('#scaleSlider').val(1);
        $('#crop-preset').val('NaN');
        cropper.setAspectRatio(NaN);
        cropper.zoomTo(1);
        updateSizeEstimate();
    }

    function saveEditedImage(){
        if(!cropper) return;
        const id = $('#imageEditModal').data('id');
        const canvas = cropper.getCroppedCanvas();
        const format = $('#saveFormat').val() || 'jpeg';
        const mime = 'image/' + (format === 'jpg' ? 'jpeg' : format);
        const quality = format === 'jpeg' ? 0.9 : 1;
        const dataUrl = canvas.toDataURL(mime, quality);
        confirmModal('Create a new version? Click Cancel to overwrite the original.').then(newVer => {
            $.post('modules/media/crop_media.php',{id:id,image:dataUrl,new_version:newVer?1:0,format:format},function(){
                closeModal('imageEditModal');
                if(cropper){cropper.destroy(); cropper=null;}
                loadImages();
                loadFolders();
            },'json');
        });
    }

    $('#uploadBtn').click(function(){ $('#fileInput').click(); });
    $('#fileInput').change(function(){ uploadFiles(this.files); });

    let dragCounter = 0;
    const dropZone = $('#dropZone');
    $('#galleryContent').on('dragenter', function(e){
        if(!currentFolder) return;
        e.preventDefault();
        dragCounter++;
        dropZone.addClass('dragging').css('display','flex');
    }).on('dragover', function(e){
        if(!currentFolder) return;
        e.preventDefault();
    }).on('dragleave', function(e){
        if(!currentFolder) return;
        dragCounter--;
        if(dragCounter<=0){
            dragCounter = 0;
            dropZone.removeClass('dragging').css('display','none');
        }
    }).on('drop', function(e){
        if(!currentFolder) return;
        e.preventDefault();
        dragCounter = 0;
        dropZone.removeClass('dragging').css('display','none');
        const files = e.originalEvent.dataTransfer.files;
        uploadFiles(files);
    });
    dropZone.click(function(){ $('#fileInput').click(); });

    $('#renameFolderBtn').click(renameFolder);
    $('#deleteFolderBtn').click(deleteFolder);
    $('#createFolderBtn').click(function(){
        clearCreateFolderMessage();
        openModal('createFolderModal');
        $('#newFolderName').focus();
    });
    $('#cancelBtn').click(function(){
        closeModal('createFolderModal');
        $('#newFolderName').val('');
        clearCreateFolderMessage();
    });
    $('#confirmCreateBtn').click(createFolder);
    $('#newFolderName').keypress(function(e){ if(e.which===13) createFolder(); });

    $('#imageGrid').on('click','.remove-btn',function(e){ e.stopPropagation(); deleteImage($(this).data('id')); });
    $('#imageGrid').on('click','.info-btn',function(e){ e.stopPropagation(); showImageInfo($(this).data('id')); });
    $('#imageGrid').on('click','.edit-btn',function(e){ e.stopPropagation(); openEditor($(this).data('id')); });
    $('#imageGrid').on('click','.image-card',function(e){
        if($(e.target).closest('.remove-btn').length || $(e.target).closest('.info-btn').length || $(e.target).closest('.edit-btn').length) return;
        showImageInfo($(this).data('id'));
    });

    $('#deleteBtn').click(function(){
        const id = $('#imageInfoModal').data('id');
        deleteImage(id);
        closeModal('imageInfoModal');
    });
    $('#saveEditBtn').click(function(){
        const id = $('#imageInfoModal').data('id');
        const newName = $('#edit-fileName').val().trim();
        const current = currentImages.find(i=>i.id===id) || {};
        if(newName && newName!==current.name){
            renameImageDirect(id,newName);
        }
        closeModal('imageInfoModal');
    });

    $('#imageEditCancel').click(function(){
        closeModal('imageEditModal');
        if(cropper){ cropper.destroy(); cropper=null; }
    });
    $('#imageEditSave').click(saveEditedImage);
    $('#flipHorizontal').click(function(){
        if(!cropper) return;
        flipX = flipX * -1;
        cropper.scaleX(flipX);
    });
    $('#flipVertical').click(function(){
        if(!cropper) return;
        flipY = flipY * -1;
        cropper.scaleY(flipY);
    });
    $('#scaleSlider').on('input', function(){
        const val = parseFloat(this.value);
        if(cropper){
            cropper.zoomTo(val);
            updateSizeEstimate();
        }
    });
    $('#crop-preset').change(function(){
        if(!cropper) return;
        const ratio = parseFloat(this.value);
        cropper.setAspectRatio(isNaN(ratio) ? NaN : ratio);
        updateSizeEstimate();
    });
    $('#saveFormat').change(updateSizeEstimate);

    $('#sort-by').change(function(){ sortBy = this.value; currentPage = 1; loadImages(); });
    $('#sort-order').change(function(){ sortOrder = this.value; currentPage = 1; loadImages(); });
    $('#view-type').change(function(){ viewType = this.value; applyViewType(); });
    $('#items-per-page').change(function(){ itemsPerPage = parseInt(this.value,10); currentPage = 1; loadImages(); });

    $('#galleryPagination').on('click', '.pagination-btn[data-page]', function(){
        const page = parseInt($(this).data('page'), 10);
        if(!isNaN(page) && page>=1 && page<=totalPages && page!==currentPage){
            currentPage = page;
            loadImages();
        }
    });

    $(window).click(function(e){
        if(e.target.id==='createFolderModal'){
            closeModal('createFolderModal');
            $('#newFolderName').val('');
            clearCreateFolderMessage();
        }
        if(e.target.id==='imageInfoModal'){ closeModal('imageInfoModal'); }
        if(e.target.id==='imageEditModal'){ closeModal('imageEditModal'); if(cropper){cropper.destroy(); cropper=null;} }
    });

    loadFolders();
});

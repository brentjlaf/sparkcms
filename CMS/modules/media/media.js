// File: media.js
$(function(){
    let currentFolder = null;
    let currentImages = [];
    let cropper = null;
    let flipX = 1;
    let flipY = 1;
    let currentFolderMeta = '';
    let sortBy = 'custom';
    let sortOrder = 'asc';
    let viewType = 'medium';
    let itemsPerPage = 12;

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
        });
    }

    function selectFolder(name){
        currentFolder = name;
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
            renderImages();
            return;
        }
        $.getJSON('modules/media/list_media.php', {folder: currentFolder}, function(res){
            currentImages = res.media || [];
            const totalBytes = currentImages.reduce((s,m)=>s+parseInt(m.size||0),0);
            const lastMod = currentImages.reduce((m,i)=>i.modified_at && i.modified_at>m?i.modified_at:m,0);
            const lastEdited = lastMod ? 'Last edited ' + new Date(lastMod*1000).toLocaleDateString() : 'No edits yet';
            currentFolderMeta = currentImages.length+' files • '+formatFileSize(totalBytes)+' • '+lastEdited;
            $('#folderStats').text(currentFolderMeta);
            $('#mediaHeroFolderInfo').text(currentFolderMeta);
            renderImages();
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
        if(itemsPerPage>0) imgs = imgs.slice(0, itemsPerPage);
        return imgs;
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
            grid.sortable({
                placeholder: 'ui-sortable-placeholder',
                start: function(e, ui){ ui.placeholder.height(ui.item.height()); },
                stop: function(){ updateOrder(); }
            });
        }
        applyViewType();
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

    function createFolder(){
        const name = $('#newFolderName').val().trim();
        if(!name) return;
        $.post('modules/media/create_folder.php',{folder:name},function(){
            $('#newFolderName').val('');
            closeModal('createFolderModal');
            loadFolders();
        });
    }

    function renameFolder(){
        if(!currentFolder) return;
        promptModal('Enter new folder name', currentFolder).then(newName => {
            if(!newName || newName === currentFolder) return;
        $.post('modules/media/rename_folder.php',{old:currentFolder,new:newName},function(res){
            if(res.status==='success'){
                currentFolder = newName;
                $('#selectedFolderName').text(newName);
                $('#mediaHeroFolderName').text(newName);
                loadImages();
                loadFolders();
            }else{
                alertModal(res.message||'Error renaming folder');
            }
        },'json');
        });
    }

    function deleteFolder(){
        if(!currentFolder) return;
        confirmModal('Delete this folder and all its files?').then(ok => {
            if(!ok) return;
            $.post('modules/media/delete_folder.php',{folder:currentFolder},function(res){
                if(res.status==='success'){
                    currentFolder = null;
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
    $('#createFolderBtn').click(function(){ openModal('createFolderModal'); $('#newFolderName').focus(); });
    $('#cancelBtn').click(function(){ closeModal('createFolderModal'); $('#newFolderName').val(''); });
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

    $('#sort-by').change(function(){ sortBy = this.value; renderImages(); });
    $('#sort-order').change(function(){ sortOrder = this.value; renderImages(); });
    $('#view-type').change(function(){ viewType = this.value; applyViewType(); });
    $('#items-per-page').change(function(){ itemsPerPage = parseInt(this.value); renderImages(); });

    $(window).click(function(e){
        if(e.target.id==='createFolderModal'){ closeModal('createFolderModal'); $('#newFolderName').val(''); }
        if(e.target.id==='imageInfoModal'){ closeModal('imageInfoModal'); }
        if(e.target.id==='imageEditModal'){ closeModal('imageEditModal'); if(cropper){cropper.destroy(); cropper=null;} }
    });

    loadFolders();
});

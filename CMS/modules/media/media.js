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
                const folderMedia = media.filter(m => m.folder === f);
                const count = folderMedia.length;
                const totalBytes = folderMedia.reduce((s,m)=>s+parseInt(m.size||0),0);
                const lastMod = folderMedia.reduce((m,i)=>i.modified_at && i.modified_at>m?i.modified_at:m,0);
                const meta = count+' files • '+formatFileSize(totalBytes)+' • Last edited '+(lastMod?new Date(lastMod*1000).toLocaleDateString():'');
                const item = $('<div class="folder-item" data-folder="'+f+'">\
                    <div class="folder-info"><h3>'+f+'</h3><p class="folder-meta">'+meta+'</p></div>\
                </div>');
                item.click(function(){ selectFolder(f); });
                list.append(item);
            });

            // Update stats
            $('#totalFolders').text((res.folders || []).length);
            $('#totalImages').text(media.length);
            const totalBytes = media.reduce((sum, m) => sum + (parseInt(m.size) || 0), 0);
            $('#totalSize').text(formatFileSize(totalBytes));

            if(currentFolder){
                $('.folder-item[data-folder="'+currentFolder+'"]').addClass('active');
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
            currentFolderMeta = currentImages.length+' files • '+formatFileSize(totalBytes)+' • Last edited '+(lastMod?new Date(lastMod*1000).toLocaleDateString():'');
            $('#folderStats').text(currentFolderMeta);
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
                const src = img.thumbnail ? img.thumbnail : img.file;
                const card = $('<div class="image-card" data-id="'+img.id+'">\
                        <div class="image-preview">\
                            <img src="'+src+'" alt="'+img.name+'">\
                            <div class="image-overlay">\
                                <div>\
                                    <button class="info-btn" data-id="'+img.id+'">ℹ</button>\
                                    <button class="edit-btn" data-id="'+img.id+'">✎</button>\
                                    <button class="remove-btn" data-id="'+img.id+'">×</button>\
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

    function uploadFiles(files){
        if(!currentFolder || !files.length) return;
        const fd = new FormData();
        Array.from(files).forEach(f => fd.append('files[]', f));
        fd.append('folder', currentFolder);
        fd.append('tags','');
        $('#uploadLoader').show();
        $.ajax({
            url: 'modules/media/upload_media.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(){
            $('#fileInput').val('');
            loadImages();
            loadFolders();
        }).always(function(){
            $('#uploadLoader').hide();
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

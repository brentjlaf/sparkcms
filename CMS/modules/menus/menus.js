$(function(){
    let pages = [];

    function loadPages(callback){
        $.getJSON('modules/pages/list_pages.php', function(data){
            pages = data;
            if(callback) callback();
        });
    }

    function countItems(items){
        let c = 0;
        if(Array.isArray(items)){
            items.forEach(it => {
                c++;
                if(it.children) c += countItems(it.children);
            });
        }
        return c;
    }

    function loadMenus(){
        $.getJSON('modules/menus/list_menus.php', function(data){
            const tbody = $('#menusTable tbody').empty();
            data.forEach(menu => {
                tbody.append(
                    '<tr data-id="'+menu.id+'">'+
                    '<td class="name">'+menu.name+'</td>'+
                    '<td class="count">'+countItems(menu.items)+'</td>'+
                    '<td><button class="btn btn-secondary editMenu">Edit</button> '+
                    '<button class="btn btn-danger deleteMenu">Delete</button></td>'+
                    '</tr>'
                );
            });
        });
    }

    function toggleFields($item){
        const type = $item.find('.type-select').val();
        if(type === 'page'){
            $item.find('.page-select').show();
            $item.find('.link-input').hide();
        } else {
            $item.find('.page-select').hide();
            $item.find('.link-input').show();
        }
    }

    function prepareItem(item){
        item = item || {};
        if(!item.type){
            if(item.link && item.link.startsWith('/')){
                item.type = 'page';
                const slug = item.link.substring(1);
                const p = pages.find(pg => pg.slug === slug);
                if(p) item.page = p.id;
            } else {
                item.type = 'custom';
            }
        }
        return item;
    }

    function initSortable($list){
        $list.sortable({
            handle: '.drag-handle',
            connectWith: '.menu-list',
            placeholder: 'menu-placeholder'
        });
    }

    function createItemElement(item){
        item = prepareItem(item);
        const pageOptions = pages.map(p => '<option value="'+p.id+'">'+p.title+'</option>').join('');
        const $li = $('<li></li>');
        const $item = $('<div class="menu-item"></div>');
        $item.append('<span class="drag-handle">&#9776;</span>');
        $item.append('<select class="form-select type-select"><option value="page">Link to Page</option><option value="custom">Custom Link</option></select>');
        $item.append('<select class="form-select page-select">'+pageOptions+'</select>');
        $item.append('<input type="text" class="form-input link-input" placeholder="URL">');
        $item.append('<input type="text" class="form-input label-input" placeholder="Title">');
        $item.append('<label style="white-space:nowrap;"><input type="checkbox" class="new-tab"> New Tab</label>');
        $item.append('<button type="button" class="btn btn-secondary btn-sm addChild">+ Sub</button>');
        $item.append('<button type="button" class="btn btn-danger removeItem">X</button>');
        $li.append($item);
        $li.append('<ul class="menu-list"></ul>');
        $item.find('.type-select').val(item.type);
        if(item.page) $item.find('.page-select').val(item.page);
        $item.find('.link-input').val(item.link || '');
        $item.find('.label-input').val(item.label || '');
        $item.find('.new-tab').prop('checked', item.new_tab ? true : false);
        toggleFields($item);
        return $li;
    }

    function addItem(item, $parent){
        $parent = $parent || $('#menuItems');
        const $el = createItemElement(item || {});
        $parent.append($el);
        initSortable($el.children('ul'));
        if(item && item.children){
            item.children.forEach(ch => addItem(ch, $el.children('ul')));
        }
    }

    $('#addMenuItem').on('click', function(){ addItem(); });

    initSortable($('#menuItems'));

    $('#menuItems').on('click', '.removeItem', function(){ $(this).closest('li').remove(); });
    $('#menuItems').on('click', '.addChild', function(){
        const $li = $(this).closest('li');
        addItem({}, $li.children('ul'));
    });
    $('#menuItems').on('change', '.type-select', function(){ toggleFields($(this).closest('.menu-item')); });

    $('#newMenuBtn').on('click', function(){
        $('#menuFormTitle').text('Add Menu');
        $('#menuId').val('');
        $('#menuName').val('');
        $('#menuItems').empty();
        addItem();
        $('#menuFormCard').show();
        $('#cancelMenuEdit').show();
    });

    $('#cancelMenuEdit').on('click', function(){
        $('#menuFormCard').hide();
        $('#menuForm')[0].reset();
        $('#menuItems').empty();
    });

    $('#menusTable').on('click', '.editMenu', function(){
        const id = $(this).closest('tr').data('id');
        $.getJSON('modules/menus/list_menus.php', function(data){
            const menu = data.find(m => m.id == id);
            if(!menu) return;
            $('#menuFormTitle').text('Edit Menu');
            $('#menuId').val(menu.id);
            $('#menuName').val(menu.name);
            $('#menuItems').empty();
            if(menu.items && menu.items.length){
                menu.items.forEach(it => addItem(it));
            } else {
                addItem();
            }
            $('#menuFormCard').show();
            $('#cancelMenuEdit').show();
        });
    });

    $('#menusTable').on('click', '.deleteMenu', function(){
        const row = $(this).closest('tr');
        confirmModal('Delete this menu?').then(ok => {
            if(!ok) return;
            $.post('modules/menus/delete_menu.php', {id: row.data('id')}, function(){
                loadMenus();
            });
        });
    });

    function gatherItems($list){
        const items = [];
        $list.children('li').each(function(){
            const $it = $(this).children('.menu-item');
            const item = {
                type: $it.find('.type-select').val(),
                page: parseInt($it.find('.page-select').val()) || null,
                link: $it.find('.link-input').val(),
                label: $it.find('.label-input').val(),
                new_tab: $it.find('.new-tab').is(':checked')
            };
            const children = gatherItems($(this).children('ul'));
            if(children.length) item.children = children;
            items.push(item);
        });
        return items;
    }

    $('#menuForm').on('submit', function(e){
        e.preventDefault();
        const data = {
            id: $('#menuId').val(),
            name: $('#menuName').val(),
            items: JSON.stringify(gatherItems($('#menuItems')))
        };
        $.post('modules/menus/save_menu.php', data, function(){
            $('#menuFormCard').hide();
            $('#menuForm')[0].reset();
            $('#menuItems').empty();
            loadMenus();
        });
    });

    loadPages(function(){
        loadMenus();
    });
});

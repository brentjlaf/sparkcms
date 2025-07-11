$(function(){
    function loadUsers(){
        $.getJSON('modules/users/list_users.php', function(data){
            const tbody = $('#usersTable tbody').empty();
            data.forEach(u => {
                tbody.append(
                    '<tr data-id="'+u.id+'" data-username="'+u.username+'" data-role="'+u.role+'" data-status="'+u.status+'">'+
                    '<td class="username">'+u.username+'</td>'+
                    '<td class="role">'+u.role+'</td>'+
                    '<td class="status">'+u.status+'</td>'+
                    '<td><button class="btn btn-secondary editUser">Edit</button> '+
                    '<button class="btn btn-danger deleteUser">Delete</button></td>'+
                    '</tr>'
                );
            });
        });
    }

    $('#newUserBtn').on('click', function(){
        $('#userFormTitle').text('Add User');
        $('#userId').val('');
        $('#userForm')[0].reset();
        $('#userFormCard').show();
        $('#cancelUserEdit').show();
    });

    $('#cancelUserEdit').on('click', function(){
        $('#userFormCard').hide();
        $('#userForm')[0].reset();
    });

    $('#usersTable').on('click', '.editUser', function(){
        const row = $(this).closest('tr');
        $('#userFormTitle').text('Edit User');
        $('#userId').val(row.data('id'));
        $('#username').val(row.data('username'));
        $('#password').val('');
        $('#role').val(row.data('role'));
        $('#status').val(row.data('status'));
        $('#userFormCard').show();
        $('#cancelUserEdit').show();
    });

    $('#usersTable').on('click', '.deleteUser', function(){
        const row = $(this).closest('tr');
        confirmModal('Delete this user?').then(ok => {
            if(!ok) return;
            $.post('modules/users/delete_user.php', {id: row.data('id')}, function(){
                loadUsers();
            });
        });
    });

    $('#userForm').on('submit', function(e){
        e.preventDefault();
        $.post('modules/users/save_user.php', $(this).serialize(), function(){
            $('#userFormCard').hide();
            $('#userForm')[0].reset();
            loadUsers();
        });
    });

    loadUsers();
});

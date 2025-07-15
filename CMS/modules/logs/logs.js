// File: logs.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }
    function loadLogs(){
        $.getJSON('modules/logs/list_logs.php', function(data){
            const tbody = $('#logsTable tbody').empty();
            (data || []).forEach(log => {
                const date = new Date(log.time * 1000).toLocaleString();
                tbody.append('<tr>'+
                    '<td class="time">'+date+'</td>'+
                    '<td class="user">'+escapeHtml(log.user)+'</td>'+
                    '<td class="page">'+escapeHtml(log.page_title)+'</td>'+
                    '<td class="action">'+escapeHtml(log.action)+'</td>'+
                '</tr>');
            });
        });
    }
    loadLogs();
});

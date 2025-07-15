// File: analytics.js
$(function(){
    function escapeHtml(str){ return $('<div>').text(str).html(); }
    function loadAnalytics(){
        $.getJSON('modules/analytics/analytics_data.php', function(data){
            const tbody = $('#analyticsTable tbody').empty();
            (data || []).forEach(row => {
                tbody.append('<tr>'+
                    '<td class="title">'+escapeHtml(row.title)+'</td>'+
                    '<td class="slug">'+escapeHtml(row.slug)+'</td>'+
                    '<td class="views">'+row.views+'</td>'+
                '</tr>');
            });
        });
    }
    loadAnalytics();
});

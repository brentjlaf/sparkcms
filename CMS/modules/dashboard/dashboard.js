// File: dashboard.js
$(function(){
    function loadStats(){
        $.getJSON('modules/dashboard/dashboard_data.php', function(data){
            $('#statPages').text(data.pages);
            $('#statMedia').text(data.media);
            $('#statUsers').text(data.users);
            $('#statViews').text(data.views);
        });
    }
    loadStats();
});

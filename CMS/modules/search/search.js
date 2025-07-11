// File: search.js
$(function(){
    const query = $('#search').data('query');
    if(query){
        $('#pageTitle').text('Search: ' + query);
    } else {
        $('#pageTitle').text('Search');
    }
});

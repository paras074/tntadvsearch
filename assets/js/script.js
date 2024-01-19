jQuery(document).ready(function ($) {
   $('#tnt-search-input').keypress(function(e){
        if ( e.which == 13 ) return false;
        //or...
        if ( e.which == 13 ) e.preventDefault();
    });


    $('#tnt-search-input').on('input', function () {
        var searchTerm = $(this).val();
        $.ajax({
            url: tnt_ajax_object.ajaxurl,
            type: 'post',
            data: {
                action: 'tnt_search_action',
                search_term: searchTerm,
            },
            success: function (response) {
                $('#tnt-search-results').html(response);
            },
        });
    });
});

$ = jQuery;

$(document).ready(function() {
  jQuery.each($('#map'),function(map_i, map){
    // Each map boundary is represented by a path 
    var src = $(map).attr('src');
    var svg = $(document.createElement('SVG'))
      .attr('width', $(map).attr('width')+'px')
      .attr('height', $(map).attr('height')+'px') 
      .addClass('size-large')
      .attr('id','svgmap');
      ;
    $('#map').parent().append(svg);

    jQuery.ajax({
      type     : 'GET',
      url      : src,
      cache    : false,
      dataType : 'xml',
      async    : true,
      beforeSend : (function() {
        display_wait_notification();
        jQuery('#doctitle').html('Loading '+src);
      }),
      complete : (function(jqueryXHR, textStatus) {
        remove_wait_notification();
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        if ( !(data === null ) ) {
          try {
            $('#svgmap').html($(data).children().first());
            jQuery.each($('#svgmap').find('path'),function(path_index, path){
              $(path).click(function(event){
                document.title = $(this).children('title').first().html();
                $(this).css({
                  'fill' : '#000000'
                });
              });
            });
            $('#map').hide();
          }
          catch (e) {
            alert('No SVG');
          }
        }
      })
    });
  });
});

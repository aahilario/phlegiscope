function initialize_dossier_triggers() {
  enable_proxied_links('human-element-dossier-trigger',{
    beforeSend : (function() {
      $('#doctitle').html("Dossier loader triggered. Please wait.");
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success : (function(data, httpstatus, jqueryXHR) {
      var response = data.error ? data.message : data.linkset;
      var markup = data.markup;
      var responseheader = data.responseheader;
      var referrer = data.referrer;
      var url = data.url;
      var contenttype = data.contenttype ? data.contenttype : '';
      $('#doctitle').html("Legiscope");
      if ( /^text\/html/.test(contenttype) ) {
        replace_contentof('original', data.original);
        // $('[class*=alternate-original]').css({ 'background-color': '#DDD' });
        initialize_remote_links(); 
      }
      replace_contentof('currenturl',
        $(document.createElement('a'))
        .attr('href', url)
        .attr('target','blank')
        .html(url)
      );

      /*
       * Javascript fragment to trigger cycling through dossier entries
      setTimeout(function(){
      $('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
      },5000);
      */
    })
  });
}

function update_representatives_avatars() {
  $('img[class*=representative-avatar]').each(function(){
    var avatar_id = $(this).attr('id').replace(/^image-/,'imagesrc-');
    if ( $(this).attr('src').length > 0 ) return;
    var member_uuid = $(this).attr('id').replace(/^image-/,'');
    var avatar_url = $('input[id='+avatar_id+']').val();
    $.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : avatar_url, cache : $('#cache').prop('checked'), member_uuid : member_uuid, fr : true },
      cache    : false,
      dataType : 'json',
      async    : false,
      beforeSend : (function() {
        display_wait_notification();
      }),
      complete : (function(jqueryXHR, textStatus) {
        remove_wait_notification();
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        var altmarkup = data.altmarkup ? data.altmarkup : null;
        var total_image_width = 0;
        $('img[id=image-'+member_uuid+']').attr('src', altmarkup);
        $("div[class=dossier-strip]").find("img").each(function(){
          total_image_width += ($(this).outerWidth() + 4);
        });
        $("div[class=dossier-strip]").width(total_image_width);
      })
    });
  });
}


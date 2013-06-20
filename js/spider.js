var Spider = {};
var hotsearch_timer = null;

var preload_a = null;
var preload_n = 0;

function replace_contentof(a,c) {
  var count_alternates = 0;
  jQuery('div[class*=alternate-'+a+']').each(function(){
    count_alternates++;
  }); 
  var target = count_alternates == 1 ? ('#'+jQuery('div[class*=alternate-'+a+']').attr('id')) : ('#'+a);
  jQuery(target).children().remove();
  jQuery(target).html('');
  jQuery(target).append(c);
}

function display_wait_notification() { 
  jQuery('#search-wait').children().remove();
  jQuery('#search-wait').append(jQuery(document.createElement('IMG'))
    .attr('src', 'data:image/gif;base64,R0lGODlhEAAQALMAAP8A/7CxtXBxdX1+gpaXm6OkqMnKzry+womLj7y9womKjwAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQBCgAAACwAAAAAEAAQAAAESBDICUqhmFqbZwjVBhAE9n3hSJbeSa1sm5HUcXQTggC2jeu63q0D3PlwAB3FYMgMBhgmk/J8LqUAgQBQhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAES3DJuUKgmFqb5znVthQF9h1JOJKl96UT27oZSRlGNxHEguM6Hu+X6wh7QN2CRxEIMggExumkKKLSCfU5GCyu0Sm36w3ryF7lpNuJAAAh+QQBCgALACwAAAAAEAAQAAAESHDJuc6hmFqbpzHVtgQB9n3hSJbeSa1sm5GUIHRTUSy2jeu63q0D3PlwCx1lMMgQCBgmk/J8LqULBGJRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYyhmFqbpxDVthwH9n3hSJbeSa1sm5HUMHRTECy2jeu63q0D3PlwCx0FgcgUChgmk/J8LqULAmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYSgmFqb5xjVthgG9n3hSJbeSa1sm5EUgnTTcSy2jeu63q0D3PlwCx2FQMgEAhgmk/J8LqWLQmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJucagmFqbJ0LVtggC9n3hSJbeSa1sm5EUQXSTYSy2jeu63q0D3PlwCx2lUMgcDhgmk/J8LqWLQGBRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuRCimFqbJyHVtgwD9n3hSJbeSa1sm5FUUXSTICy2jeu63q0D3PlwCx0lEMgYDBgmk/J8LqWLw2FRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuQihmFqbZynVtiAI9n3hSJbeSa1sm5FUEHTTMCy2jeu63q0D3PlwCx3lcMgIBBgmk/J8LqULg2FRhV6z2q0VF94iJ9pOBAA7')
    .attr('id', 'busy-notification-wait')
  );
}

function remove_wait_notification() {
  jQuery('#search-wait').children().remove();
}

function preload_worker_original() {
  if ( typeof preload_a != 'null' && preload_n < preload_a.length ) {
    var hashpair   = preload_a[preload_n++];
    var hash       = hashpair.hash;
    var live       = jQuery('#seek').prop('checked') ? jQuery('#seek').prop('checked') : hashpair.live;
    var linkstring = jQuery('a[id='+hash+']').attr('href');

    jQuery('a[id='+hash+']').addClass('uncached').removeClass('cached');

    jQuery.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : linkstring, proxy : jQuery('#proxy').prop('checked'), modifier : live, fr: true, linktext: jQuery('a[class*=legiscope-remote][id='+hash+']').html() },
      cache    : false,
      dataType : 'json',
      async    : true,
      beforeSend : (function() {
        display_wait_notification();
        jQuery('#doctitle').html('Loading '+linkstring);
      }),
      complete : (function(jqueryXHR, textStatus) {
        remove_wait_notification();
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        jQuery('a[id='+hash+']').addClass('cached').removeClass('uncached');
        if ( data && data.original ) replace_contentof('original',data.original);
				if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        preload_worker();
      })
    });
  } 
}

function preload_worker() {

  var hash       = jQuery("div[id=original]").find('a[class*=legiscope-remote][class*=uncached]').first().attr('id');
  var live       = jQuery('#seek').prop('checked');
  var linkstring = jQuery('a[id='+hash+']').attr('href');

  jQuery('a[id='+hash+']').addClass('uncached').removeClass('cached');

  jQuery.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : { url : linkstring, proxy : jQuery('#proxy').prop('checked'), modifier : live, fr: true, linktext: jQuery('a[class*=legiscope-remote][id='+hash+']').html() },
    cache    : false,
    dataType : 'json',
    async    : true,
    beforeSend : (function() {
      display_wait_notification();
      jQuery('#doctitle').html('Loading '+linkstring);
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (function(data, httpstatus, jqueryXHR) {
      jQuery('a[id='+hash+']').addClass('cached').removeClass('uncached');
      if ( data && data.original ) replace_contentof('original',data.original);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      setTimeout((function(){preload_worker();}),100);
    })
  });
}

function preload(components) {
  jQuery.ajax({
    type     : 'POST',
    url      : '/preload/',
    data     : { links: components, modifier : jQuery('#seek').prop('checked') },
    cache    : false,
    dataType : 'json',
    async    : true,
    beforeSend : (function() {
      display_wait_notification();
      preload_n = 0;
      if ( typeof preload_a != 'null' ) preload_a = null;
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (function(data, httpstatus, jqueryXHR) {
      var uncached_entries = data.count ? data.count : 0;
      var uncached_list = data.uncached ? data.uncached : [];
			if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      preload_a = new Array(uncached_entries);
      preload_n = 0;
      for (var n in uncached_list) {
        var e = uncached_list[n];
        preload_a[preload_n] = { hash : e.hash, live : e.live };
        preload_n++;
      }
      preload_n = 0;
      preload_worker();
    })
  });
}

function initialize_linkset_clickevents(linkset,childtag) {
  var child_tags = typeof childtag == 'undefined' ? 'li' : childtag;
  jQuery(linkset).on('contextmenu',function(){
    return false;
  }).click(function(e){

		var self = jQuery(this);

    e.stopPropagation();
    e.preventDefault();

    if ( jQuery(this).prop && jQuery(this).prop('coordinates') ) {
			if ( jQuery(this).hasClass('upper-section') || jQuery(this).hasClass('lower-section') ) {
				var coordinates = jQuery(this).prop('coordinates');
				jQuery.ajax({
					type     : 'POST',
					url      : '/reorder/',
					data     : { clusterid : jQuery(this).attr('id'), proxy : jQuery('#proxy').prop('checked'), move : jQuery(this).hasClass('upper-section') ? -1 : 1 },
					cache    : false,
					dataType : 'json',
					async    : true,
					beforeSend : (function() {
						display_wait_notification();
					}),
					complete : (function(jqueryXHR, textStatus) {
						remove_wait_notification();
					}),
					success  : (function(data, httpstatus, jqueryXHR) {

						var linkset = data.linkset;

						if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);

						if ( linkset && linkset.length > 0 ) {
							replace_contentof('linkset', linkset);
							initialize_linkset_clickevents(self,child_tags);
							setTimeout(function(){ initialize_remote_links(); },1);
						}
					})
				});
				return;
			}
		}

    var components = new Array(jQuery(this).children(child_tags).length);
    var component_index = 0;
    jQuery(this).children(child_tags).find('a').each(function(){
      if ( component_index > 300 ) return;
      var linkset_child = jQuery(this).attr('id');
      if ( jQuery(this).hasClass('cached') ) return;
      components[component_index] = linkset_child; 
      component_index++;
    });
    preload(components);

  }).find(child_tags).each(function(){
    jQuery(this).on('contextmenu', function(){
      return false;
    }).mouseup(function(e){
      if (2 == parseInt(jQuery(e).prop('button'))) {
        jQuery(this).parent().click();
      }
      return false;
    });
  });
}

function std_seek_response_handler(data, httpstatus, jqueryXHR) {

  if (typeof data == 'null') return;

	var response = data && data.error ? data.message : data.linkset;
	var markup = data.markup;
	var responseheader = data.responseheader;
	var referrer = data.referrer;
	var url = data.url;
	var contenttype = data.contenttype ? data.contenttype : '';
	var retainoriginal = data.retainoriginal ? data.retainoriginal : false;
  var targetframe = data.targetframe ? data.targetframe : '[class*=alternate-original]'; 

	jQuery('div[class*=contentwindow]').each(function(){
		if (jQuery(this).attr('id') == 'issues') return;
		if (retainoriginal)
		if (jQuery(this).attr('id') == 'original') return;
		jQuery(this).children().remove();
	});

	if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);

	if ( /^application\/pdf/.test(contenttype) ) {
    if ( !(jQuery('#spider').prop('checked')) ) {
      var target_container = jQuery(retainoriginal ? ('#'+jQuery(targetframe).first().attr('id')) : '#original');
      display_wait_notification();
      PDFJS.getDocument('/fetchpdf/'+data.contenthash).then(function(pdf){
        var np = pdf.numPages;
        for ( var pagecounter = 1 ; pagecounter <= np ; pagecounter++ ) { 
          if ( pagecounter == 1 ) jQuery(target_container).children().remove();
          pdf.getPage(pagecounter).then(function(page){
            var pagecount = jQuery(target_container).children().length;
            var target_name = "pdfdoc-"+pagecount;
            jQuery('#doctitle').html("Target: "+target_name);
            jQuery(target_container).append(jQuery(document.createElement('CANVAS'))
              .addClass('inline-pdf')
              .attr('id', target_name)
            );
            var scale = 1.0;
            var viewport = page.getViewport(scale);
            var canvas = document.getElementById(target_name);
            if ( typeof canvas != 'undefined' && canvas != null) {
              var context = canvas.getContext('2d');
              canvas.height = viewport.height;
              canvas.width = jQuery(canvas).parent().first().outerWidth();
              var renderContext = {
                canvasContext : context,
                viewport : viewport
              };
              page.render(renderContext);
            }
          });
        }
        remove_wait_notification();
      });
    }
	} else {
		replace_contentof('original',data.original);
		if ( /^text\/html/.test(contenttype) ) {
			if ( response && response.length > 0 ) {
				replace_contentof('linkset', response);
				initialize_linkset_clickevents(jQuery('ul[class*=link-cluster]'),'li');
			}
		}
		jQuery('#siteURL').val(referrer);
		replace_contentof('markup',markup);
		replace_contentof('responseheader',responseheader);
		replace_contentof('referrer',jQuery(document.createElement('A'))
			.addClass('legiscope-remote')
			.attr('href', referrer)
			.html('Back')
		);
		replace_contentof('currenturl',
			jQuery(document.createElement('A'))
			.attr('href', data.url)
			.attr('target','blank')
			.append(data.url)
		);
		initialize_authentication_inputs();
		jQuery('#tab_'+data.defaulttab).click();
	}
	setTimeout(function(){ 
		initialize_remote_links(); 
		var seek_enabled    = jQuery('#seek').prop('checked');
		if ( !seek_enabled ) return;
		var component_count = jQuery("span[class*=search-match-searchlisting]").length || jQuery("div[id=original]").find('a[class*=legiscope-remote]').length;
		var component_index = 0;
		if ( 0 == component_count ) {
			jQuery("div[id=original]").children('a').each(function(){
				component_count++;
			});
		}
		jQuery('#doctitle').html("Seek: +"+component_count);
		if ( 0 == component_count ) return;
		if ( components > 300 ) components = 300;
		var components = new Array(component_count);
		jQuery("div[id=original]").find('a[class*=legiscope-remote]').each(function(){
			if ( component_index > 300 ) return;
			var linkset_child = jQuery(this).attr('id');
			if ( jQuery(this).hasClass('cached') ) return;
			components[component_index] = linkset_child; 
			component_index++;
		});
		preload(components);
		jQuery('a[class*=pull-in]').first().click(); 
		return true; 
	},1);
}

function load_content_window(a,ck,obj,data,handlers) {
  var object_text = typeof obj != 'undefined' ? jQuery(obj).html() : null;
  var std_data = (jQuery('#metalink').html().length > 0)
    ? { url : a, modifier : ck || jQuery('#seek').prop('checked'), proxy : jQuery('#proxy').prop('checked'), cache : jQuery('#cache').prop('checked'), linktext : object_text, metalink : jQuery('#metalink').html() } 
    : { url : a, modifier : ck || jQuery('#seek').prop('checked'), proxy : jQuery('#proxy').prop('checked'), cache : jQuery('#cache').prop('checked'), linktext : object_text }
    ;
  var async = (data && data.async) ? data.async : false;
  if ( typeof data != "undefined" && typeof data != "null" ) {
    for ( var i in data ) {
      var element = data[i];
      jQuery(std_data).prop(i, element);
    }
  }
  jQuery.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : std_data,
    cache    : false,
    dataType : 'json',
    async    : async,
    beforeSend : (handlers && handlers.beforeSend) ? handlers.beforeSend : (function() {
      jQuery('a[class*=legiscope-remote]').unbind('click');
      display_wait_notification();
    }),
    complete : (handlers && handlers.complete) ? handlers.complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (handlers && handlers.success) ? handlers.success : std_seek_response_handler
  });
}

function initialize_remote_links() {

  jQuery('a[class*=metapager]').unbind('click');

  jQuery('a[class*=metapager]').click(function(e){
    var content_id = /^switch-/.test(jQuery(this).attr('id')) ? ('content-'+jQuery(this).attr('id').replace(/^switch-/,'')) : null;
    var content = jQuery('span[id='+content_id+']').html();
    var data = jQuery('#jumpto') && jQuery('#jumpto').val() ?  { 'LEGISCOPE' : { coPage : jQuery('#jumpto').val() } } : null;
    jQuery('#metalink').html(content);
    e.stopPropagation();
    load_content_window(jQuery(this).attr('href'), jQuery(e).attr('metaKey') || jQuery('#seek').prop('checked'), jQuery(this), data);
    jQuery('#metalink').html('');
    return false;
  });


  jQuery('a[class*=fauxpost]').unbind('click');

  jQuery('a[class*=fauxpost]').click(function(e){
    var content_id = /^switch-/.test(jQuery(this).attr('id')) ? ('content-'+jQuery(this).attr('id').replace(/^switch-/,'')) : null;
    var content = jQuery('span[id='+content_id+']').html();
    jQuery('#metalink').html(content);
    e.stopPropagation();
    load_content_window(jQuery(this).attr('href'), jQuery(e).attr('metaKey') || jQuery('#seek').prop('checked'), jQuery(this));
    jQuery('#metalink').html('');
    return false;
  });

  enable_proxied_links('legiscope-remote');

  jQuery('span[class*=legiscope-refresh]').unbind('mouseout');
  jQuery('span[class*=legiscope-refresh]').mouseout(function(){
    jQuery(this).parent().children('span[class*=legiscope-refresh]').each(function(){
      jQuery(this)
        .unbind('click')
        .addClass('hover')
        .click(function(e){
          e.stopPropagation();
          load_content_window(jQuery(this).parent().children('a').attr('href'),'reload',null);
        }).mouseout(function(){
          var subject = this;
          setTimeout(function(){jQuery(subject).removeClass('hover');},1000);
        });
    });
  });

  jQuery('[class*=link-cluster]')
    .unbind('mouseenter')
    .unbind('mouseleave')
    .unbind('mousemove');

  jQuery('[class*=link-cluster]').mouseenter(function(e){
		if ( jQuery(this).hasClass('suppress-reorder') ) return;
    jQuery(this).prop('linkset-location', jQuery(this).offset());
  }).mouseleave(function(e){
		if ( jQuery(this).hasClass('suppress-reorder') ) return;
    jQuery(this).removeClass('upper-section').removeClass('lower-section');
    jQuery('#doctitle').html('Legiscope');
  }).mousemove(function(e){
		if ( jQuery(this).hasClass('suppress-reorder') ) return;
    if ( !jQuery(this).prop || !jQuery(this).prop('linkset-location') ) return;
    var y = (jQuery(this).innerHeight() / 2) - (e.pageY - jQuery(this).prop('linkset-location').top);
    var x = (e.pageX - jQuery(this).prop('linkset-location').left) - (jQuery(this).innerWidth() / 2);
    jQuery(this).removeClass('upper-section').removeClass('lower-section');
    jQuery(this).prop('coordinates',{ x : x, y : y });
    if ( x > 0 ) return;
    jQuery(this).addClass( y >= 0 ? 'upper-section' : 'lower-section' );
  });
  return true;
}

function initialize_spider_tabs() {
  jQuery('#contenttabs').children().remove();
  jQuery('#sitecontent').children().each(function(){
    if ( jQuery(this).attr('id') == 'contenttabs' ) return;
    jQuery('#contenttabs').append(
      jQuery(document.createElement('SPAN'))
        .attr('id','tab_'+jQuery(this).attr('id'))
        .addClass('contenttab')
        .html(jQuery(this).attr('id').toUpperCase())
        .click(function(e){
          var target = jQuery(this).attr('id').replace(/^tab_/,'');
          jQuery('#contenttabs').children().removeClass('activetab').removeClass('inactivetab');
          jQuery('#contenttabs').addClass('inactivetab');
          jQuery(this).addClass('activetab').removeClass('inactivetab');
          jQuery('div[class*=contentwindow]').addClass('hidden');
          jQuery('div[id='+target+']').removeClass('hidden');
        })
    );
  }); 
  jQuery('#contenttabs').append(
    jQuery(document.createElement('SPAN'))
      .attr('id','currenturl')
      .addClass('contenttab')
  ).prepend(
    jQuery(document.createElement('SPAN'))
      .attr('id','referrer')
      .addClass('contenttab')
  );
}

function enable_proxied_links(classname,handlers) {
  var handler = typeof handlers == 'undefined' ? {} : handlers; 
  jQuery('a[class*='+classname+']')
    .unbind('click');

  jQuery('a[class*='+classname+']').click(function(e){
    e.stopPropagation();
    load_content_window(
			jQuery(this).attr('href'),
		 	jQuery(e).attr('metaKey'),
		 	jQuery(this),
		 	{ async : true },
		 	handler
		);
    return false;
  });
}

function initialize_hot_search() {
  jQuery('#keywords').keyup(function(e) {
    var self = this;
    if ( typeof hotsearch_timer != 'null' ) {
      clearTimeout(hotsearch_timer);
      hotsearch_timer = null;
    }
    jQuery(this).addClass('hotsearch-active');
    jQuery('#doctitle').html(jQuery(this).val());
    hotsearch_timer = setTimeout((function(){
      jQuery(self).removeClass('hotsearch-active');
      clearTimeout(hotsearch_timer);
      hotsearch_timer = null;
      jQuery.ajax({
        type     : 'POST',
        url      : '/keyword/',
        data     : { fragment : jQuery('#keywords').val(), proxy : jQuery('#proxy').prop('checked') },
        cache    : false,
        dataType : 'json',
        beforeSend : (function() {
          display_wait_notification();
        }),
        complete : (function(jqueryXHR, textStatus) {
          remove_wait_notification();
        }),
        success  : (function(data, httpstatus, jqueryXHR) {
          var count = 0; 
          var records = data.records ? data.records : [];
          var returns = data.count ? data.count : 0;
          var retainoriginal = data.retainoriginal ? data.retainoriginal : false;
					if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
          jQuery('#currenturl').html("Matches: "+returns);
          jQuery('div[class*=contentwindow]').each(function(){
            if (jQuery(this).attr('id') == 'issues') return;
            if (retainoriginal)
            if (jQuery(this).attr('id') == 'original') return;
            jQuery(this).children().remove();
          });
					if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
          if ( !(0 < returns) ) return;
          replace_contentof('original',jQuery(document.createElement('UL')).attr('id','searchresults'));
          for ( var r in records ) {
            var record = records[r];
            var meta = record.meta ? record.meta : '';
            var referrers = record.referrers ? record.referrers : [];
            var referrerset = document.createElement('UL');
            for ( var m in referrers ) {
              var referrer_url = referrers[m];
              jQuery(referrerset).append(jQuery(document.createElement('LI'))
                .append(jQuery(document.createElement('A'))
                  .attr('href', referrer_url)
                  .addClass('legiscope-remote')
                  .html(referrer_url)
                )
              );
            }
            jQuery('ul[id=searchresults]').append(
              jQuery(document.createElement('LI'))
                .addClass('search-match')
                .append(jQuery(document.createElement('A'))
                  .attr('href', record.url)
                  .attr('id','match-'+record.hash)
                  .addClass('legiscope-remote')
                  .addClass('search-match')
                  .addClass('cached')
                  .html(record.sn)
                )
                .append(jQuery(document.createElement('SPAN'))
                  .attr('id','url-'+record.hash)
                  .addClass('search-hit-category')
                  .html('&nbsp;|&nbsp;' + record.category)
                )
                .append(jQuery(document.createElement('SPAN'))
                  .attr('id','url-'+record.hash)
                  .addClass('search-hit-url')
                  .append(jQuery(document.createElement('A'))
                    .attr('href', record.url)
                    .attr('id','match-link-'+record.hash)
                    .addClass('legiscope-remote')
                    .addClass('search-match-url')
                    .addClass('cached')
                    .html(record.url)
                  )
                )
                .append(jQuery(document.createElement('SPAN'))
                  .attr('id','description-'+record.hash)
                  .addClass('search-hit-desc')
                  .html(record.description)
                )
                .append(jQuery(referrerset))
            );
            count++;
            if ( count > 100 ) break;
          }
          jQuery('ul[id=searchresults]').append(
            jQuery(document.createElement('LI'))
              .append(jQuery(document.createElement('SPAN'))
                .html('Total matches: '+(data.count ? data.count : count))
              )
          );
          jQuery('#siteURL').focus();
          initialize_remote_links();
          jQuery('#tab_original').click();
        })
      });

    }),1000);
  });
}

function initialize_authentication_inputs() {
  jQuery('[class*=authentication-tokens]').unbind('keyup');
  jQuery('[class*=authentication-tokens]').keyup(function(e){
    if (jQuery(e).attr('keyCode') == 13) {
      // Gather all input fields with the given selector class name,
      // and execute a /seek/ action 
      var data = {};
      jQuery('[class*=authentication-tokens]').each(function(){
        if (jQuery(this).attr('id') == 'authentication-target-url') return;
        var name = jQuery(this).attr('name');
        var val  = jQuery(this).val();
        jQuery(data).prop(name, val);
      });
      load_content_window(jQuery('[id=authentication-target-url]').val(), 'false', null, data);
    }
  });
}  

function initialize_spider() {
  jQuery('#siteURL').keydown(function(e){
    if( jQuery(e).attr('keyCode') == 13) {
      load_content_window(jQuery(this).val(), jQuery(e).attr('metaKey'), null, null);
    }
  });
  initialize_remote_links();
  initialize_spider_tabs();
  jQuery('#keywords').focus().select();
  initialize_hot_search();
}


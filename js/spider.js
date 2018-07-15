var Spider = {};
var hotsearch_timer = null;

var preload_container = null;
var preload_a = null;
var preload_n = 0;

function set_hoststats(m) {
  jQuery('ul[id=wp-admin-bar-root-legiscope]').remove();
  jQuery(m).insertAfter(
    jQuery('div[id=wp-toolbar]').find('ul[class*=ab-top-menu]').last()
  );
}

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

function update_a_age(data) {
  if ( data && data.age && data.urlhash ) {
    jQuery('a[id='+data.urlhash+']')
      .removeClass('age-newfetch')
      .removeClass('age-recent')
      .removeClass('age-10')
      .removeClass('age-100')
      .removeClass('age-1000')
      .removeClass('age-10000')
      .removeClass('age-100000')
      .removeClass('age-1m')
      .removeClass('age-10m')
      .removeClass('age-100m')
      .addClass(data.age);
  }
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

function preload_worker() {
  if ( typeof preload_a != 'null' && preload_n < preload_a.length ) {

    var hashpair   = preload_a[preload_n++];
    var hash       = hashpair.hash;
    var live       = jQuery('#seek').prop('checked') ? jQuery('#seek').prop('checked') : hashpair.live;
    var linkstring = jQuery('a[id='+hash+']').attr('href');

    if ( jQuery('a[id='+hash+']').hasClass('no-autospider') ) return false;

    jQuery('a[id='+hash+']').addClass('uncached').removeClass('cached').removeClass('traverse');

    if (!linkstring || !(0 < linkstring.length) ) {
      if ( jQuery('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    }
    else
    jQuery.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : linkstring, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : live, fr: true, linktext: jQuery('a[class*=legiscope-remote][id='+hash+']').html() },
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
        if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
        else
        if ( data && data.content ) replace_contentof(data && data.rootpage ? 'processed' : 'content',data.content);
        if ( data && data.hoststats ) set_hoststats(data.hoststats);
        if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
        if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        update_a_age(data);
        if ( jQuery('#spider').prop('checked') ) setTimeout((function(){preload_worker();}),100);
      })
    });
  } 
}

function preload_worker_unconditional() {

  var hash       = jQuery("div[id=content]").find('a[class*=legiscope-remote][class*=traverse]').first().attr('id');
  var live       = jQuery('#seek').prop('checked');
  var linkstring = jQuery('a[id='+hash+']').attr('href');

  if ( jQuery('a[id='+hash+']').hasClass('no-autospider') ) return false;

  jQuery('a[id='+hash+']').addClass('uncached').removeClass('traverse');

  if (!linkstring || !(0 < linkstring.length) ) {
    if ( jQuery('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    return false;
  }
  else
  jQuery.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : { url : linkstring, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : live, fr: true, linktext: jQuery('a[class*=legiscope-remote][id='+hash+']').html() },
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
      if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
      else
      if ( data && data.content ) replace_contentof(data && data.rootpage ? 'processed' : 'content',data.content);
      if ( data && data.hoststats ) set_hoststats(data.hoststats);
      if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      update_a_age(data);
      if ( jQuery('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    })
  });
  return false;
}

function preload(components) {
  jQuery.ajax({
    type     : 'POST',
    url      : '/preload/',
    data     : { links: components, update : jQuery('#update').prop('checked'), modifier : jQuery('#seek').prop('checked') },
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
      if ( data && data.hoststats ) set_hoststats(data.hoststats);
      if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      update_a_age(data);
      preload_a = new Array(uncached_entries);
      preload_n = 0;
      for (var n in uncached_list) {
        var e = uncached_list[n];
        preload_a[preload_n] = { hash : e.hash, live : e.live };
        preload_n++;
      }
      preload_n = 0;
      jQuery('#preload').prop('checked') ? preload_worker() : preload_worker_unconditional();
    })
  });
}

function handle_link_marker_click(m) {
  jQuery.ajax({
    type     : 'POST',
    url      : '/link/',
    data     : { url : jQuery('[id=currenturl-reflexive]').html(), link : jQuery(m).attr('id'), cache : jQuery('#cache').prop('checked') },
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
      var linkset = data.linkset ? data.linkset : null;
			var state = data.state ? (parseInt(data.state) == 1 ? true : false) : false;
			if ( 'strings' == typeof linkset ) {
				replace_contentof('linkset', linkset);
        initialize_linkset_clickevents(jQuery('ul[class*=link-cluster]'),'li');
			}
			jQuery(m).prop('checked', state);
			if ( state ) {
				jQuery(m)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.first()
					.prepend(jQuery(m).parent().detach());
		  }
			else {
				jQuery(m)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.last()
					.append(jQuery(m).parent().detach());
			}
    })
  });
  return false;
}

function initialize_linkset_clickevents(linkset,childtag) {
  var child_tags = typeof childtag == 'undefined' ? 'li' : childtag;
  jQuery(linkset).on('contextmenu',function(){
    return false;
  }).unbind('click').click(function(e){

    if (!jQuery('#spider').prop('checked')) {
      window.alert("Enable 'Spider' option to trigger preload");
      return;
    }
    var components = new Array(jQuery(this).children(child_tags).length);
    var component_index = 0;
    jQuery(this).children(child_tags).find('a').each(function(){
      if ( jQuery(this).hasClass('no-autospider') ) return true;
      if ( jQuery(this).hasClass('cached') && !jQuery('#spider').prop('checked') ) return true;
      if ( jQuery(this).hasClass('uncached') ) jQuery(this).addClass('traverse');
      if ( component_index > 300 ) return true;
      var linkset_child = jQuery(this).attr('id');
      components[component_index] = linkset_child; 
      component_index++;
      return true;
    });
    preload(components);

  }).find(child_tags).each(function(){
    jQuery(this).find('a[class*=legiscope-remote]').first().each(function(){
      var link_id = jQuery(this).attr('id');
      if ( jQuery(this).parentsUntil('ul').first().parent().attr('id') == 'systemrootlinks' ) return true;
      if ( link_id.length > 0 && jQuery(this).hasClass('markable') ) {
				var link_marked = jQuery(this).hasClass('is-source-root');
        jQuery(this).parent().first().each(function(){
          jQuery(this).children('input[id=m-'+link_id+']').remove();
          jQuery(this).prepend(
            jQuery(document.createElement('INPUT'))
              .attr('type','checkbox')
              .attr('value','1')
              .attr('id','m-'+link_id)
              .addClass('link-marker')
							.prop('checked',link_marked)
          );
        });
      }
      return true;
    });
    jQuery(this).on('contextmenu', function(){
      return false;
    }).mouseup(function(e){
      if (2 == parseInt(jQuery(e).prop('button'))) {
        jQuery(this).parent().click();
      }
      return false;
    });
  });
  jQuery('input[class*=link-marker]')
    .unbind('click')
    .on('click',function(e){
      e.stopPropagation();
      e.preventDefault();
      return handle_link_marker_click(jQuery(this)); 
    })
	  .each(function(){
			if ( jQuery(this).prop('checked') ) {
				jQuery(this)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.first()
					.prepend(jQuery(this).parent().detach());
		  }
		});
}

function std_seek_response_handler(data, httpstatus, jqueryXHR) {

  if (typeof data == 'null') return true;

  var linkset = data && data.error ? data.message : ( data && data.linkset ? data.linkset : {} );
  var referrer = data && data.referrer ? data.referrer : null
  var url = data && data.url ? data.url : null;
  var contenttype = data && data.contenttype ? data.contenttype : '';
  var retainoriginal = data && data.retainoriginal ? data.retainoriginal : false;
  var rootpage = data && data.rootpage ? data.rootpage : false;
  var targetframe = data && data.targetframe ? data.targetframe : '[class*=alternate-content]'; 

  if ( data && data.clicked ) {
     jQuery('a').removeClass('clicked');
     jQuery('a[id='+data.clicked+']').addClass('clicked');
  }

  jQuery('div[class*=contentwindow]').each(function(){
    if (jQuery(this).attr('id') == 'processed') return true;
    if (retainoriginal)
    if (jQuery(this).attr('id') == 'content') return true;
    jQuery(this).children().remove();
    return true;
  });

  if ( data && data.hoststats ) set_hoststats(data.hoststats);
  if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
  if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
  if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
  update_a_age(data);

  if ( /^application\/pdf/.test(contenttype) ) {
    if ( !(jQuery('#spider').prop('checked')) ) {
      var target_container = jQuery(retainoriginal ? ('#'+jQuery(targetframe).first().attr('id')) : '#subcontent');
      display_wait_notification();
      PDFJS.getDocument('/fetchpdf/'+data.urlhash).then(function(pdf){
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
            return true;
          });
        }
        remove_wait_notification();
        return true;
      });
    }
  }
 	else {
    if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
    else
    replace_contentof(rootpage ? 'processed' : 'content', data && data.content ? data.content : '');
    if ( /^text\/html/.test(contenttype) ) {
      if ( linkset && linkset.length > 0 ) {
        replace_contentof('linkset', linkset);
        initialize_linkset_clickevents(jQuery('ul[class*=link-cluster]'),'li');
      }
    }
    jQuery('#siteURL').val(referrer);
    replace_contentof('referrer',jQuery(document.createElement('A'))
      .addClass('legiscope-remote')
      .attr('href', referrer)
      .html('Back')
    );
    replace_contentof('currenturl',
      jQuery(document.createElement('A'))
      .attr('href', data && data.url ? data.url : '')
      .attr('target','blank')
			.attr('id','currenturl-reflexive')
      .append(data && data.url ? data.url : 'No Link')
    );
    initialize_authentication_inputs();
    if ( data && data.defaulttab )
    jQuery('#tab_'+data.defaulttab).click();
  }
  initialize_remote_links(); 
  setTimeout(function(){ 
    var seek_enabled    = jQuery('#seek').prop('checked');
    if ( !seek_enabled ) return false;
    var component_count = jQuery("span[class*=search-match-searchlisting]").length || jQuery("div[id=content]").find('a[class*=legiscope-remote]').length;
    var component_index = 0;
    if ( 0 == component_count ) {
      jQuery("div[id=content]").children('a').each(function(){
        component_count++;
        return true;
      });
    }
    jQuery('#doctitle').html("Seek: +"+component_count);
    if ( 0 == component_count ) return false;
    if ( components > 300 ) components = 300;
    var components = new Array(component_count);
    jQuery("div[id=content]").find('a[class*=legiscope-remote]').each(function(){
      if ( jQuery(this).hasClass('no-autospider') ) return true;
      if ( jQuery(this).hasClass('cached') ) return true;
      if ( jQuery(this).hasClass('uncached') ) jQuery(this).addClass('traverse');
      if ( component_index > 300 ) return true;
      var linkset_child = jQuery(this).attr('id');
      components[component_index] = linkset_child; 
      component_index++;
      return true;
    });
    preload(components);
    jQuery('a[class*=pull-in]').first().click(); 
    return true; 
  },1);
  return true;
}

function load_content_window(a,ck,obj,data,handlers) {
  var object_text = typeof obj != 'undefined' ? jQuery(obj).html() : null;
  var std_data = (jQuery('#metalink').html().length > 0)
    ? { url : a, update : jQuery('#update').prop('checked'), modifier : ck || jQuery('#seek').prop('checked'), proxy : jQuery('#proxy').prop('checked'), cache : jQuery('#cache').prop('checked'), linktext : object_text, metalink : jQuery('#metalink').html() } 
    : { url : a, update : jQuery('#update').prop('checked'), modifier : ck || jQuery('#seek').prop('checked'), proxy : jQuery('#proxy').prop('checked'), cache : jQuery('#cache').prop('checked'), linktext : object_text }
    ;
  var async = (data && data.async) ? data.async : true;
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
  return false;
}

function initialize_remote_links() {

  jQuery('a[class*=metapager]').unbind('click').click(function(e){
    var content_id = /^switch-/.test(jQuery(this).attr('id')) ? ('content-'+jQuery(this).attr('id').replace(/^switch-/,'')) : null;
    var content = jQuery('span[id='+content_id+']').html();
    var data = jQuery('#jumpto') && jQuery('#jumpto').val() ?  { 'LEGISCOPE' : { coPage : jQuery('#jumpto').val() } } : null;
    jQuery('#metalink').html(content);
    e.stopPropagation();
    load_content_window(jQuery(this).attr('href'), jQuery(e).attr('metaKey') || jQuery('#seek').prop('checked'), jQuery(this), data);
    jQuery('#metalink').html('');
    return false;
  });

  jQuery('span[class*=legiscope-refresh]').unbind('mouseout');
  jQuery('span[class*=legiscope-refresh]').mouseout(function(){
    jQuery(this).parent().children('span[class*=legiscope-refresh]').each(function(){
      jQuery(this)
        .addClass('hover')
        .unbind('click')
        .click(function(e){
          e.stopPropagation();
          load_content_window(jQuery(this).parent().children('a').attr('href'),'reload',null);
          return false;
        }).mouseout(function(){
          var subject = this;
          setTimeout(function(){jQuery(subject).removeClass('hover');},1000);
          return true;
        });
      return true;
    });
    return true;
  });

  enable_proxied_links('legiscope-remote');

  jQuery('a[class*=fauxpost]').unbind('click').click(function(e){
    var content_id = /^switch-/.test(jQuery(this).attr('id')) ? ('content-'+jQuery(this).attr('id').replace(/^switch-/,'')) : null;
    var content = jQuery('span[id='+content_id+']').html();
    jQuery('#metalink').html(content);
    e.stopPropagation();
    load_content_window(jQuery(this).attr('href'), jQuery(e).attr('metaKey') || jQuery('#seek').prop('checked'), jQuery(this));
    jQuery('#metalink').html('');
    return false;
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
        .unbind('click')
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
  jQuery('a[class*='+classname+']').each(function() {
    jQuery(this)
      .removeClass('proxied')
      .addClass('proxied')
      .unbind('click')
      .click(function(e){
        e.stopPropagation();
        return load_content_window(
          jQuery(this).attr('href'),
          jQuery(e).attr('metaKey'),
          jQuery(this),
          { async : true },
          handler
        );
      });
    return true;
  });
}

function initialize_hot_search(s,url) {
  jQuery(s).keyup(function(e) {
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
        url      : url,
        data     : { fragment : jQuery(s).val(), proxy : jQuery('#proxy').prop('checked') },
        cache    : false,
        dataType : 'json',
        async    : true,
        beforeSend : (function() {
          display_wait_notification();
          jQuery('#currenturl').html("");
        }),
        complete : (function(jqueryXHR, textStatus) {
          remove_wait_notification();
        }),
        success  : (function(data, httpstatus, jqueryXHR) {
          var count = 0; 
          var records = data && data.records ? data.records : [];
          var returns = data && data.count ? data.count : 0;
          var retainoriginal = data && data.retainoriginal ? data.retainoriginal : false;
          if ( data && data.hoststats ) set_hoststats(data.hoststats);
          if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
          if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
          update_a_age(data);
          jQuery('#currenturl').html((0 < returns) ? ("Matches: "+returns) : "");
          if ( !(0 < returns) ) {
            replace_contentof('subcontent',"<h2>No matches</h2>");
            return;
          }
          replace_contentof('subcontent',jQuery(document.createElement('UL')).attr('id','searchresults'));
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
                  .attr('id','title-'+record.hash)
                  .addClass('search-hit-title')
                  .html(record && record.title ? record.title : '')
                )
                .append(jQuery(document.createElement('SPAN'))
                  .attr('id','description-'+record.hash)
                  .addClass('search-hit-desc')
                  .html(record.description)
                )
                .append(jQuery(referrerset))
            );
            count++;
            if ( count > 500 ) break;
          }
          if ( data && data.focus_siteurl) jQuery('#siteURL').focus();
          initialize_remote_links();
          jQuery('#tab_processed').click();
        })
      });

    }),1000);
  });
  jQuery(s).focus().select().val('');
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
    return true;
  });
}  

function initialize_spider() {
  jQuery('#siteURL').keydown(function(e){
    if( jQuery(e).attr('keyCode') == 13) {
      load_content_window(jQuery(this).val(), jQuery(e).attr('metaKey'), null, null);
      return false;
    }
    return true;
  });
  initialize_remote_links();
  initialize_spider_tabs();
  initialize_hot_search('[id=keywords]','/keyword/');
}

var match_timeout = null;
var current_re = null;
var match_counter = 0;

function execute_filter(s,c,a) {
  var empty_match = jQuery(s).val().length == 0;
  var sp = s;
  var cp = c;
  var ap = a;
  match_counter = 0;
  jQuery(c).find('[class*='+a+']').each(function(){
    var id = jQuery(this).attr('id');
    if (empty_match || current_re.test(jQuery(this).text().replace(/ñ/gi,'n'))) {
      jQuery('li[id=line-'+id+']').removeClass('hidden');
    } else {
      jQuery('li[id=line-'+id+']').addClass('hidden');
    }
    jQuery(this).removeClass(a);
  });
  if ( match_counter > 0 ) jQuery(s).removeClass('hotsearch-active');
}

function initialize_filter(s,c,a) {
  jQuery(s).unbind('click').click(function(e){
    e.stopPropagation();
    e.preventDefault();
    return false;
  });
  jQuery(s).unbind('keyup').keyup(function(e){
    if ( typeof match_timeout != 'null' ) {
      clearTimeout(match_timeout);
      match_timeout = null;
    }
    current_re = null;
    current_re = new RegExp(jQuery(this).val(),'gi');
    match_timeout = setTimeout((function() { 
      jQuery(s).addClass('hotsearch-active');
      if ( 0 < jQuery(s).val().length ) {
        jQuery(c).find('[class*=invalidated]').addClass('hidden');
        jQuery(c).find('[class*=matchable]').addClass(a);
        execute_filter(s,c,a); 
      } else {
        jQuery(c).find('[class*=invalidated]').removeClass('hidden');
        jQuery(c).find('[class*=matchable]').removeClass(a).removeClass('hidden').parent().removeClass('hidden');
        jQuery(s).removeClass('hotsearch-active');
      }
    }),700);
  });
}

function initialize_dossier_triggers() {
  enable_proxied_links('human-element-dossier-trigger',{
    beforeSend : (function() {
      jQuery('#doctitle').html("Dossier loader triggered. Please wait.");
      display_wait_notification();
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success : (function(data, httpstatus, jqueryXHR) {
      var referrer = data.referrer;
      var url = data.url;
      var contenttype = data.contenttype ? data.contenttype : '';
      var linkset = data.linkset ? data.linkset : null;
      jQuery('#doctitle').html("Legiscope");

      if ( data && data.hoststats ) set_hoststats(data.hoststats);
      if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
      update_a_age(data);

      if ( /^text\/html/.test(contenttype) ) {
        if ( typeof linkset != 'null' ) {
          replace_contentof('linkset', linkset);
          initialize_linkset_clickevents(jQuery('ul[id=house-bills-by-rep]'),'li');
        }
        if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
        else
        replace_contentof('content', data.content);
        initialize_remote_links(); 
      }
      replace_contentof('currenturl',
        jQuery(document.createElement('a'))
        .attr('href', url)
        .attr('target','blank')
				.attr('id','currenturl-reflexive')
        .html(url)
      );

    })
  });
}

function update_representatives_avatars() {
  jQuery('img[class*=representative-avatar][src=""]').first().each(function(){
    var avatar_id = jQuery(this).attr('id').replace(/^image-/,'imagesrc-');
    if ( jQuery(this).attr('src').length > 0 && !/^data:/.test(jQuery(this).attr('src')) ) {
      setTimeout((function(){update_representatives_avatars();}),100);
      return true;
    }
    var no_replace = jQuery('input[id='+avatar_id+']').hasClass('no-replace');
    var alt_name = no_replace ? (jQuery(this).attr('id')+'-alt') : jQuery(this).attr('id'); 
    var member_uuid = jQuery(this).attr('id').replace(/^image-/,'');
    var avatar_url = jQuery('input[id='+avatar_id+']').val();
    jQuery(this).attr('id', alt_name);
    jQuery.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : avatar_url, modifier : jQuery('#spider').prop('checked'), cache : jQuery('#cache').prop('checked'), member_uuid : member_uuid, no_replace : no_replace, fr : true },
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
        var altmarkup = data.altmarkup ? data.altmarkup : null;
        var total_image_width = 0;
        jQuery('img[id='+alt_name+']').attr('src', altmarkup);
        if ( data && data.hoststats ) set_hoststats(data.hoststats);
        if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
        if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
        update_a_age(data);
        if ( !no_replace ) {
          jQuery("div[class=dossier-strip]").find("img").each(function(){
            total_image_width += (jQuery(this).outerWidth() + 4);
          });
          jQuery("div[class=dossier-strip]").width(total_image_width);
        }
        setTimeout((function(){update_representatives_avatars();}),10);
      })
    });
    return true;
  });
}

function initialize_traversable(c) {
  var selector = c && (0<c.length) ? c : 'div[class*=committee-leader-box]';
  jQuery(selector).find("a[class*=no-autospider]").each(function(){
    jQuery(this).addClass('traverse');
  });
}



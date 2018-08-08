var $ = jQuery;
var Spider = {};
var hotsearch_timer = null;

var preload_container = null;
var preload_a = null;
var preload_n = 0;

function set_hoststats(m) {
  $('ul[id=wp-admin-bar-root-legiscope]').remove();
  $(m).insertAfter(
    $('div[id=wp-toolbar]').find('ul[class*=ab-top-menu]').last()
  );
}

function replace_contentof(a,c) {
  var count_alternates = 0;
  $('div[class*=alternate-'+a+']').each(function(){
    count_alternates++;
  }); 
  var target = count_alternates == 1 ? ('#'+$('div[class*=alternate-'+a+']').attr('id')) : ('#'+a);
  $(target).children().remove();
  $(target).html('');
  $(target).append(c);
}

function update_a_age(data) {
  if ( data && data.age && data.urlhash ) {
    $('a[id='+data.urlhash+']')
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
  $('#search-wait').children().remove();
  $('#search-wait').append($(document.createElement('IMG'))
    .attr('src', 'data:image/gif;base64,R0lGODlhEAAQALMAAP8A/7CxtXBxdX1+gpaXm6OkqMnKzry+womLj7y9womKjwAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQBCgAAACwAAAAAEAAQAAAESBDICUqhmFqbZwjVBhAE9n3hSJbeSa1sm5HUcXQTggC2jeu63q0D3PlwAB3FYMgMBhgmk/J8LqUAgQBQhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAES3DJuUKgmFqb5znVthQF9h1JOJKl96UT27oZSRlGNxHEguM6Hu+X6wh7QN2CRxEIMggExumkKKLSCfU5GCyu0Sm36w3ryF7lpNuJAAAh+QQBCgALACwAAAAAEAAQAAAESHDJuc6hmFqbpzHVtgQB9n3hSJbeSa1sm5GUIHRTUSy2jeu63q0D3PlwCx1lMMgQCBgmk/J8LqULBGJRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYyhmFqbpxDVthwH9n3hSJbeSa1sm5HUMHRTECy2jeu63q0D3PlwCx0FgcgUChgmk/J8LqULAmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYSgmFqb5xjVthgG9n3hSJbeSa1sm5EUgnTTcSy2jeu63q0D3PlwCx2FQMgEAhgmk/J8LqWLQmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJucagmFqbJ0LVtggC9n3hSJbeSa1sm5EUQXSTYSy2jeu63q0D3PlwCx2lUMgcDhgmk/J8LqWLQGBRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuRCimFqbJyHVtgwD9n3hSJbeSa1sm5FUUXSTICy2jeu63q0D3PlwCx0lEMgYDBgmk/J8LqWLw2FRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuQihmFqbZynVtiAI9n3hSJbeSa1sm5FUEHTTMCy2jeu63q0D3PlwCx3lcMgIBBgmk/J8LqULg2FRhV6z2q0VF94iJ9pOBAA7')
    .attr('id', 'busy-notification-wait')
  );
}

function remove_wait_notification() {
  $('#search-wait').children().remove();
}

function preload_worker() {
  if ( typeof preload_a != 'null' && preload_n < preload_a.length ) {

    var hashpair   = preload_a[preload_n++];
    var hash       = hashpair.hash;
    var live       = $('#seek').prop('checked') ? $('#seek').prop('checked') : hashpair.live;
    var linkstring = $('a[id='+hash+']').attr('href');

    if ( $('a[id='+hash+']').hasClass('no-autospider') ) return false;

    $('a[id='+hash+']').addClass('uncached').removeClass('cached').removeClass('traverse');

    if (!linkstring || !(0 < linkstring.length) ) {
      if ( $('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    }
    else
    $.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { 
        url      : linkstring,
        update   : $('#update').prop('checked'),
        proxy    : $('#proxy').prop('checked'),
        debug    : $('#debug').prop('checked'),
        modifier : live,
        fr       : true,
        linktext : $('a[class*=legiscope-remote][id='+hash+']').html()
      },
      cache    : false,
      dataType : 'json',
      async    : true,
      beforeSend : (function() {
        display_wait_notification();
        $('#doctitle').html('Loading '+linkstring);
      }),
      complete : (function(jqueryXHR, textStatus) {
        remove_wait_notification();
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        $('a[id='+hash+']').addClass('cached').removeClass('uncached');
        if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
        else
        if ( data && data.content ) replace_contentof(data && data.rootpage ? 'processed' : 'content',data.content);
        if ( data && data.hoststats ) set_hoststats(data.hoststats);
        if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
        if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        update_a_age(data);
        if ( $('#spider').prop('checked') ) setTimeout((function(){preload_worker();}),100);
      })
    });
  } 
}

function preload_worker_unconditional() {

  var hash       = $("div[id=content]").find('a[class*=legiscope-remote][class*=traverse]').first().attr('id');
  var live       = $('#seek').prop('checked');
  var linkstring = $('a[id='+hash+']').attr('href');

  if ( $('a[id='+hash+']').hasClass('no-autospider') ) return false;

  $('a[id='+hash+']').addClass('uncached').removeClass('traverse');

  if (!linkstring || !(0 < linkstring.length) ) {
    if ( $('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    return false;
  }
  else
  $.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : { 
      url      : linkstring,
      debug    : $('#debug').prop('checked'),
      update   : $('#update').prop('checked'),
      proxy    : $('#proxy').prop('checked'),
      modifier : live,
      fr       : true,
      linktext : $('a[class*=legiscope-remote][id='+hash+']').html()
    },
    cache    : false,
    dataType : 'json',
    async    : true,
    beforeSend : (function() {
      display_wait_notification();
      $('#doctitle').html('Loading '+linkstring);
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (function(data, httpstatus, jqueryXHR) {
      $('a[id='+hash+']').addClass('cached').removeClass('uncached');
      if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
      else
      if ( data && data.content ) replace_contentof(data && data.rootpage ? 'processed' : 'content',data.content);
      if ( data && data.hoststats ) set_hoststats(data.hoststats);
      if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      update_a_age(data);
      if ( $('#spider').prop('checked') ) setTimeout((function(){preload_worker_unconditional();}),100);
    })
  });
  return false;
}

function preload(components) {
  $.ajax({
    type     : 'POST',
    url      : '/preload/',
    data     : { 
      links    : components,
      debug    : $('#debug').prop('checked'),
      update   : $('#update').prop('checked'),
      modifier : $('#seek').prop('checked')
    },
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
      $('#preload').prop('checked') ? preload_worker() : preload_worker_unconditional();
    })
  });
}

function handle_link_marker_click(m) {
  $.ajax({
    type     : 'POST',
    url      : '/link/',
    data     : { url : $('[id=currenturl-reflexive]').html(), link : $(m).attr('id'), cache : $('#cache').prop('checked') },
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
        initialize_linkset_clickevents($('ul[class*=link-cluster]'),'li');
			}
			$(m).prop('checked', state);
			if ( state ) {
				$(m)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.first()
					.prepend($(m).parent().detach());
		  }
			else {
				$(m)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.last()
					.append($(m).parent().detach());
			}
    })
  });
  return false;
}

function initialize_linkset_clickevents(linkset,childtag) {
  var child_tags = typeof childtag == 'undefined' ? 'li' : childtag;
  $(linkset).on('contextmenu',function(){
    return false;
  }).unbind('click').click(function(e){

    if (!$('#spider').prop('checked')) {
      window.alert("Enable 'Spider' option to trigger preload");
      return;
    }
    var components = new Array($(this).children(child_tags).length);
    var component_index = 0;
    $(this).children(child_tags).find('a').each(function(){
      if ( $(this).hasClass('no-autospider') ) return true;
      if ( $(this).hasClass('cached') && !$('#spider').prop('checked') ) return true;
      if ( $(this).hasClass('uncached') ) $(this).addClass('traverse');
      if ( component_index > 300 ) return true;
      var linkset_child = $(this).attr('id');
      components[component_index] = linkset_child; 
      component_index++;
      return true;
    });
    preload(components);

  }).find(child_tags).each(function(){
    $(this).find('a[class*=legiscope-remote]').first().each(function(){
      var link_id = $(this).attr('id');
      if ( $(this).parentsUntil('ul').first().parent().attr('id') == 'systemrootlinks' ) return true;
      if ( link_id.length > 0 && $(this).hasClass('markable') ) {
				var link_marked = $(this).hasClass('is-source-root');
        $(this).parent().first().each(function(){
          $(this).children('input[id=m-'+link_id+']').remove();
          $(this).prepend(
            $(document.createElement('INPUT'))
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
    $(this).on('contextmenu', function(){
      return false;
    }).mouseup(function(e){
      if (2 == parseInt($(e).prop('button'))) {
        $(this).parent().click();
      }
      return false;
    });
  });
  $('input[class*=link-marker]')
    .unbind('click')
    .on('click',function(e){
      e.stopPropagation();
      e.preventDefault();
      return handle_link_marker_click($(this)); 
    })
	  .each(function(){
			if ( $(this).prop('checked') ) {
				$(this)
					.parentsUntil('div[class*=linkset]')
			    .parent()
					.children('ul[class*=link-cluster]')
					.first()
					.prepend($(this).parent().detach());
		  }
		});
}

function std_seek_response_handler(data, httpstatus, jqueryXHR) {

  if (typeof data == 'null') return true;

  var linkset        = data && data.error         ?data.message        : ( data && data.linkset ? data.linkset : {} );
  var referrer       = data && data.referrer      ?data.referrer       : null
  var url            = data && data.url           ?data.url            : null;
  var contenttype    = data && data.contenttype   ?data.contenttype    : '';
  var retainoriginal = data && data.retainoriginal?data.retainoriginal : false;
  var rootpage       = data && data.rootpage      ?data.rootpage          : false;
  var targetframe    = data && data.targetframe   ?data.targetframe       : '[class*=alternate-content]';

  if ( data && data.clicked ) {
     $('a').removeClass('clicked');
     $('a[id='+data.clicked+']').addClass('clicked');
  }

  $('div[class*=contentwindow]').each(function(){
    if ($(this).attr('id') == 'processed') return true;
    if (retainoriginal)
    if ($(this).attr('id') == 'content') return true;
    $(this).children().remove();
    return true;
  });

  if ( data && data.hoststats ) set_hoststats(data.hoststats);
  if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
  if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
  if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
  update_a_age(data);

  if ( /^application\/pdf/.test(contenttype) ) {
    if ( !($('#spider').prop('checked')) ) {
      var target_container = $(retainoriginal ? ('#'+$(targetframe).first().attr('id')) : '#subcontent');
      display_wait_notification();
      PDFJS.getDocument('/fetchpdf/'+data.urlhash).then(function(pdf){
        var np = pdf.numPages;
        for ( var pagecounter = 1 ; pagecounter <= np ; pagecounter++ ) { 
          if ( pagecounter == 1 ) $(target_container).children().remove();
          pdf.getPage(pagecounter).then(function(page){
            var pagecount = $(target_container).children().length;
            var target_name = "pdfdoc-"+pagecount;
            $('#doctitle').html("Target: "+target_name);
            $(target_container).append($(document.createElement('CANVAS'))
              .addClass('inline-pdf')
              .attr('id', target_name)
            );
            var scale = 1.0;
            var viewport = page.getViewport(scale);
            var canvas = document.getElementById(target_name);
            if ( typeof canvas != 'undefined' && canvas != null) {
              var context = canvas.getContext('2d');
              canvas.height = viewport.height;
              canvas.width = $(canvas).parent().first().outerWidth();
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
        initialize_linkset_clickevents($('ul[class*=link-cluster]'),'li');
      }
    }
    $('#siteURL').val(referrer);
    replace_contentof('referrer',$(document.createElement('A'))
      .addClass('legiscope-remote')
      .attr('href', referrer)
      .html('Back')
    );
    replace_contentof('currenturl',
      $(document.createElement('A'))
      .attr('href', data && data.url ? data.url : '')
      .attr('target','blank')
			.attr('id','currenturl-reflexive')
      .append(data && data.url ? data.url : 'No Link')
    );
    initialize_authentication_inputs();
    if ( data && data.defaulttab ) $('#tab_'+data.defaulttab).click();
  }
  initialize_remote_links(); 
  setTimeout(function(){ 
    var seek_enabled    = $('#seek').prop('checked');
    if ( !seek_enabled ) return false;
    var component_count = $("span[class*=search-match-searchlisting]").length || $("div[id=content]").find('a[class*=legiscope-remote]').length;
    var component_index = 0;
    if ( 0 == component_count ) {
      $("div[id=content]").children('a').each(function(){
        component_count++;
        return true;
      });
    }
    $('#doctitle').html("Seek: +"+component_count);
    if ( 0 == component_count ) return false;
    if ( components > 300 ) components = 300;
    var components = new Array(component_count);
    $("div[id=content]").find('a[class*=legiscope-remote]').each(function(){
      if ( $(this).hasClass('no-autospider') ) return true;
      if ( $(this).hasClass('cached') ) return true;
      if ( $(this).hasClass('uncached') ) $(this).addClass('traverse');
      if ( component_index > 300 ) return true;
      var linkset_child = $(this).attr('id');
      components[component_index] = linkset_child; 
      component_index++;
      return true;
    });
    preload(components);
    $('a[class*=pull-in]').first().click(); 
    return true; 
  },1);
  return true;
}

function load_content_window(a,ck,obj,data,handlers) {
  var object_text = typeof obj != 'undefined' ? $(obj).html() : null;
  var std_data = ($('#metalink').html().length > 0)
    ? { url : a, update : $('#update').prop('checked'), modifier : ck || $('#seek').prop('checked'), proxy : $('#proxy').prop('checked'), cache : $('#cache').prop('checked'), debug : $('#debug').prop('checked'), linktext : object_text, metalink : $('#metalink').html() } 
    : { url : a, update : $('#update').prop('checked'), modifier : ck || $('#seek').prop('checked'), proxy : $('#proxy').prop('checked'), cache : $('#cache').prop('checked'), debug : $('#debug').prop('checked'), linktext : object_text }
    ;
  var async = (data && data.async) ? data.async : true;
  if ( typeof data != "undefined" && typeof data != "null" ) {
    for ( var i in data ) {
      var element = data[i];
      $(std_data).prop(i, element);
    }
  }
  $.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : std_data,
    cache    : false,
    dataType : 'json',
    async    : async,
    beforeSend : (handlers && handlers.beforeSend) ? handlers.beforeSend : (function() {
      $('a[class*=legiscope-remote]').unbind('click');
      display_wait_notification();
    }),
    complete : (handlers && handlers.complete) ? handlers.complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (handlers && handlers.success) ? handlers.success : std_seek_response_handler
  });
  return false;
}

function initialize_systemrootlinks() {
  $.ajax({
    type     : 'GET',
    url      : '/system/',
    data     : { fragment : 'systemrootlinks' },
    cache    : false,
    dataType : 'json',
    async    : false,
    beforeSend : (function() {
      display_wait_notification();
      $('#currenturl').html("");
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (function(data, httpstatus, jqueryXHR) {
      if ( data && data.links ) {
        $('#systemrootlinks').empty();
        for ( var index in data.links ) {
          var record = data.links[index];
          var host = record.host;
          var hash = record.hash;
          var link = $(document.createElement('LI'))
            .append($(document.createElement('A'))
              .addClass('legiscope-remote')
              .attr('href','http://'+host)
              .attr('id',hash)
              .text(host)
            );
          $('#systemrootlinks').append(link);
        }
      }
    })
  });
}

function initialize_remote_links() {

  $('a[class*=metapager]').unbind('click').click(function(e){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    var data = $('#jumpto') && $('#jumpto').val() ?  { 'LEGISCOPE' : { coPage : $('#jumpto').val() } } : null;
    $('#metalink').html(content);
    e.stopPropagation();
    load_content_window($(this).attr('href'), $(e).attr('metaKey') || $('#seek').prop('checked'), $(this), data);
    $('#metalink').html('');
    return false;
  });

  $('span[class*=legiscope-refresh]').unbind('mouseout');
  $('span[class*=legiscope-refresh]').mouseout(function(){
    $(this).parent().children('span[class*=legiscope-refresh]').each(function(){
      $(this)
        .addClass('hover')
        .unbind('click')
        .click(function(e){
          e.stopPropagation();
          load_content_window($(this).parent().children('a').attr('href'),'reload',null);
          return false;
        }).mouseout(function(){
          var subject = this;
          setTimeout(function(){$(subject).removeClass('hover');},1000);
          return true;
        });
      return true;
    });
    return true;
  });

  enable_proxied_links('legiscope-remote');

  $('a[class*=fauxpost]').unbind('click').click(function(e){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    $('#metalink').html(content);
    e.stopPropagation();
    load_content_window($(this).attr('href'), $(e).attr('metaKey') || $('#seek').prop('checked'), $(this));
    $('#metalink').html('');
    return false;
  });

  return true;
}

function initialize_spider_tabs() {
  $('#contenttabs').children().remove();
  $('#sitecontent').children().each(function(){
    if ( $(this).attr('id') == 'contenttabs' ) return;
    $('#contenttabs').append(
      $(document.createElement('SPAN'))
        .attr('id','tab_'+$(this).attr('id'))
        .addClass('contenttab')
        .html($(this).attr('id').toUpperCase())
        .unbind('click')
        .click(function(e){
          var target = $(this).attr('id').replace(/^tab_/,'');
          $('#contenttabs').children().removeClass('activetab').removeClass('inactivetab');
          $('#contenttabs').addClass('inactivetab');
          $(this).addClass('activetab').removeClass('inactivetab');
          $('div[class*=contentwindow]').addClass('hidden');
          $('div[id='+target+']').removeClass('hidden');
        })
    );
  }); 
  $('#contenttabs').append(
    $(document.createElement('SPAN'))
      .attr('id','currenturl')
      .addClass('contenttab')
  ).prepend(
    $(document.createElement('SPAN'))
      .attr('id','referrer')
      .addClass('contenttab')
  );
}

function enable_proxied_links(classname,handlers) {
  var handler = typeof handlers == 'undefined' ? {} : handlers; 
  $('a[class*='+classname+']').each(function() {
    $(this)
      .removeClass('proxied')
      .addClass('proxied')
      .unbind('click')
      .click(function(e){
        e.stopPropagation();
        return load_content_window(
          $(this).attr('href'),
          $(e).attr('metaKey'),
          $(this),
          { async : true },
          handler
        );
      });
    return true;
  });
}

function initialize_hot_search(s,url) {
  $(s).keyup(function(e) {
    var self = this;
    if ( typeof hotsearch_timer != 'null' ) {
      clearTimeout(hotsearch_timer);
      hotsearch_timer = null;
    }
    $(this).addClass('hotsearch-active');
    $('#doctitle').html($(this).val());
    hotsearch_timer = setTimeout((function(){
      $(self).removeClass('hotsearch-active');
      clearTimeout(hotsearch_timer);
      hotsearch_timer = null;
      $.ajax({
        type     : 'POST',
        url      : url,
        data     : { fragment : $(s).val(), proxy : $('#proxy').prop('checked') },
        cache    : false,
        dataType : 'json',
        async    : true,
        beforeSend : (function() {
          display_wait_notification();
          $('#currenturl').html("");
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
          $('#currenturl').html((0 < returns) ? ("Matches: "+returns) : "");
          if ( !(0 < returns) ) {
            replace_contentof('subcontent',"<h2>No matches</h2>");
            return;
          }
          replace_contentof('subcontent',$(document.createElement('UL')).attr('id','searchresults'));
          for ( var r in records ) {
            var record = records[r];
            var meta = record.meta ? record.meta : '';
            var referrers = record.referrers ? record.referrers : [];
            var referrerset = document.createElement('UL');
            for ( var m in referrers ) {
              var referrer_url = referrers[m];
              $(referrerset).append($(document.createElement('LI'))
                .append($(document.createElement('A'))
                  .attr('href', referrer_url)
                  .addClass('legiscope-remote')
                  .html(referrer_url)
                )
              );
            }
            $('ul[id=searchresults]').append(
              $(document.createElement('LI'))
                .addClass('search-match')
                .append($(document.createElement('A'))
                  .attr('href', record.url)
                  .attr('id','match-'+record.hash)
                  .addClass('legiscope-remote')
                  .addClass('search-match')
                  .addClass('cached')
                  .html(record.sn)
                )
                .append($(document.createElement('SPAN'))
                  .attr('id','url-'+record.hash)
                  .addClass('search-hit-category')
                  .html('&nbsp;|&nbsp;' + record.category)
                )
                .append($(document.createElement('SPAN'))
                  .attr('id','url-'+record.hash)
                  .addClass('search-hit-url')
                  .append($(document.createElement('A'))
                    .attr('href', record.url)
                    .attr('id','match-link-'+record.hash)
                    .addClass('legiscope-remote')
                    .addClass('search-match-url')
                    .addClass('cached')
                    .html(record.url)
                  )
                )
                .append($(document.createElement('SPAN'))
                  .attr('id','title-'+record.hash)
                  .addClass('search-hit-title')
                  .html(record && record.title ? record.title : '')
                )
                .append($(document.createElement('SPAN'))
                  .attr('id','description-'+record.hash)
                  .addClass('search-hit-desc')
                  .html(record.description)
                )
                .append($(referrerset))
            );
            count++;
            if ( count > 500 ) break;
          }
          if ( data && data.focus_siteurl) $('#siteURL').focus();
          initialize_remote_links();
          $('#tab_processed').click();
        })
      });

    }),1000);
  });
  $(s).focus().select().val('');
}

function initialize_authentication_inputs() {
  $('[class*=authentication-tokens]').unbind('keyup');
  $('[class*=authentication-tokens]').keyup(function(e){
    if ($(e).attr('keyCode') == 13) {
      // Gather all input fields with the given selector class name,
      // and execute a /seek/ action 
      var data = {};
      $('[class*=authentication-tokens]').each(function(){
        if ($(this).attr('id') == 'authentication-target-url') return;
        var name = $(this).attr('name');
        var val  = $(this).val();
        $(data).prop(name, val);
      });
      load_content_window($('[id=authentication-target-url]').val(), 'false', null, data);
    }
    return true;
  });
}  

function initialize_spider() {
  setTimeout(function(){
  $('#siteURL').keydown(function(e){
    if( $(e).attr('keyCode') == 13) {
      load_content_window($(this).val(), $(e).attr('metaKey'), null, null);
      return false;
    }
    return true;
  });
  initialize_systemrootlinks();
  initialize_remote_links();
  initialize_spider_tabs();
  initialize_hot_search('[id=keywords]','/keyword/');
  },1000);
}

var match_timeout = null;
var current_re = null;
var match_counter = 0;

function execute_filter(s,c,a) {
  var empty_match = $(s).val().length == 0;
  var sp = s;
  var cp = c;
  var ap = a;
  match_counter = 0;
  $(c).find('[class*='+a+']').each(function(){
    var id = $(this).attr('id');
    if (empty_match || current_re.test($(this).text().replace(/ñ/gi,'n'))) {
      $('li[id=line-'+id+']').removeClass('hidden');
    } else {
      $('li[id=line-'+id+']').addClass('hidden');
    }
    $(this).removeClass(a);
  });
  if ( match_counter > 0 ) $(s).removeClass('hotsearch-active');
}

function initialize_filter(s,c,a) {
  $(s).unbind('click').click(function(e){
    e.stopPropagation();
    e.preventDefault();
    return false;
  });
  $(s).unbind('keyup').keyup(function(e){
    if ( typeof match_timeout != 'null' ) {
      clearTimeout(match_timeout);
      match_timeout = null;
    }
    current_re = null;
    current_re = new RegExp($(this).val(),'gi');
    match_timeout = setTimeout((function() { 
      $(s).addClass('hotsearch-active');
      if ( 0 < $(s).val().length ) {
        $(c).find('[class*=invalidated]').addClass('hidden');
        $(c).find('[class*=matchable]').addClass(a);
        execute_filter(s,c,a); 
      } else {
        $(c).find('[class*=invalidated]').removeClass('hidden');
        $(c).find('[class*=matchable]').removeClass(a).removeClass('hidden').parent().removeClass('hidden');
        $(s).removeClass('hotsearch-active');
      }
    }),700);
  });
}

function initialize_dossier_triggers() {
  enable_proxied_links('human-element-dossier-trigger',{
    beforeSend : (function() {
      $('#doctitle').html("Dossier loader triggered. Please wait.");
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
      $('#doctitle').html("Legiscope");

      if ( data && data.hoststats ) set_hoststats(data.hoststats);
      if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
      update_a_age(data);

      if ( /^text\/html/.test(contenttype) ) {
        if ( typeof linkset != 'null' ) {
          replace_contentof('linkset', linkset);
          initialize_linkset_clickevents($('ul[id=house-bills-by-rep]'),'li');
        }
        if ( data && data.subcontent ) replace_contentof('subcontent', data.subcontent);
        else
        replace_contentof('content', data.content);
        initialize_remote_links(); 
      }
      replace_contentof('currenturl',
        $(document.createElement('a'))
        .attr('href', url)
        .attr('target','blank')
				.attr('id','currenturl-reflexive')
        .html(url)
      );

    })
  });
}

function update_representatives_avatars() {
  $('img[class*=representative-avatar][src=""]').first().each(function(){
    var avatar_id = $(this).attr('id').replace(/^image-/,'imagesrc-');
    if ( $(this).attr('src').length > 0 && !/^data:/.test($(this).attr('src')) ) {
      setTimeout((function(){update_representatives_avatars();}),100);
      return true;
    }
    var no_replace = $('input[id='+avatar_id+']').hasClass('no-replace');
    var alt_name = no_replace ? ($(this).attr('id')+'-alt') : $(this).attr('id'); 
    var member_uuid = $(this).attr('id').replace(/^image-/,'');
    var avatar_url = $('input[id='+avatar_id+']').val();
    $(this).attr('id', alt_name);
    $.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { 
        url         : avatar_url,
        debug       : $('#debug').prop('checked'),
        modifier    : $('#spider').prop('checked'),
        cache       : $('#cache').prop('checked'),
        member_uuid : member_uuid,
        no_replace  : no_replace,
        fr          : true
      },
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
        $('img[id='+alt_name+']').attr('src', altmarkup);
        if ( data && data.hoststats ) set_hoststats(data.hoststats);
        if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
        if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        if ( data && data.systemrootlinks ) replace_contentof('systemrootlinks',data.systemrootlinks);
        update_a_age(data);
        if ( !no_replace ) {
          $("div[class=dossier-strip]").find("img").each(function(){
            total_image_width += ($(this).outerWidth() + 4);
          });
          $("div[class=dossier-strip]").width(total_image_width);
        }
        setTimeout((function(){update_representatives_avatars();}),10);
      })
    });
    return true;
  });
}

function initialize_traversable(c) {
  var selector = c && (0<c.length) ? c : 'div[class*=committee-leader-box]';
  $(selector).find("a[class*=no-autospider]").each(function(){
    $(this).addClass('traverse');
  });
}



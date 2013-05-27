var Spider = {};
var hotsearch_timer = null;

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

var preload_a = null;
var preload_n = 0;

function preload_worker() {
  if ( typeof preload_a != 'null' && preload_n < preload_a.length ) {
    var hashpair = preload_a[preload_n++];
    var hash = hashpair.hash;
    var live = $('#seek').prop('checked') ? $('#seek').prop('checked') : hashpair.live;
    var linkstring = $('a[id='+hash+']').attr('href');

    $('a[id='+hash+']').addClass('uncached').removeClass('cached');

    $.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : linkstring, proxy : $('#proxy').prop('checked'), modifier : live, fr: true, linktext: $('a[class*=legiscope-remote][id='+hash+']').html() },
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
        if ( data && data.original ) replace_contentof('original',data.original);
        preload_worker();
      })
    });
  } 
}
function preload(components) {
  $.ajax({
    type     : 'POST',
    url      : '/preload/',
    data     : { links: components, modifier : $('#seek').prop('checked') },
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
  $(linkset).on('contextmenu',function(){
    return false;
  }).click(function(e){

		var self = $(this);

    e.stopPropagation();
    e.preventDefault();

    if ( !$(this).prop || !$(this).prop('coordinates') ) return;

    if ( $(this).hasClass('upper-section') || $(this).hasClass('lower-section') ) {
      var coordinates = $(this).prop('coordinates');
      $.ajax({
        type     : 'POST',
        url      : '/reorder/',
        data     : { clusterid : $(this).attr('id'), proxy : $('#proxy').prop('checked'), move : $(this).hasClass('upper-section') ? -1 : 1 },
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

          if ( linkset && linkset.length > 0 ) {
            replace_contentof('linkset', linkset);
            initialize_linkset_clickevents(self,child_tags);
            setTimeout(function(){ initialize_remote_links(); },1);
          }
        })
      });

      return;
    }

    var components = new Array($(this).children(child_tags).length);
    var component_index = 0;
    $(this).children(child_tags).find('a').each(function(){
      if ( component_index > 300 ) return;
      var linkset_child = $(this).attr('id');
      if ( $(this).hasClass('cached') ) return;
      components[component_index] = linkset_child; 
      component_index++;
    });
    preload(components);

  }).find(child_tags).each(function(){
    $(this).on('contextmenu', function(){
      return false;
    }).mouseup(function(e){
      if (2 == parseInt($(e).prop('button'))) {
        $(this).parent().click();
      }
      return false;
    });
  });
}

function std_seek_response_handler(data, httpstatus, jqueryXHR) {
	var response = data.error ? data.message : data.linkset;
	var markup = data.markup;
	var responseheader = data.responseheader;
	var referrer = data.referrer;
	var url = data.url;
	var contenttype = data.contenttype ? data.contenttype : '';
	var retainoriginal = data.retainoriginal ? data.retainoriginal : false;

	$('div[class*=contentwindow]').each(function(){
		if ($(this).attr('id') == 'issues') return;
		if (retainoriginal)
		if ($(this).attr('id') == 'original') return;
		$(this).children().remove();
	});

	if ( /^application\/pdf/.test(contenttype) ) {
    if ( !($('#spider').prop('checked')) ) {
      var target_container = $(retainoriginal ? ('#'+$('[class*=alternate-original]').first().attr('id')) : '#original');
      PDFJS.getDocument('/fetchpdf/'+data.contenthash).then(function(pdf){
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
          });
        }
      });
    }
	} else {
		replace_contentof('original',data.original);
		if ( /^text\/html/.test(contenttype) ) {
			if ( response && response.length > 0 ) {
				replace_contentof('linkset', response);
				initialize_linkset_clickevents($('ul[class*=link-cluster]'),'li');
			}
		}
		$('#siteURL').val(referrer);
		replace_contentof('markup',markup);
		replace_contentof('responseheader',responseheader);
		replace_contentof('referrer',$(document.createElement('A'))
			.addClass('legiscope-remote')
			.attr('href', referrer)
			.html('Back')
		);
		replace_contentof('currenturl',
			$(document.createElement('A'))
			.attr('href', data.url)
			.attr('target','blank')
			.append(data.url)
		);
		initialize_authentication_inputs();
		$('#tab_'+data.defaulttab).click();
	}
	setTimeout(function(){ 
		initialize_remote_links(); 
		var seek_enabled    = $('#seek').prop('checked');
		if ( !seek_enabled ) return;
		var component_count = $("span[class*=search-match-searchlisting]").length || $("div[id=original]").find('a[class*=legiscope-remote]').length;
		var component_index = 0;
		if ( 0 == component_count ) {
			$("div[id=original]").children('a').each(function(){
				component_count++;
			});
		}
		$('#doctitle').html("Seek: +"+component_count);
		if ( 0 == component_count ) return;
		if ( components > 300 ) components = 300;
		var components = new Array(component_count);
		$("div[id=original]").find('a[class*=legiscope-remote]').each(function(){
			if ( component_index > 300 ) return;
			var linkset_child = $(this).attr('id');
			if ( $(this).hasClass('cached') ) return;
			components[component_index] = linkset_child; 
			component_index++;
		});
		preload(components);
		$('a[class*=pull-in]').first().click(); 
		return true; 
	},1);
}

function load_content_window(a,ck,obj,data,handlers) {
  var object_text = typeof obj != 'undefined' ? $(obj).html() : null;
  var std_data = ($('#metalink').html().length > 0)
    ? { url : a, modifier : ck || $('#seek').prop('checked'), proxy : $('#proxy').prop('checked'), cache : $('#cache').prop('checked'), linktext : object_text, metalink : $('#metalink').html() } 
    : { url : a, modifier : ck || $('#seek').prop('checked'), proxy : $('#proxy').prop('checked'), cache : $('#cache').prop('checked'), linktext : object_text }
    ;
  var async = (data && data.async) ? data.async : false;
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
      /* $('#siteURL').val(a); */
      display_wait_notification();
    }),
    complete : (handlers && handlers.complete) ? handlers.complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : (handlers && handlers.success) ? handlers.success : std_seek_response_handler
  });
}

function initialize_remote_links() {

  $('a[class*=metapager]').unbind('click');

  $('a[class*=metapager]').click(function(e){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    var data = $('#jumpto') && $('#jumpto').val() ?  { 'LEGISCOPE' : { coPage : $('#jumpto').val() } } : null;
    $('#metalink').html(content);
    e.stopPropagation();
    load_content_window($(this).attr('href'), $(e).attr('metaKey') || $('#seek').prop('checked'), $(this), data);
    $('#metalink').html('');
    return false;
  });


  $('a[class*=fauxpost]').unbind('click');

  $('a[class*=fauxpost]').click(function(e){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    $('#metalink').html(content);
    e.stopPropagation();
    load_content_window($(this).attr('href'), $(e).attr('metaKey') || $('#seek').prop('checked'), $(this));
    $('#metalink').html('');
    return false;
  });

  enable_proxied_links('legiscope-remote');

  $('span[class*=legiscope-refresh]').unbind('mouseout');
  $('span[class*=legiscope-refresh]').mouseout(function(){
    $(this).parent().children('span[class*=legiscope-refresh]').each(function(){
      $(this)
        .unbind('click')
        .addClass('hover')
        .click(function(e){
          e.stopPropagation();
          load_content_window($(this).parent().children('a').attr('href'),'reload',null);
        }).mouseout(function(){
          var subject = this;
          setTimeout(function(){$(subject).removeClass('hover');},1000);
        });
    });
  });

  $('[class*=link-cluster]')
    .unbind('mouseenter')
    .unbind('mouseleave')
    .unbind('mousemove');

  $('[class*=link-cluster]').mouseenter(function(e){
    $(this).prop('linkset-location', $(this).offset());
  }).mouseleave(function(e){
    $(this).removeClass('upper-section').removeClass('lower-section');
    $('#doctitle').html('Legiscope');
  }).mousemove(function(e){
    if ( !$(this).prop || !$(this).prop('linkset-location') ) return;
    var y = ($(this).innerHeight() / 2) - (e.pageY - $(this).prop('linkset-location').top);
    var x = (e.pageX - $(this).prop('linkset-location').left) - ($(this).innerWidth() / 2);
    $(this).removeClass('upper-section').removeClass('lower-section');
    $(this).prop('coordinates',{ x : x, y : y });
    if ( x > 0 ) return;
    $(this).addClass( y >= 0 ? 'upper-section' : 'lower-section' );
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
  $('a[class*='+classname+']')
    .unbind('click');

  $('a[class*='+classname+']').click(function(e){
    e.stopPropagation();
    load_content_window(
			$(this).attr('href'),
		 	$(e).attr('metaKey'),
		 	$(this),
		 	{ async : true },
		 	handler
		);
    return false;
  });
}

function initialize_hot_search() {
  $('#keywords').keyup(function(e) {
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
        url      : '/keyword/',
        data     : { fragment : $('#keywords').val(), proxy : $('#proxy').prop('checked') },
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
          $('#currenturl').html("Matches: "+returns);
          $('div[class*=contentwindow]').each(function(){
            if ($(this).attr('id') == 'issues') return;
            if (retainoriginal)
            if ($(this).attr('id') == 'original') return;
            $(this).children().remove();
          });
          if ( !(0 < returns) ) return;
          replace_contentof('original',$(document.createElement('UL')).attr('id','searchresults'));
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
                  .attr('id','description-'+record.hash)
                  .addClass('search-hit-desc')
                  .html(record.description)
                )
                .append($(referrerset))
            );
            count++;
            if ( count > 100 ) break;
          }
          $('ul[id=searchresults]').append(
            $(document.createElement('LI'))
              .append($(document.createElement('SPAN'))
                .html('Total matches: '+(data.count ? data.count : count))
              )
          );
          $('#siteURL').focus();
          initialize_remote_links();
          $('#tab_original').click();
        })
      });

    }),1000);
  });
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
  });
}  

function initialize_spider() {
  $('#siteURL').keydown(function(e){
    if( $(e).attr('keyCode') == 13) {
      load_content_window($(this).val(), $(e).attr('metaKey'), null, null);
    }
  });
  initialize_remote_links();
  initialize_spider_tabs();
  $('#keywords').focus().select();
  initialize_hot_search();
}

$ = jQuery;
var timer_id = 0;

function highlight_toc_entry(id) {
  $('#toc').find('A').css({ 'background-color' : 'transparent' });
  $('#toc').find('#link-'+id).css({ 'background-color' : '#DDD' });
}

function defer_toc_highlight(toc,interval) {
  clearTimeout(timer_id);
  timer_id = 0;
  var matched = 0;
  timer_id = setTimeout(function() {
    var toc = $('#toc').data('toc');
    var scroll_y = $(window).scrollTop().toFixed(0);
    if ( typeof toc === 'object' && toc.length > 0 )
    toc.forEach(function(toc_entry, index) {
      if ( matched > 0 ) return;
      if ( scroll_y < Number.parseInt(toc_entry.offset) ) {
        var entry = $('#toc').data('toc');
        toc_entry = toc[Number.parseInt($('#toc').data('prior'))];
        document.title = $('#link-'+toc_entry.id).text();
        // Set TOC trigger edge as left table edge
        try {
          $('#toc').data('floatedge',Number.parseInt($('#'+toc_entry.id).offset().left));
          matched = 1;
          highlight_toc_entry(toc_entry.id);
        }
        catch(e) {
        }
        clearTimeout(timer_id);
        timer_id = 0;
      }
      else {
        $('#toc').data('prior',index);
      }
    });
  },interval);
}

function scroll_to_anchor(event,context,prefix){
  var self = context;
  var local_parser = document.createElement('A');
  var anchor_id;

  local_parser.href = $(self).attr('href');
  anchor_id = local_parser.hash.replace(/#/,prefix);
  
  document.title = $(self).text();
  $('html, body').animate({
    scrollTop: $('#'+anchor_id).offset().top.toFixed(0)
  });
  document.location = $('#'+anchor_id).attr('href');
}

$(document).ready(function() {

  // Copyright 2018, Antonio Victor Andrada Hilario
  // avahilario@gmail.com
  // I am releasing this to the public domain.
  // 6 July 2018
  // #StopTheKillings

  var preamble_y = 0;
  var preamble_offset = 0;
  var tocdiv = document.createElement('DIV');
  var toc = new Array();
  var parser = document.createElement('A');

  parser.href = document.location;
  
  // Generate empty TOC div
  $(tocdiv)
    .attr('id','toc')
    .css({
      'width'            : '180px',
      'max-height'       : ($(window).innerHeight()-40)+'px',
      'background-color' : '#FFF',
      'padding'          : '5px 0 5px 0',
      'margin-left'      : '5px',
      'overflow'         : 'scroll',
      'overflow-x'       : 'hidden',
      'display'          : 'block',
      'position'         : 'absolute',
      'top'              : $(window).scrollTop()+'px',
      'left'             : '-10px',
      'border'           : 'solid 3px #DDD'
    })
    .text("");

  // Add TOC div to WordPress content DIV
  $('div.site-inner').append(tocdiv);

  $('#toc').data({'prior' : 0, 'floatedge' : 0, 'timer_fade' : 0, 'table_count' : 0});

  // Add anchors to each article header

  // Iterate through each H1 Article header
  $("div.entry-content").find('H1').each(function(index){
    var article_text = $(this).text();
    var slug = article_text.toLowerCase().replace(/\n/,' ').replace(/[^a-z ]/g,' ').replace(/[ ]{1,}/,' ').replace(/[ ]*$/,'').replace(/[ ]{1,}/g,'-');
    var link = document.createElement('A');
    var anchor = document.createElement('A');
    // Set link color if the article includes "draft" or "new" 
    var link_color = /(draft|new)/i.test(article_text) 
      ? { 'color' : '#F01', 'font-style' : 'italic' } 
      : /(available formats)/i.test(article_text)
        ? { 'color' : '#AAA', 'font-style' : 'italic' }
        : { 'color' : 'blue' };
    // Prepare TOC link
    $(link).attr('id','link-'+slug)
      .addClass('toc-link')
      .css({
        'white-space'  : 'nowrap',
        'display'      : 'block',
        'float'        : 'left',
        'padding-left' : '5px',
        'width'        : '100%',
        'clear'        : 'both'
      })
      .css(link_color)
      .attr('href',parser.pathname+'#'+slug)
      .text(article_text.replace(/Article ([a-z]{1,})/gi,''))
      .click(function(event){scroll_to_anchor(event,link,'a-');})
      ;
    // Record TOC entry for use in animating TOC highlight updates.
    toc[toc.length] = { 
      offset : $(this).offset().top.toFixed(0),
      id     : slug
    };
    // Attach the TOC metadata to the TOC, sure.
    $('#toc').data('toc', toc);
    // Derive anchor target
    $(anchor).attr('name',slug)
      .attr('id','a-'+slug)
      .css({
        'text-decoration' : 'none',
        'color'           : 'black',
        'padding-top'     : '10px',
        'box-shadow'      : '0 0 0 0' 
      })
      .attr('href',parser.pathname+'#'+slug)
      .append('&nbsp;')
      .addClass('toc-anchor');
    // Add ID attribute to this H1 tag, replace text, and add ID to table body. 
    // Also apply formatting.
    $(this)
      .before(anchor)
      .attr('id','h-'+slug)
      .css({
        'text-align' : 'center'
      })
      ;
    $('#h-'+slug+' ~ table').first()
      .attr('id',slug)
      // At this point, we can alter the "Section X" text inside tables (the one with id {slug}),
      // and turn those string fragments into HTML anchors.
      .find('TD').each(function(tindex){
        $(this).click(function(event){
          $('#toc').show();
        });
        $(this).find('STRONG').each(function(sindex){
          var strong = $(this);
          var section_text = $(strong).text();
          // Ignore instances of "See ..."
          if ( !(/^see /gi.test(section_text) ) && /^section ([0-9]{1,})/i.test(section_text) ) {
            var section_num = section_text.replace(/^section ([0-9]{1,}).*/i,"$1");
            var section_anchor = $(document.createElement('A'))
              .data({
                'section_num' : section_num,
                'slug'        : slug,
                'path'        : parser.pathname
              })
              .css({
                'text-decoration' : 'none',
                'color'           : 'black',
                'padding-top'     : '10px',
                'box-shadow'      : '0 0 0 0' 
              })
              .addClass('toc-section')
              .text('SECTION '+section_num+'.');
            $(strong).empty().append(section_anchor);
          }
        });
      });

    // Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
    // Replace references to articles ("in Article X...") with links to local anchors.
    // Append Article link (and an explicit line break) to the TOC container 
    $(tocdiv).append(link);
    $(tocdiv).append(document.createElement('BR'));
  });


  // This placeholder image serves no function
  $('div.post-thumbnail').first().find('img.wp-post-image').remove();

  $('#toc').css({'top': $(window).scrollTop()+'px'});

  // Since the site will only be serving the Constitution for a while,
  // best include the privacy policy link in the link box.
  var privacy_policy = $(document.createElement('EM')).css({'margin-top': '10px'}).append($(document.createElement('A'))
    .css({'color' : '#AAA'})
    .attr('href','/privacy-policy/')
    .attr('target','_target')
    .text('Privacy Policy'))
    ;
  $('#toc').append(privacy_policy);

  setTimeout(function(){
    // Fix up references to existing sections: Add event handler for a click on links that lead to local anchors.
    // Note:  This is potentially an O(n^n) operation.  
    //   Every section cell can refer to every other section's anchor.  
    //   If every section refers to every other, no self-referencing, that's a O(n(n-1)*n) = O(n^3-n^2).
    //   A lower bound for search is O(ni^2), when every cell refers to just one other cell.
    //   Linear time search has glb O(n^2) (a few cells refer to at most one other cell).
    //   So:  Do this in a timed event.
    
    jQuery.each($('div.site-inner').find('table').children(),function(index,table){
      // Table context
      var table_count = $('#toc').data('table_count');
      if ( table_count > 0 ) { 
        $(table).find('tr').each(function(){
          // TR context
          var tr = this;
          jQuery.each($(tr).children(), function(td_index,td){
            // TD context
            $(td).data('index',td_index);
            // Locate and modify toc-section anchors
            $(td).find('[class*=toc-section]').each(function(){
              var slug = $(this).data('slug');
              var section_num = $(this).data('section_num');
              var path = $(this).data('path');
              $(this)
                .attr('name',slug+'-'+section_num+'-'+td_index)
                .attr('id','a-'+slug+'-'+section_num+'-'+td_index)
                .attr('href',path+'#'+slug+'-'+section_num+'-'+td_index)
                ;
            });
            if ( Number.parseInt($(td).attr('colspan')) == 3 ) {
              $(td).attr('colspan','2');
            }
            else if ( td_index == 1 ) {
              // Increase reading space by collapsing middle columns
              $(td).hide();
            }

            // Separately: If this cell contains any A tags linking to any other cell in this document,
            // we add a click handler that causes the browser to scroll that target into view.
            jQuery.each($(td).find('A'),function(a_index,anchor){
              if ( $(anchor).hasClass('toc-section') ) {
                // Do nothing
              }
              else if ( $(anchor).hasClass('toc-anchor') ) {
                // Do nothing
              }
              else {
                $(anchor).click(function(event){
                  try {
                    scroll_to_anchor(event,this,'a-');
                  } catch(e) {
                  }
                  event.preventDefault();
                });
              }
            });
          });
        });
      }
      table_count++;
      $('#toc').data('table_count',table_count);
    });

    // Reset font size by removing all font-size style specifiers
    var custom_css = $('head').find('style#wp-custom-css').text().replace(/font-size: ([^;]{1,});/i,'');
    $('head').find('style#wp-custom-css').text(custom_css);
    // If the parser was given an existing anchor, go to it, after this initialization is done..
    $('#link-'+parser.hash.replace(/^#/,'')).click();
  },100);

  // Attach handler that triggers reappearance of TOC on mouse movement
  $(window).mousemove(function(event){
    var offsetedge = Number.parseInt(event.pageX);
    var triggeredge = Number.parseInt($('#toc').data('floatedge'));
    clearTimeout($('#toc').data('timer_fade'));
    // FIXME:  You're repeating code here, from the scroll() event handler 
    if ( offsetedge + 10 < triggeredge ) {
      $('#toc').show().css({
        'top'        : $(window).scrollTop().toFixed(0)+'px',
        'max-height' : ($(window).innerHeight()-40)+'px'
      });
    }
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(1000);
    },3000));
  });

  $('#toc').data('timer_fade',setTimeout(function(){
    $('#toc').fadeOut(1000);
  },3000));

  $(window).scroll(function(event){
    clearTimeout($('#toc').data('timer_fade'));
    var offsetedge = Number.parseInt(event.pageX);
    var triggeredge = Number.parseInt($('#toc').data('floatedge'));
    if ( offsetedge + 10 < triggeredge ) {
      $('#toc').show();
    }
    $('#toc').css({
      'top'        : $(window).scrollTop().toFixed(0)+'px',
      'max-height' : ($(window).innerHeight()-40)+'px'
    });
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(1000);
    },3000));
    defer_toc_highlight(toc,200);
  });

});

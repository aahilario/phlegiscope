$ = jQuery;
var timer_id = 0;
var enable_copy = 0;
var intrasection_links = 0;

function highlight_toc_entry(id) {
  $('#toc').find('A').css({ 'background-color' : 'transparent' });
  $('#toc').find('#link-'+id).css({ 'background-color' : '#DDD' });
}

function defer_toc_highlight(interval) {
  var matched = 0;
  clearTimeout(timer_id);
  timer_id = 0;
  timer_id = setTimeout(function() {
    var toc = $('#toc').data('toc');
    var window_halfheight = Number.parseInt(Number.parseInt($(window).innerHeight().toFixed(0)) / 2);
    var scroll_y = Number.parseInt($(window).scrollTop().toFixed(0)) + window_halfheight;
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
  var anchor_id;
  var parent_td;

  event.preventDefault();

  anchor_id = $(self).attr('href').replace(/#/,'').replace(/^\/(constitutions\/)?/,prefix);

  document.title = $(self).text();
  $('#'+anchor_id).parents('TD').first().each(function(){
    var self = this;
    var offset_top = $(self).offset().top.toFixed(0);
    var parent_offset_top = $(self).parents('TR').offset().top.toFixed(0);
    
    if (parent_offset_top === undefined) {
      $('html, body').animate({
        scrollTop: $(self).offset().top.toFixed(0)
      });
    }
    else {
      $(self).parents('TR').first().each(function(){
        var self = this;
        // FIXME: Implement YFE 
        $('html, body').animate({
          scrollTop: ($(self).offset().top - 20).toFixed(0),
          backgroundColor: '#FFFFFF'
        });
      });
    }

  });
  if ($('#'+anchor_id).parents('TR').length === 0) {
    $('html, body').animate({
      scrollTop: $('#'+anchor_id).offset().top.toFixed(0)
    });
  }
  document.location = '#'+anchor_id;
}

function replace_section_x_text(context) {
}

function set_section_cell_handler(column_index,slug,context) {
  // Experimental copy to clipboard
  var self = $(context);
  var toc = $('#toc').data('toc');
  var toc_index = $('#toc').data('toc_current');
  var parser = document.createElement('A'); 
  parser.href = document.location;

  if ( undefined === toc[toc_index].section )
    toc[toc_index].section = []; 
  if ( undefined === toc[toc_index].section[column_index] )
    toc[toc_index].section[column_index] = 0; 

  if ( undefined === toc[toc_index].subsection ) 
    toc[toc_index].subsection = [];
  if ( undefined === toc[toc_index].subsection[column_index] ) 
    toc[toc_index].subsection[column_index] = 0;

  $(context).click(function(event){
    $('#toc').show();
    if ( enable_copy > 0 ) {//{{{
      var self = this;
      var textarea = document.createElement('TEXTAREA');
      var innertext = {};

      try {
        innertext = $(self).data('innertext');
      }
      catch (e) {}
      if ( undefined === innertext ) innertext = {}; 
      if ( innertext.length > 0 ) {
        $(self).empty().append(innertext);
        $(self).data('innertext',{});
      }
      else {
        $(self).data('innertext',$(self).text());
        $(textarea).text($(self).text())
          .css({
            'padding'          : 0,
            'margin'           : 0,
            'display'          : 'block',
            'height'           : ($(self).innerHeight()-4)+'px',
            'width'            : ($(self).innerWidth()-2)+'px',
            'clear'            : 'both',
            'background-color' : 'transparent',
            'font-size'        : 'inherit',
            'color'            : 'black !important',
            'scroll'           : 'none',
            'overflow'         : 'auto',
            'resize'           : 'none',
            'border'           : '0px solid',
          });
        $(self).empty().append(textarea);
        $(self).children().first().focus().select();
        document.execCommand("copy");
        setTimeout(function(){
          $(self).click();
        },50);
      }
    }//}}}
  });
  // Replace leading subsection string with anchor.
  var is_subsection = /^\(?([0-9a-z]{1,})\) /i.test($(self).text());

  if ( is_subsection ) {

    var anchor_text = $(self).text();
    var section_num = toc[toc_index].section[column_index];
    var subsection_num = anchor_text.replace(/^(\()?([0-9a-z]{1,})\) .*/i,"$2");
    var anchor_data = {
      'section_num'    : section_num,
      'subsection_num' : toc[toc_index].subsection[column_index] + 1,
      'slug'           : slug,
      'path'           : parser.pathname
    };
    var section_anchor = $(document.createElement('A'));

    $(section_anchor)
      .data(anchor_data)
      .css({
        'text-decoration' : 'none',
        'color'           : 'blue',
        'padding-top'     : '10px',
        'box-shadow'      : '0 0 0 0' 
      })
      .addClass('toc-section')
      .addClass('toc-subsection')
      .text('('+subsection_num+') ')
      .click(function(event){
        var self = this;
        scroll_to_anchor(event,$('#'+$(self).attr('id').replace(/link-/,'a-')),'a-');
      });
    $(self).empty()
      .append(section_anchor)
      .append(anchor_text.replace(/^(\()?([0-9a-z]{1,})\) /,''));

    toc[toc_index].subsection[column_index]++;
  } 

  $('#toc').data('toc', toc);
  // Replace Section highlight prefix ("SECTION XXX...") with anchor.
  
  $(self).find('STRONG').each(function(sindex){
    var anchor_container = $(this);
    var anchor_text = $(anchor_container).text();
    var toc = $('#toc').data('toc');
    var toc_current = $('#toc').data('toc_current');

    // Ignore instances of "See ..."
    if ( !(/^see /gi.test(anchor_text) ) ) {
      if ( /^section ([0-9]{1,})/i.test(anchor_text) ) {
        var section_num = anchor_text.replace(/^section ([0-9]{1,}).*/i,"$1");
        var anchor_data = {
          'section_num' : section_num,
          'slug'        : slug,
          'path'        : parser.pathname
        };
        var section_anchor = $(document.createElement('A'))
          .data(anchor_data)
          .css({
            'text-decoration' : 'none',
            'color'           : 'blue',
            'padding-top'     : '10px',
            'box-shadow'      : '0 0 0 0' 
          })
          .addClass('toc-section')
          .text('SECTION '+section_num+'.')
          .click(function(event){
            scroll_to_anchor(event,this,'a-');
            event.preventDefault();
          });
        $(anchor_container).empty().append(section_anchor);
        toc[toc_current].section[column_index] = section_num;
        toc[toc_current].subsection[column_index] = 0;
        $('#toc').data('toc', toc);
      }
    }
  });

  parser = null;
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
      'max-height'       : ($(window).innerHeight()-90)+'px',
      'background-color' : '#FFF',
      'padding'          : '5px 0 5px 0',
      'margin-left'      : '5px',
      'overflow'         : 'scroll',
      'overflow-x'       : 'hidden',
      'display'          : 'block',
      'float'            : 'right',
      'clear'            : 'none',
      'position'         : 'fixed',
      'z-index'          : '10',
      'top'              : '50px',
      'left'             : '10px',
      'border'           : 'solid 3px #DDD'
    })
    .text("");

  // Add TOC div to WordPress content DIV
  $('#page').append(tocdiv);

  $('#toc').data({'prior' : 0, 'floatedge' : 0, 'timer_fade' : 0, 'table_count' : 0});

  // Add anchors to each article header

  // Iterate through each H1 Article header
  $("div.entry-content").find('H1').each(function(article_index){

    // The variables 
    //     article_index, 
    //     toc_index, 
    //     column_index, and 
    //     toc.section[column_index],
    //     toc.subsection[column_index] are
    //   used to generate anchor links.
    var article_text = $(this).text();
    var slug = article_text.toLowerCase().replace(/\n/,' ').replace(/[^a-z ]/g,' ').replace(/[ ]{1,}/,' ').replace(/[ ]*$/,'').replace(/[ ]{1,}/g,'-');
    var link = document.createElement('A');
    var anchor = document.createElement('A');
    var toc_index = toc.length;

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
        
      .text(article_text.replace(/Article ([a-z]{1,})/gi,''))
      .click(function(event){
        var self = this;
        var targete = '#'+$(self).attr('id').replace(/link-/,'a-');
        scroll_to_anchor(event,$(targete),'a-');
      })
      ;
    // Record TOC entry for use in animating TOC highlight updates.
    toc[toc_index] = { 
      article    : article_index,
      section    : {},
      subsection : {},  // Counters; reset at each section
      offset     : $(this).offset().top.toFixed(0),
      id         : slug
    };
    // Attach the TOC metadata to the TOC, sure.
    $('#toc').data('toc', toc);
    $('#toc').data('toc_current', toc_index);

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

    // Get the first table following an h-<slug> H1
    $('#h-'+slug+' ~ table').first()
      .attr('id',slug)
      // At this point, we can alter the "Section X" text inside tables (the one with id {slug}),
      // and turn those string fragments into HTML anchors.

      // First, highlight weasel words
      // Replacing HTML damages DOM attributes
      .find('TR').each(function(row_index){
        jQuery.each($(this).find('TD'),function(column_index,td){
          var ww = $(td).html()
            .replace(/((provided )?(for )?by law)/i, '<span style="color: red; font-weight: bold">$1</span>')
            .replace(/^SECTION ([0-9]{1,})./i, '<strong>SECTION $1.</strong>')
            ;
          $(td).html(ww);
          set_section_cell_handler(column_index,slug,$(td));
        });
      });

    // TODO: Replace references to articles ("in Article X...") with links to local anchors.

    // Append Article link (and an explicit line break) to the TOC container 
    $(tocdiv).append(link);
    $(tocdiv).append(document.createElement('BR'));
  });


  // This placeholder image serves no function
  $('div.post-thumbnail').first().find('img.wp-post-image').remove();

  // Since the site will only be serving the Constitution for a while,
  // best include the privacy policy link in the link box.
  var aux_link = {
    'white-space'  : 'nowrap',
    'display'      : 'block',
    'float'        : 'left',
    'padding-left' : '5px',
    'width'        : '100%',
    'clear'        : 'both',
    'color'        : '#AAA'
  }
  var privacy_policy = $(document.createElement('EM')).css({'margin-top': '10px'}).append($(document.createElement('A'))
    .css(aux_link)
    .attr('href','/privacy-policy/')
    .attr('target','_target')
    .text('Privacy Policy'))
    ;
  var representative_map = $(document.createElement('EM')).css({'margin-top': '10px'}).append($(document.createElement('A'))
    .css(aux_link)
    .attr('href','/representatives-by-map/')
    .attr('target','_target')
    .text('Representatives'))
    ;

  $('#toc').append(privacy_policy);
  $('#toc').append(document.createElement('BR'));
  $('#toc').append(representative_map);

  jQuery.each($('div.site-inner').find('table'),function(index,table){
    // Table context
    var tabledef = { 
      n : index,
      title : null,
      slug : null,
      sections : new Array()
    }; 
    var table_count = $('#toc').data('table_count');
    var slug = $(table).attr('id');
    if ( slug === undefined ) return;
    tabledef.slug = slug;
    tabledef.title = $('#h-'+slug).text();
    jQuery.each($(table).find('TR'), function(tr_index, tr) {
      // TR context
      jQuery.each($(tr).children(), function(td_index,td){
        // TD context
        // 0. Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
        // 1. Locate and modify toc-section anchors
        // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
        // 3. Apply column span mod and collapse middle columns

        if ( undefined === tabledef.sections[td_index] )
          tabledef.sections[td_index] = {
            current_ident : null,
            current_slug : null,
            current_section : null,
            subsection_num : null,
            contents : new Array()
          };

        // Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
        $(td).data('index',td_index);

        // 1. Locate and modify toc-section anchors; fix 
        $(td).find('[class*=toc-section]').each(function(){
          var slug = $(this).data('slug');
          var subsection_num = $(this).data('subsection_num');
          var section_num = (undefined === subsection_num) 
            ? $(this).data('section_num') 
            : $(this).data('section_num')+'-'+$(this).data('subsection_num');
          var path = $(this).data('path');
          var cell_ident = slug+'-'+section_num+'-'+td_index;
          $(this)
            .attr('name',slug+'-'+section_num+'-'+td_index)
            .attr('id','a-'+slug+'-'+section_num+'-'+td_index)
            .attr('href',path+'constitutions/'+cell_ident);

          tabledef.sections[td_index].current_ident   = cell_ident;
          tabledef.sections[td_index].current_slug    = slug;
          tabledef.sections[td_index].current_section = section_num;
          tabledef.sections[td_index].subsection_num  = subsection_num;
        });

        if ( Number.parseInt($(td).attr('colspan')) == 3 ) {
          // Uncommment to restrict to 1987 constitution and latest ConCom draft
          // $(td).attr('colspan','2');
        }
        else if ( td_index == 1 ) {
          // Increase reading space by collapsing 27 June draft column
          $(td).remove();
        }
        else {
          if ( $(td).text().length > 0 )
          tabledef.sections[td_index].contents[tabledef.sections[td_index].contents.length] = {
            ident          : tabledef.sections[td_index].current_ident,
            content        : $(td).text()
          };
        }

        if ( intrasection_links > 0 ) {
          // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
          var section_match = new RegExp('section [0-9]{1,}( of article [XIV]{1,})*','gi');
          var matches;
          while ((matches = section_match.exec( $(td).text() )) !== null) {
            var offset = section_match.lastIndex - matches[0].length;
            if (!( offset > 0 )) continue;
            console.log("Got "+matches[0]+" @ "+(offset)+': '+$(td).text());
          }
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
              } catch(e) {}
            });
          }
        });

      });
    });
    table_count++;
    $('#toc').data('table_count',table_count);
    jQuery.ajax({
      type     : 'POST',
      url      : '/stash/',
      data     : tabledef,
      cache    : false,
      dataType : 'json',
      async    : true,
      complete : (function(jqueryXHR, textStatus) {
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
      })
    });
    tabledef = null;
  });

  // If the requested URL contains a hash part, extract that text and 
  //   place above the "Available Formats" table
  var target_section = parser.hash.replace(/^#/,'');
  if ( target_section.length > 0 ) {
    var section_text = $('#a-'+target_section).parents('TD').first().text();
    $('#selected_section').empty().append(section_text);
  }

  setTimeout(function(){
    // FIXME: Fix up references to existing sections: Add event handler for a click on links that lead to local anchors.
    // Note:  This is potentially an O(n^n) operation.  
    //   Every section cell can refer to every other section's anchor.  
    //   If every section refers to every other, no self-referencing, that's a O(n(n-1)*n) = O(n^3-n^2).
    //   A lower bound for search is O(ni^2), when every cell refers to just one other cell.
    //   Linear time search has glb O(n^2) (a few cells refer to at most one other cell).
    //   So:  Do this in a timed event.

    $('#maindoc-jump-link').each(function(){
      // Presence of this element on the page causes an unconditional document reload.
      document.location = $(this).attr('href');
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
      
      $('#toc').css({'max-height' : ($(window).innerHeight()-90)+'px'}).show();
    }
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(1000);
    },3000));
  });

  $('#toc').data('timer_fade',setTimeout(function(){
    $('#toc').fadeOut(1000);
  },3000));

  // Adjust menu size (position is fixed) 
  $(window).scroll(function(event){
    clearTimeout($('#toc').data('timer_fade'));
    var offsetedge = Number.parseInt(event.pageX);
    var triggeredge = Number.parseInt($('#toc').data('floatedge'));
    if ( offsetedge + 10 < triggeredge ) {
      $('#toc').css({'max-height' : ($(window).innerHeight()-90)+'px'}).show();
    }
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(3000);
    },3000));
    defer_toc_highlight(200);
  });

});

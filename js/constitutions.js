$ = jQuery;
var timer_id = 0;
var enable_copy = 0;
var intrasection_links = 0;
var enable_stash_code = 0;
var enable_html_extractor = 0;

function generate_toc_div(container) {//{{{
  var tocdiv = document.createElement('DIV');
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
  $(container).append(tocdiv);
  $('#toc').data({'prior' : 0, 'floatedge' : 0, 'timer_fade' : 0, 'table_count' : 0});
  return tocdiv;
}//}}}

function highlight_toc_entry(id) {//{{{
  $('#toc').find('A').css({ 'background-color' : 'transparent' });
  $('#toc').find('#link-'+id).css({ 'background-color' : '#DDD' });
}//}}}

function defer_toc_highlight(interval) {//{{{
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
}//}}}

function scroll_to_anchor(event,context,prefix){//{{{

  var self = context;
  var anchor_id;
  var parent_td;

  event.preventDefault();
  event.stopPropagation();

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
  document.location = '#'+anchor_id.replace(/^a-/,'');
}//}}}

function construct_commentary_box(data,row,colspan)
{//{{{
  var slug = data && data.slug ? '-'+data.slug : '';
  var new_cell = $(document.createElement('TD'))
    .attr('colspan',colspan)
    .append(data && data.content 
        ? data.content 
        : $(document.createElement('I')).html(data === null ? '&nbsp;' : 'No comments yet')
        )
    ;
  var new_row = $(document.createElement('TR'))
    .attr('id','commentary-box'+slug)
    .addClass('commentary-boxes')
    .append(new_cell)
    ;
  $('#page').find('[class~=commentary-boxes]').hide();
  $('#page').find('[id=commentary-box'+slug+']').empty().remove();
  $(row).after(new_row);
}//}}}

function cell_copy(event, context)
{//{{{
  var self = context;
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

function handle_comment_linkbox_raise(event,context,slug)
{//{{{
  var self = context;
  var links = new Array();
  var row = $(self).parents('TR').first();
  var colspan = 0;
  // Count visible columns in the row containing this cell.
  jQuery.each($(row).find('TD:visible'),function(column_index, td){
    var t_colspan = $(td).attr('colspan');
    if ( t_colspan === undefined )
      colspan++;
    else
      colspan += +Number.parseInt(t_colspan).toFixed(0);
    jQuery.each($(td).find('A:visible'),function(a_index,anchor){
      links[links.length] = $(anchor).attr('id').replace(/^a-/i,'');
    });
  });
  if ( links.length > 0 )
    jQuery.ajax({
      type     : 'GET',
      url      : '/stash/',
      data     : { 
        sections : links,
        selected : $(self).find('A').attr('id') === undefined ? null : $(self).find('A').attr('id'),
        slug     : $(self).parentsUntil('TABLE').parent().attr('id')
      },
      cache    : false,
      dataType : 'json',
      async    : true,
      beforeSend : (function(jqueryXHR, setttings){
        construct_commentary_box(null,row,colspan);
      }),
      complete : (function(jqueryXHR, textStatus) {
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        // We've sent the server a list of links in the *row* containing this TD cell. 
        construct_commentary_box(data,row,colspan);
        $(self).effect("highlight", {}, 1500);
        $('#comment-send').click(function(event){
          var title = $('#comment-title').val();
          var link  = $('#comment-url').val();
          var summary = $('#comment-summary').val();
          if ( link.length > 0 && title.length > 0 )
            jQuery.ajax({
              type     : 'POST',
              url      : '/stash/'+slug,
              data     : {
                selected : $(self).find('A').attr('id') === undefined ? null : $(self).find('A').attr('id').replace(/^a-/i,''),
                slug     : slug,
                title    : title,
                link     : link,
                summary  : summary,
                links    : links
              },
              cache    : false,
              dataType : 'json',
              async    : true,
              complete : (function(jqueryXHR, textStatus) {
              }),
              success  : (function(data, httpstatus, jqueryXHR) {
                construct_commentary_box(data,row,colspan);
                $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
              })
            });
        });
      })
    });
  else
    $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
}//}}}

function set_section_cell_handler(column_index,slug,context) {//{{{

  var self = $(context);
  var toc = $('#toc').data('toc');
  var toc_index = $('#toc').data('toc_current');
  var parser = document.createElement('A'); 
  parser.href = document.location;

  $(context).data('column_index', column_index);

  if ( undefined === toc[toc_index].section )
    toc[toc_index].section = []; 
  if ( undefined === toc[toc_index].section[column_index] )
    toc[toc_index].section[column_index] = 0; 

  if ( undefined === toc[toc_index].subsection ) 
    toc[toc_index].subsection = [];
  if ( undefined === toc[toc_index].subsection[column_index] ) 
    toc[toc_index].subsection[column_index] = 0;


  // Replace leading subsection string with anchor.
  var is_subsection = /^\(?([0-9a-z]{1})\)[ ]{1}/i.test($(self).text());

  if ( is_subsection ) {//{{{

    var anchor_text = $(self).text();
    var section_num = toc[toc_index].section[column_index];
    var subsection_num = anchor_text.replace(/(\r|\n)/g,' ').replace(/^([(]?)([0-9a-z])\)[ ](.*)/,"$1$2) ");
    var anchor_data = {
      'section_num'    : section_num,
      'subsection_num' : +(toc[toc_index].subsection[column_index])+1,
      'slug'           : slug,
      'path'           : parser.pathname
    };
    var section_anchor = $(document.createElement('A'));

    if ( subsection_num.length > 4 )
      alert( "Warning: "+subsection_num);
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
      .text(subsection_num+' ')
      .click(function(event){
        var self = this;
        scroll_to_anchor(event,$('#'+$(self).attr('id').replace(/link-/,'a-')),'a-');
        $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
      });
    $(self).empty()
      .append(section_anchor)
      .append(anchor_text.replace(/^([(]?)([0-9a-z]{1})\)/i,''))
      ;

    toc[toc_index].subsection[column_index]++;
  } //}}}

  $('#toc').data('toc', toc);
  
  // Replace Section highlight prefix ("SECTION XXX...") with anchor.
  $(self).find('STRONG').each(function(sindex){
    var anchor_container = $(this);
    var anchor_text = $(anchor_container).text();
    var toc = $('#toc').data('toc');
    var toc_current = $('#toc').data('toc_current');

    if ( !(/^see /gi.test(anchor_text) ) ) {//{{{
      // Ignore instances of "See ..."
      if ( /^section ([0-9]{1,})/i.test(anchor_text) ) {
        var section_num = anchor_text.replace(/^section ([0-9]{1,}).*/i,"$1");
        var anchor_data = {
          'section_num' : +(toc[toc_current].section[column_index])+1,
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
            var anchor_self = this;
            scroll_to_anchor(event,anchor_self,'a-');
            $(this).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
          });
        $(anchor_container).empty().append(section_anchor);
        toc[toc_current].section[column_index]++;
        toc[toc_current].subsection[column_index] = 0;
        $('#toc').data('toc', toc);
      }
    }//}}}

  });

  $(context).click(function(event){
    var self = this;
    $('#toc').fadeIn(100);
    // Experimental copy to clipboard

    if ( enable_copy > 0 )
      cell_copy(event,self);

    handle_comment_linkbox_raise(event,self,slug);
  });
  parser = null;
}//}}}

function raise_toc_on_mousemove(event) {//{{{
  var offsetedge = Number.parseInt(event.pageX);
  var triggeredge = Number.parseInt($('#toc').data('floatedge'));
  clearTimeout($('#toc').data('timer_fade'));
  if ( offsetedge + 10 < triggeredge ) {
    $('#toc').css({'max-height' : ($(window).innerHeight()-90)+'px'}).fadeIn(100);
  }
  $('#toc').data('timer_fade',setTimeout(function(){
    $('#toc').fadeOut(1000);
  },3000));
}//}}}

function handle_window_scroll(event) {//{{{
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
}//}}}

$(document).ready(function() {

  // Copyright 2018, Antonio Victor Andrada Hilario
  // avahilario@gmail.com
  // I am releasing this to the public domain.
  // 6 July 2018
  // #StopTheKillings

  var preamble_y = 0;
  var preamble_offset = 0;
  var toc = new Array();
  var tocdiv = generate_toc_div($('#page'));
  var parser = document.createElement('A');

  parser.href = document.location;
  
  // Stylesheet injection
  // Add external link icon to custom CSS
  var wp_custom_css = $('head').find('style#wp-custom-css').text() + ".external-link { background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVQoka2QwQ3AMAwCs1N28k7eiZ3oI7IcU6efBomXOREyxhUZ2brTdNAcVB2BaJgCVcDAalJLXsB+iLAjm1pAwzHWHD3gWMcMg/ERMjKfFOHVqMEGqEM/gKP/6gE2f+h+Z5P45wAAAABJRU5ErkJggg=='); background-repeat:no-repeat; background-position:center left; padding-left: 17px; }";
  $('head').find('style#wp-custom-css').text(wp_custom_css);

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
      // At this point, we can alter the "Section X" text inside tables (the one with id {slug}),
      // and turn those string fragments into HTML anchors.
      .attr('id',slug)
      // First, highlight weasel words
      // Replacing HTML damages DOM attributes
      .find('TR').each(function(row_index){
        jQuery.each($(this).find('TD'),function(column_index,td){
          var ww = $(td).html()
            .replace(/((provided )?(for )?by law)/i, '<span style="color: red; font-weight: bold">$1</span>')
            .replace(/^SECTION ([0-9]{1,})./i, '<strong>SECTION $1.</strong>')
            ;
          $(td).html(ww);
          set_section_cell_handler(column_index,slug,td);
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

  if ( enable_html_extractor > 0 ) {
    var html_extractor = $(document.createElement('DIV'))
      .attr('id','html-extractor')
      .css({
        'position'         : 'fixed',
        'width'            : '100px',
        'top'              : '50px',
        'left'             : ($(window).innerWidth()-110)+'px',
        'display'          : 'block',
        'clear'            : 'none',
        'z-index'          : '10',
        'overflow'         : 'scroll',
        'overflox-x'       : 'hidden',
        'float'            : 'right',
        'border'           : 'solid 3px #DDD',
        'background-color' : '#FFF',
      });

    $('#page').append(html_extractor);
  }

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

    // Store table properties for /stash/
    tabledef.slug = slug;
    tabledef.title = $('#h-'+slug).text();

    $(table).addClass('constitution-article');

    if ( enable_html_extractor > 0 ) {
      // Duplicate markup components, except those dynamically generated.
      $('#html-extractor').append($('#h-'+slug).clone());
    }

    var visible_columns = 0;
    jQuery.each($(table).find('TR'), function(tr_index, tr) {
      // TR context
      var previous_column_cell = null;
      var row_visible_cells = 0;
      jQuery.each($(tr).children(), function(td_index,td){
        // TD context
        // 0. Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
        // 1. Locate and modify toc-section anchors
        // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
        // 3. Apply column span mod and collapse middle columns

        if ( enable_stash_code > 0 ) {
          if ( undefined === tabledef.sections[td_index] )
            tabledef.sections[td_index] = {
              current_ident : null,
              current_slug : null,
              current_section : null,
              subsection_num : null,
              contents : new Array()
            };
        }

        // Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
        $(td).data('index',td_index);
        // Add column classname to ConCom draft sections
        if ( td_index > 0 ) {
          $(td).addClass("concom-"+td_index);
        }
 
        // 1. Locate and modify toc-section anchors: Make links point to /constitutions/ section preview. 
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

          if ( enable_stash_code > 0 ) {
            tabledef.sections[td_index].current_ident   = cell_ident;
            tabledef.sections[td_index].current_slug    = slug;
            tabledef.sections[td_index].current_section = section_num;
            tabledef.sections[td_index].subsection_num  = subsection_num;
          }
        });

        // Column presentation mods 
        if ( Number.parseInt($(td).attr('colspan')) == 3 ) {
          // Uncommment to restrict to 1987 constitution and latest ConCom draft
          // $(td).attr('colspan','2');
          $(td).addClass('header-full');
        }
        else if ( td_index == 1 ) {
          // Increase reading space by collapsing 27 June draft column
          $(td).hide();
        }
        else {
          row_visible_cells++; 
          if ( td_index == 3 ) {
            if ( $(previous_column_cell).text().length == $(td).text().length && $(td).text().length == 0 ) {
              $(previous_column_cell).hide();
              $(td).attr('colspan','2');
              row_visible_cells--;
            }
            else if ( $(previous_column_cell).text() == $(td).text() ) {
              if ( $(previous_column_cell).text().length > 0 ) {
                $(previous_column_cell).hide();
                $(td).attr('colspan','2');
                row_visible_cells--;
              }
            }
          }
          if ( $(td).text().length > 0 ) {
            // Store Article table parameters for /stash/
            if ( enable_stash_code > 0 ) tabledef.sections[td_index].contents[tabledef.sections[td_index].contents.length] = {
              ident          : tabledef.sections[td_index].current_ident,
              content        : $(td).text()
            };
          }
          if ( enable_html_extractor > 0 ) {
            // Add table cell to html-extractor
            var clone = $(td).clone().addClass('final-20180717');
            if ( /^ConCom Draft.*/i.test($(clone).text()) ) {
              $(clone).empty();
              $(clone).html("ConCom Final\rDraft");
            }
            $(td).parent().append(clone);
          }
        }

        // Force automatic height computation
        $(td).css({'height' : 'auto'});

        if ( intrasection_links > 0 ) {//{{{
          // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
          var section_match = new RegExp('section [0-9]{1,}( of article [XIV]{1,})*','gi');
          var matches;
          while ((matches = section_match.exec( $(td).text() )) !== null) {
            var offset = section_match.lastIndex - matches[0].length;
            if (!( offset > 0 )) continue;
            console.log("Got "+matches[0]+" @ "+(offset)+': '+$(td).text());
          }
        }//}}}

        jQuery.each($(td).find('A'),function(a_index,anchor){
          // Separately: If this cell contains any A tags linking to any other cell in this document,
          // we add a click handler that causes the browser to scroll that target into view.
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

        previous_column_cell = $(td);
      });
      if ( tr_index > 0 ) {
        if ( row_visible_cells > visible_columns )
          visible_columns = row_visible_cells;
      }

      $(tr).css({'height':'auto'});
    });

    if ( visible_columns < 3 ) {
      $(table).find('tr').find('.concom-1').each(function(){$(this).hide();});
      $(table).find('tr').find('.concom-2').each(function(){$(this).hide();});
      $(table).find('tr').find('td').each(function(){
        if ( $(this).hasClass('header-full') )
          $(this).attr('colspan','2');
        else
          $(this).attr('colspan','1');
      });
    }

    table_count++;
    $('#toc').data('table_count',table_count);

    if ( enable_stash_code > 0 ) {
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
    }

    tabledef = null;

    if ( enable_html_extractor > 0 ) {
      // Append this table to the HTML extractor div
      $('#html-extractor')
        .append($(table).clone());
    }
  });

  if ( enable_html_extractor > 0 ) {
    // Clean up the HTML extractor's cells.  Replace TD contents with text.
    jQuery.each($('div#html-extractor').find('table'),function(index,table){
      jQuery.each($(table).find('TR'), function(tr_index, tr) {
        jQuery.each($(tr).children(), function(td_index,td){
          var content = document.createTextNode($(td).text());
          $(td).text($(content).text());
        });
      });
    });
  }

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

    var parser = document.createElement('A');
    parser.href = document.location;

    // Facebook Preview fix: Jump to real page after a few seconds.
    $('#maindoc-jump-link').each(function(){
      // Presence of this element on the page causes an unconditional document reload.
      var self = this;
      setTimeout(function(){
        document.location = $(self).attr('href');
      },7000);
    });

    // Reset font size by removing all font-size style specifiers
    var custom_css = $('head').find('style#wp-custom-css').text().replace(/font-size: ([^;]{1,});/i,'');
    $('head').find('style#wp-custom-css').text(custom_css);


    // Hide TOC after a few seconds
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(1000);
    },3000));

  },100);

  // Attach handler that triggers reappearance of TOC on mouse movement
  $(window).mousemove(function(event){
    raise_toc_on_mousemove(event);
  });

  // Adjust menu size (position is fixed) 
  $(window).scroll(function(event){
    handle_window_scroll(event);
  });

  setTimeout(function(){
    // If the parser was given an existing anchor, go to it, after this initialization is done..
    $('#page').find('#a-'+parser.hash.replace(/^\#/,'')).first().each(function(){
      $(this).click();
      $(this).parentsUntil('TD').parents().first().click();
    });
  },200);

});

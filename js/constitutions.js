$ = jQuery;

function Lecturer()
{/*{{{*/
  this.enable_copy            = 0;
  this.intrasection_links     = 0;
  this.intradoc_links         = 0;
  this.enable_stash_code      = 1;
  this.enable_html_extractor  = 0;
  this.enable_debug_indicator = 0;
  this.table_header_offset    = 0;
  this.tocdiv                 = null;
  this.toc                    = new Array();
  this.parser                 = document.createElement('A');
}/*}}}*/

Lecturer.prototype = 
{//{{{

  generate_toc_div : function(container)
  {//{{{
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
        'border'           : 'solid 0px transparent'
      })
    .text("");

    // Add TOC div to WordPress content DIV
    $(container).append(tocdiv);
    $('#toc').data({
      'prior'       : 0,
      'floatedge'   : 0,
      'timer_fade'  : 0,
      'scroll_w'    : 0,
      'toc_hltime'  : 0,
      'table_count' : 0
    });
    return tocdiv;
  }//}}}
  ,

  generate_debug_view : function(container)
  {//{{{

    if ( !(this.enable_debug_indicator > 0) )
      return null;

    var debugdiv = document.createElement('DIV');
    $(debugdiv)
      .attr('id','debug')
      .css({
        'width'            : '280px',
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
        'left'             : (+$(window).innerWidth()-320)+'px',
        'border'           : 'solid 1px #DDD'
      })
    .text("");

    // Add TOC div to WordPress content DIV
    $(container).append(debugdiv);
    return debugdiv;

  }//}}}
  ,

  generate_user_commentview : function(container)
  {//{{{
    var commentview = document.createElement('DIV');
    $(commentview)
      .attr('id','commentary-sidebar')
      .css({
        'width'            : '380px',
        'max-height'       : ($(window).innerHeight()-90)+'px',
        'background-color' : '#F0F0F0',
        'padding'          : '5px 5px 5px 5px',
        'margin-left'      : '5px',
        'overflow-x'       : 'hidden',
        'display'          : 'block',
        'float'            : 'right',
        'clear'            : 'none',
        'position'         : 'fixed',
        'z-index'          : '5',
        'top'              : '40px',
        'left'             : (+$(window).innerWidth()-420)+'px',
        'border'           : 'solid 1px #DDD'
      })
    .text("");

    // Add TOC div to WordPress content DIV
    $(container).append(commentview);
    $('#commentary-sidebar').click(function(event){
      $('#commentary-sidebar').fadeOut(400);
    });
    return commentview;
  }//}}}
  ,

  highlight_toc_entry : function(id)
  {//{{{
    if ( undefined === id ) return;
    $('#toc').find('A').css({ 'background-color' : 'transparent' });
    jQuery.each($('#toc').find('#link-'+id),
        function(dummy,a) {
          $(a).css({ 'background-color' : '#DDD' });
          document.title = $(a).text();
        });
  }//}}}
  ,

  toc_highlight : function(f)
  {//{{{
    var Self = this;
    var top_edge = +Number.parseInt($(window).scrollTop().toFixed(0)); 
    var innerheight = +Number.parseInt($(window).innerHeight().toFixed(0));
    var bottom_edge = +top_edge+innerheight;
    var maxdist = innerheight;
    var prevdist = maxdist;
    var prevrow;

    $('#toc').data('cell-in-viewport',false);
    $('#toc').data('in-scope-cell','');

    this.highlight_toc_entry($('#toc').data('toc-current'));

    // Issue #5: The viewport is contained within an Article's scope when 
    $('#commentary-sidebar').fadeOut(400);

    $('#toc').data('occluded',0);
    $('#toc').data('matched-table','');

    // Defer /stash/ GET action when in WP admin mode. 
    jQuery.each($('#wpadminbar'),function(dummy,wpadminbar){
      $('#toc').data('defer-viewport-scope',1);
    });

    if ( ( $('#toc').data('defer-viewport-scope') || 0 ) == 1 )
      return;

    jQuery.each($('#page').find('H1'),function(h_index,h1) {

      if ( $('#toc').data('cell-in-viewport') ) {
        if ( +$('#toc').data('occluded') > 0 )
          console.log("Freeze header for "+$('#toc').data('matched-table'));
        else
          console.log("No header for "+$('#toc').data('matched-table'));

        return;
      }

      var h1_id = $(h1).attr('id');

      jQuery.each($('#'+h1_id+' ~ table').first(),function(t_index, table) {

        $('#toc').data('testing-table', $(table).attr('id'));

        if ( $('#toc').data('cell-in-viewport') ) return;

        var occluded = 0;
        var inscope = 0;
        jQuery.each($(table).find('TR'),function(tr_index,tr){
          // Get row bounding rectangle.
          var bounding = tr.getBoundingClientRect();
          $(tr).attr('title',"("+bounding.top+","+(+bounding.top+bounding.height).toFixed(0)+")");
          if ( bounding.top >= 0 && 
              bounding.bottom <= innerheight ) {
            // Sample distance between bisector of the row and bisector of the viewport.
            var distmid = Math.abs((bounding.top + (bounding.height / 2)) - (innerheight / 2)); 
            if ( prevdist < distmid )
              return; // Skip sampling when minima already stored
            if ( distmid < maxdist ) {
              if (!('undefined' === typeof(prevrow)))
                $(prevrow).removeClass('in-scope'); 

              $('#toc').data('cell-in-viewport',true); // Suppresses further iterations

              $(tr).addClass('in-scope');
              inscope++;
              $('#toc').data('matched-table',$('#toc').data('testing-table'));
              prevrow = tr;
            }
            // $(tr).attr('title',"Distance "+distmid);
            prevdist = distmid;
          }
          else {
            // If at least out-of-scope table row precedes a number of in-scope rows, 
            // then the table header must be frozen at the top of the viewport, 
            // since the viewport occludes at least one row.
            $(tr).removeClass('in-scope');
            if ( inscope == 0 )
              occluded++;
          }
        });
        $('#toc').data('article-scope',"In: "+inscope+", Occ "+occluded+', '+$('#toc').data('matched-table'));
        $('#toc').data('occluded',occluded);

        if ( occluded > 0 ) {
          var matched_table = $('#toc').data('matched-table');
          if ( matched_table ) jQuery.each($('#'+matched_table).find('.article-header').first(),function(dummy,tr_header){
            var tr_bb = tr_header.getBoundingClientRect();
            var cloned_th = $(tr_header).clone();
            Self.table_header_offset = tr_bb.height;
            $('#floating-header').remove();
            $('#page').append(
                $(document.createElement('TABLE'))
                .append(cloned_th)
                .attr('id','floating-header')
                .css({
                  'background-color' : '#FFF',
                  'z-index'  : '1001',
                  'position' : 'fixed',
                  'top'      : '0px',
                  'left'     : tr_bb.left,
                  'width'    : tr_bb.width,
                  'height'   : tr_bb.height
                })
                );
          });
        }
        else
          setTimeout(function(){ $('#floating-header').fadeOut(300); },100);

        jQuery.each($(table).find('.in-scope'),function(t_index_2,matched) {
          // In TR scope
          jQuery.each($(matched).find('TD'),function(td_index_m,td) {
            if ( undefined === $(td).attr('id') )
              return;
            if ( 0 < $('#toc').data('in-scope-cell').length )
              return;
            // console.log($(td).attr('id'));
            if (undefined === $(td).data('hidden')) {
              // Store the slug for the first cell containing a link.
              jQuery.each($(td).find('A'),function(dummy,a){
                $('#toc').data('in-scope-cell',$(a).attr('id').replace(/^a-/,''));
              });
            }
          });
        });
      });

      // Defer activating the /stash/ GET request until after the user
      // has stopped scrolling for about a second and a half.
      if ( 0 < $('#toc').data('in-scope-cell').length ) {
        $('#toc').data('scroll_w',setTimeout(function(){
          $('#c-'+$('#toc').data('in-scope-cell')).click();
        },1500));
      }
    });

    if ( this.enable_debug_indicator > 0 )
    {//{{{/// DEBUG //////////////////////////////////////////////
      $('#debug').empty()
        .append(
            $(document.createElement('SPAN'))
            .text(top_edge)
            )
        .append($(document.createElement('BR')))
        .append(
            $(document.createElement('SPAN'))
            .text(bottom_edge)
            )
        .append($(document.createElement('BR')))
        .append(
            $(document.createElement('DIV'))
            .attr('id','current-td')
            .text($('#toc').data('in-scope-cell'))
            )
        .append(
            $(document.createElement('DIV'))
            .attr('id','scoping')
            .text($('#toc').data('article-scope'))
            )
        ;
      /// DEBUG //////////////////////////////////////////////
    }//}}}


  }//}}}
  ,

  defer_toc_highlight : function(interval)
  {//{{{
    var Self = this;
    if ( $('#toc').data('toc_hltime') > 0 ) 
      clearTimeout($('#toc').data('toc_hltime'));
    $('#toc').data('toc_hltime',setTimeout(function(){
      Self.toc_highlight();
    },interval));

  }//}}}
  ,

  scroll_to_anchor : function(event,context,prefix)
  {//{{{

    var Self = this;
    var self = context;
    var anchor_id;
    var parent_td;

    event.preventDefault();
    event.stopPropagation();

    anchor_id = $(self).attr('href').replace(/#/,'').replace(/^\/(constitutions\/)?/,prefix);

    $('#'+anchor_id).parents('TD').first().each(function(){
      var self = this;
      var offset_top = $(self).offset().top.toFixed(0);
      var parent_offset_top = +($(self).parents('TR').offset().top).toFixed(0);

      if (parent_offset_top === undefined) {
        $('html, body').animate({
          scrollTop: +($(self).offset().top).toFixed(0)
        });
      }
      else {
        $(self).parents('TR').first().each(function(){
          var self = this;
          $('html, body').animate({
            scrollTop: +($(self).offset().top).toFixed(0)-Self.table_header_offset,
            backgroundColor: '#FFFFFF'
          },{
            complete : (function(){ 
              document.location = '#'+anchor_id.replace(/^a-/i,''); 
              $('html, body').scrollTop(+($(self).offset().top).toFixed(0)-Self.table_header_offset);
            })
          });
        });
      }

    });
    if ($('#'+anchor_id).parents('TR').length === 0) {
      $('html, body').animate({
        scrollTop: +($('#'+anchor_id).offset().top).toFixed(0)-Self.table_header_offset
      },{
        complete : (function(){ 
          document.location = '#'+anchor_id.replace(/^a-/i,''); 
          $('html, body').scrollTop(+($(self).offset().top).toFixed(0)-Self.table_header_offset);
        })
      });
    }

  }//}}}
  ,

  editable_link_click_event : function(event)
  {//{{{
    var clickable = event.target;
    var clickable_id = $(clickable).attr('id').replace(/^link-/,'');
    event.preventDefault();
    event.stopPropagation();
    document.title = $(clickable).text();
    $('#comment-title').val($(clickable).text());
    $('#comment-url').val($(clickable).attr('href'));
    $('#comment-summary').val($('#comment-'+clickable_id).text());
    $('#comment-url').data('match',clickable_id);
  }//}}}
  ,

  remove_comment_link : function(event)
  {//{{{
    var Self = this;
    var clickable = event.target;
    var containing_table = $(clickable).parentsUntil('table').parent().first();
    jQuery.ajax({
      type : 'DELETE',
      url  : '/stash/'+$(clickable).data('section')+'/'+$(clickable).attr('id'),
      cache : false,
      dataType : 'json',
      async : true,
      beforeSend : (function(jqueryXHR, setttings) {
      }),
      complete : (function(jqueryXHR, textStatus) {
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        jQuery.each($(containing_table).find('TR[class*=comment-target]'),function(dummy,tr){
          $(tr).removeClass('comment-target').find('TD').first().click();
        });
      })
    });
  }//}}}
  ,

  construct_commentary_row : function(data,row,colspan)
  {//{{{
    var Self = this;
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
    jQuery.each($(new_row).find('A[class~=editable]'),function(linkindex, anchor){
      $(anchor).click(Self.editable_link_click_event);
    });
    jQuery.each($(new_row).find('[class~=trash]'),function(linkindex, clickable){
      $(clickable)
        .data('section',data.slug)
        .click(Self.remove_comment_link);
    });
    $('#page').find('[class~=commentary-boxes]').hide();
    $('#page').find('[id=commentary-box'+slug+']').empty().remove();
    $(row)
      .data('columns', colspan )
      .addClass('comment-target')
      .after(new_row);
  }//}}}
  ,

  construct_sidebar : function(data)
  {//{{{
    if ( data && data.content ) { 
      $('#commentary-sidebar').empty().append(data.content);
      $('#commentary-sidebar').effect("highlight", {}, 700);
    }
  }//}}}
  ,

  construct_commentary_box : function(data,row,colspan)
  {//{{{
    try {
      var sidebar = $('#commentary-sidebar').attr('id');
    } catch(e) {}

    if ( undefined === sidebar ) 
      this.construct_commentary_row(data,row,colspan);
    else
      this.construct_sidebar(data);
  }//}}}
  ,

  cell_copy : function(event, context)
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
  ,

  handle_comment_linkbox_raise : function(event,slug)
  {//{{{
    var Self = this;
    var self = event.target;
    var links = new Array();
    var row = $(self).parents('TR').first();
    var colspan = 0;
    // Remove 'comment-target' class from all rows in this cell's parent table
    jQuery.each($(row)
        .parentsUntil('table')
        .first()
        .parent()
        .find('TR[class~=comment-target]'),
        function(dummy,tr){
          $(tr).removeClass('comment-target');
        });
    // Count visible columns in the row containing this cell.
    jQuery.each($(row).find('TD:visible'),function(column_index, td){
      var t_colspan = $(td).attr('colspan');
      if ( t_colspan === undefined )
        colspan++;
      else
        colspan += +Number.parseInt(t_colspan).toFixed(0);
      jQuery.each($(td).find('A:visible'),function(a_index,anchor){
        if ( undefined === $(anchor).attr('id') ) return;
        links[links.length] = ( $(anchor).attr('id') || $(anchor).attr('name') ).replace(/^a-/i,'');
      });
    });
    if ( links.length > 0 )
      jQuery.ajax({
        type     : 'GET',
        url      : '/stash/',
        data     : { 
          sections : links,
          selected : $(self).find('A').attr('id') === undefined ? null : $(self).find('A').attr('id'),
          slug     : $(self).parentsUntil('TABLE').parent().attr('id'),
        },
        cache    : false,
        dataType : 'json',
        async    : true,
        beforeSend : (function(jqueryXHR, setttings){
          Self.construct_commentary_box(null,row,colspan);
        }),
        complete : (function(jqueryXHR, textStatus) {
        }),
        success  : (function(data, httpstatus, jqueryXHR) {
          // We've sent the server a list of links in the *row* containing this TD cell. 
          if ( data && data.mode ) {
            if ( data.mode == 1 ) 
              jQuery.each($('#commentary-sidebar'),function(dummy,div){
                $(div).remove();
              });
          }
          Self.construct_commentary_box(data,row,colspan);
          if ( data && data.content )
            $(self).effect("highlight", {}, 1500);
          $('#comment-send').click(function(event){
            var title = $('#comment-title').val();
            var link  = $('#comment-url').val();
            var summary = $('#comment-summary').val();
            var match = $('#comment-url').data('match');
            if ( link.length > 0 && title.length > 0 )
              jQuery.ajax({
                type     : 'POST',
                url      : '/stash/'+slug,
                data     : {
                  selected : $(self).find('A').attr('id') === undefined ? null : $(self).find('A').attr('id').replace(/^a-/i,''),
                  column   : $(self).data('index'),
                  slug     : slug,
                  match    : match,
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
                  Self.construct_commentary_box(data,row,colspan);
                  $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
                  $('#comment-url').data('match',null);
                })
              });
          });
        })
      });
    else
      $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
  }//}}}
  ,

  substitute_section_link :  function(sindex, strong, column_index, slug) 
  {//{{{

    var Self = this;
    var anchor_container = $(strong);
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
          'path'        : this.parser.pathname
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
            var anchor_self = event.target;
            Self.scroll_to_anchor(event,anchor_self,'a-');
            $(this).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
          });
        $(anchor_container).empty().append(section_anchor);
        toc[toc_current].section[column_index]++;
        toc[toc_current].subsection[column_index] = 0;
        $('#toc').data('toc', toc);
      }
    }//}}}

  }//}}}
  ,

  set_section_cell_handler : function(column_index,slug,context)
  {//{{{

    var Self = this;
    var toc = $('#toc').data('toc');
    var toc_index = $('#toc').data('toc_current');

    Self.parser.href = document.location;

    $(context).data('column_index', column_index);

    if ( undefined === this.toc[toc_index].section )
      this.toc[toc_index].section = []; 
    if ( undefined === this.toc[toc_index].section[column_index] )
      this.toc[toc_index].section[column_index] = 0; 

    if ( undefined === this.toc[toc_index].subsection ) 
      this.toc[toc_index].subsection = [];
    if ( undefined === this.toc[toc_index].subsection[column_index] ) 
      this.toc[toc_index].subsection[column_index] = 0;


    // Replace leading subsection string with anchor.
    var is_subsection = /^\(?([0-9a-z]{1})\)[ ]{1}/i.test($(context).text());

    if ( is_subsection ) {//{{{

      var anchor_text = $(context).text();
      var section_num = this.toc[toc_index].section[column_index];
      var subsection_num = anchor_text.replace(/(\r|\n)/g,' ').replace(/^([(]?)([0-9a-z])\)[ ](.*)/,"$1$2) ");
      var anchor_data = {
        'section_num'    : section_num,
        'subsection_num' : +(this.toc[toc_index].subsection[column_index])+1,
        'slug'           : slug,
        'path'           : Self.parser.pathname
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
          Self.scroll_to_anchor(event,$('#'+$(self).attr('id').replace(/link-/,'a-')),'a-');
          $(self).parentsUntil('TR').first().parent().effect("highlight", {}, 1500);
        });
      $(context).empty()
        .append(section_anchor)
        .append(anchor_text.replace(/^([(]?)([0-9a-z]{1})\)/i,''))
        ;

      this.toc[toc_index].subsection[column_index]++;
    } //}}}

    $('#toc').data('toc', this.toc);
    
    // Replace Section highlight prefix ("SECTION XXX...") with anchor.
    jQuery.each($(context).find('STRONG'),function(sindex,strong){
      Self.substitute_section_link(sindex,strong,column_index,slug);
    });

    $(context).click(function(event){
      $('#toc').fadeIn(100);
      // Experimental copy to clipboard
      if ( this.enable_copy > 0 )
        Self.cell_copy(event);
      Self.handle_comment_linkbox_raise(event,slug);
    });
    parser = null;
  }//}}}
  ,

  update_toc_size : function()
  {//{{{
    if ( !('undefined' === typeof($('#toc').data('rightedge'))) ) {
      $('#toc').css({
        'width' : (+Number.parseInt($('#toc').data('rightedge'))-20)+'px'
      });
    }
    $('#toc').css({'max-height' : ($(window).innerHeight()-90)+'px'});
  }//}}}
  ,

  raise_toc_on_mousemove : function(event)
  {//{{{
    var offsetedge = Number.parseInt(event.pageX);
    var triggeredge = Number.parseInt($('#toc').data('floatedge'));
    clearTimeout($('#toc').data('timer_fade'));
    this.update_toc_size();
    if ( offsetedge + 10 < triggeredge ) {
      $('#toc').fadeIn(100);
    }
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(1000);
    },3000));
  }//}}}
  ,
  
  handle_window_scroll : function(event)
  {//{{{
    clearTimeout($('#toc').data('timer_fade'));
    clearTimeout($('#toc').data('scroll_w'));
    var offsetedge = Number.parseInt(event.pageX);
    var triggeredge = Number.parseInt($('#toc').data('floatedge'));
    if ( offsetedge + 10 < triggeredge ) {
      $('#toc').css({'max-height' : ($(window).innerHeight()-90)+'px'}).show();
    }
    $('#toc').data('timer_fade',setTimeout(function(){
      $('#toc').fadeOut(3000);
    },3000));
    this.defer_toc_highlight(200);
  }//}}}
  ,

  build_toc_from_articles : function(article_index,article_head)
  {//{{{

    // The variables 
    //     article_index, 
    //     toc_index, 
    //     column_index, and 
    //     toc.section[column_index],
    //     toc.subsection[column_index] 
    // are used to generate anchor links.
    var Self = this;
    var article_text = $(article_head).text();
    var slug = article_text.toLowerCase().replace(/\n/,' ').replace(/[^a-z ]/g,' ').replace(/[ ]{1,}/,' ').replace(/[ ]*$/,'').replace(/[ ]{1,}/g,'-');
    var link = document.createElement('A');
    var anchor = document.createElement('A');
    var toc_index = toc.length;

    // Set link color if the article includes "draft" or "new" 
    var link_color = /(draft|new)/i.test(article_text) 
      ? { 'color' : '#F01' } 
      : /(available formats)/i.test(article_text)
        ? { 'color' : 'blue', 'font-weight' : 'lighter' }
        : { 'color' : 'blue' };

    // Prepare TOC link
    $(link).attr('id','link-'+slug)
      .addClass('toc-link')
      .addClass('toc-real')
      .css(link_color)
      .text(article_text.replace(/Article ([a-z]{1,})/gi,''))
      .click(function(event){
        var self = event.target;
        var targete = '#'+$(self).attr('id').replace(/^link-/,'a-');
        var slug = $(self).attr('id').replace(/^link-/,'');
        Self.scroll_to_anchor(event,$(targete),'a-');
        $('#toc').data('toc-current',slug);
        Self.highlight_toc_entry(slug);
      })
      ;
    // Record TOC entry for use in animating TOC highlight updates.
    this.toc[toc_index] = { 
      article    : article_index,
      section    : {},
      subsection : {},  // Counters; reset at each section
      offset     : $(article_head).offset().top.toFixed(0),
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
        'height'          : '1px',
        'display'         : 'block',
        'clear'           : 'both',
        'box-shadow'      : '0 0 0 0' 
      })
      .attr('href','#'+slug)
      .append('&nbsp;')
      .addClass('toc-anchor');

    // Add ID attribute to this H1 tag, replace text, and add ID to table body. 
    // Also apply formatting.
    $(article_head)
      .before(anchor)
      .attr('id','h-'+slug)
      .css({
        'text-align' : 'center'
      })
      ;

    // Get the first table following an h-<slug> H1
    jQuery.each($('#h-'+slug+' ~ table').first(),function(table_index, table){
      $(table)
        // At this point, we can alter the "Section X" text inside tables (the one with id {slug}),
        // and turn those string fragments into HTML anchors.
        .attr('id',slug)
        // First, highlight weasel words
        // Replacing HTML damages DOM attributes
        .find('TR').each(function(row_index){
          var row = this;
          jQuery.each($(row).find('TD'),function(column_index,td){
            var ww = $(td).html()
              .replace(/((provided )?(for )?by law)/i, '<span style="color: red; font-weight: bold">$1</span>')
              .replace(/^SECTION ([0-9]{1,})./i, '<strong>SECTION $1.</strong>')
              ;
            $(td)
              .data('index',column_index)
              .html(ww);
            Self.set_section_cell_handler(column_index,slug,td);
          });
        });
      // Make it's left edge the trigger edge
      $('#toc').data('floatedge',$(table).offset().left );
    });


    // Do once:  Set right edge of TOC DIV
    if ( 'undefined' === typeof( $('#toc').data('rightedge')) ) {
      var table_offset = $('#h-'+slug+' ~ table').first().offset();
      if ( !('undefined' === typeof(table_offset) ) ) { 
        $('#toc').data('rightedge', +table_offset.left-15);
        this.update_toc_size();
      }
    }

    // TODO: Replace references to articles ("in Article X...") with links to local anchors.

    // Append Article link (and an explicit line break) to the TOC container 
    $(this.tocdiv).append(link);
    $(this.tocdiv).append(document.createElement('BR'));
  }//}}}
  ,

  mark_highlighted_cell : function(event)
  {//{{{
    var cell = event.target;
    var Self = this;
    jQuery.each($(cell).parents('TABLE').first(), function(tindex, table) {
      var slug = $(this).attr('id');
      $('#toc').data('toc-current',slug);
    });
    var slug = $(cell).attr('id');
    $('#toc').data('current-td',slug);

  }//}}}
  ,

  prepare_table_presentation : function(Self,index,table)
  {//{{{

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

    if ( this.enable_html_extractor > 0 ) {
      // Duplicate markup components, except those dynamically generated.
      $('#html-extractor').append($('#h-'+slug).clone());
    }

    var visible_columns = 0;

    // Hide cells that duplicate a previous revision's content
    // Extract modified DOM tables (when enable_html_extractor > 0)
    jQuery.each($(table).find('TR'), function(tr_index, tr) {//{{{
      // TR context
      var previous_column_cell = null;
      var row_visible_cells = 0;

      if ( tr_index == 0 ) $(tr).addClass('article-header');

      jQuery.each($(tr).children(), function(td_index,td){//{{{

        // TD context
        // 0. Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
        // 1. Locate and modify toc-section anchors
        // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
        // 3. Apply column span mod and collapse middle columns

        if ( this.enable_stash_code > 0 ) {
          if ( undefined === tabledef.sections[td_index] )
            tabledef.sections[td_index] = {
              current_ident   : null,
              current_slug    : null,
              current_section : null,
              subsection_num  : null,
              contents        : new Array()
            };
        }

        // Force automatic height computation, override WordPress editor
        $(td).css({'height' : 'auto', 'vertical-align' : 'top'});

        // Add column classname to ConCom draft sections
        if ( td_index > 0 ) {
          $(td).addClass("concom-"+td_index);
        }

        // Identify table to which this cell belongs
        $(td).mouseover( function(event){
          Self.mark_highlighted_cell(event);
        });

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

          $(this)
            .parents('TD').first().attr('id','c-'+cell_ident);

          if ( this.enable_stash_code > 0 ) {
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
          row_visible_cells++; 
        }
        else if ( td_index == 1 ) {
          // Increase reading space by collapsing 27 June draft column
          $(td).remove();
          return;
        }
        else {
          // TODO: Replace with cell labeling
          if ( td_index == 3 ) {
            if ( $(previous_column_cell).text().length == $(td).text().length && $(td).text().length == 0 ) {
              $(previous_column_cell).data('hidden',1).hide();
              $(td).attr('colspan','2');
            }
            else if ( $(previous_column_cell).text() == $(td).text() ) {
              // Merge text of identical 9 July and Final Draft cells
              if ( $(previous_column_cell).text().length > 0 ) {
                $(previous_column_cell).data('hidden',1).hide();
                $(td).attr('colspan','2');
              }
            }
            else if ( $(td).text().length > 0 ) { 
              if ( tr_index > 0 )
                row_visible_cells++;
            }
          }
          else if ( tr_index > 0 ) {
            if ( $(td).text().length > 0 ) 
              row_visible_cells++; 
          }
          else if ( tr_index == 0 && td_index == 0 ) {
            // Only count the first visible cell
            row_visible_cells++;
          }
          if ( $(td).text().length > 0 ) {
            // Store Article table parameters for /stash/
            if ( this.enable_stash_code > 0 ) tabledef.sections[td_index].contents[tabledef.sections[td_index].contents.length] = {
              ident          : tabledef.sections[td_index].current_ident,
              content        : $(td).text()
            };
          }

          if ( this.enable_html_extractor > 0 ) {
            // Add table cell to html-extractor
            var clone = $(td).clone().addClass('final-20180717');
            if ( /^ConCom Draft.*/i.test($(clone).text()) ) {
              $(clone).empty();
              $(clone).html("ConCom Final\rDraft");
            }
            $(td).parent().append(clone);
          }
        }

        if ( this.intrasection_links > 0 ) {//{{{
          // 2. Modify substrings "Section XXX" and convert to links pointing WITHIN the Article table. 
          var section_match = new RegExp('section [0-9]{1,}( of article [XIV]{1,})*','gi');
          var matches;
          while ((matches = section_match.exec( $(td).text() )) !== null) {
            var offset = section_match.lastIndex - matches[0].length;
            if (!( offset > 0 )) continue;
            console.log("Got "+matches[0]+" @ "+(offset)+': '+$(td).text());
          }
        }//}}}

        if ( this.intradoc_links > 0 ) jQuery.each($(td).find('A'),function(a_index,anchor){
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
                Self.scroll_to_anchor(event,this,'a-');
              } catch(e) {}
            });
          }
        });

        previous_column_cell = $(td);
      });//}}}

      if ( tr_index > 0 ) {
        // Skip header column
        if ( row_visible_cells > visible_columns )
          visible_columns = row_visible_cells;
      }

      if ( row_visible_cells == 0 ) {
        $(tr).remove();
      }
      else
        $(tr).css({'height':'auto'});
    });//}}}

    if ( 0 < visible_columns && visible_columns < 3 ) {
      $(table).find('tr').find('.concom-1').each(function(){$(this).hide();});
      $(table).find('tr').find('.concom-2').each(function(){$(this).hide();});
      $(table).find('tr').find('td').each(function(){
        if ( $(this).hasClass('header-full') )
          $(this).attr('colspan','2').css({'text-align' : 'center'});
        else
          $(this).attr('colspan','1');
      });
    }

    table_count++;

    $('#toc').data('table_count',table_count);

    if ( this.enable_stash_code > 0 ) {
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

    if ( this.enable_html_extractor > 0 ) {
      // Append this table to the HTML extractor div
      $('#html-extractor')
        .append($(table).clone());
    }
  }//}}}
  ,

  unconditional_maindoc_reload : function(jumplink)
  {//{{{
    // Presence of this element on the page causes an unconditional document reload.
    setTimeout(function(){
      document.location = $(jumplink).attr('href');
    },7000);
  }//}}}
  ,

  jq_document_ready : function()
  {/*{{{*/
    // Copyright 2018, Antonio Victor Andrada Hilario
    // avahilario@gmail.com
    // I am releasing this to the public domain.
    // 6 July 2018
    // #StopTheKillings

    var Self = this;

    Self.tocdiv = Self.generate_toc_div($('#page'));
    /// DEBUG //////////////////////////////////////////////
    Self.debugview = Self.generate_debug_view($('#page'));
    /// DEBUG //////////////////////////////////////////////
    var parser = document.createElement('A');

    Self.generate_user_commentview($('#page'));

    parser.href = document.location;
    
    // Add anchors to each article header

    // BUILD TOC
    // Iterate through each H1 Article header
    jQuery.each($("div.entry-content").find('H1'),function(h1_index,h1){
      Self.build_toc_from_articles(h1_index,h1);
    });

    // The placeholder image serves no function
    $('div.post-thumbnail').first().find('img.wp-post-image').remove();

    // Since the site will only be serving the Constitution for a while,
    // best include the privacy policy link in the link box.
    var privacy_policy = $(document.createElement('EM'))
      .css({'margin-top': '10px'}).append($(document.createElement('A'))
      .addClass('aux-link')
      .attr('href','/privacy-policy/')
      .attr('target','_target')
      .text('Privacy Policy'))
      ;
    var representative_map = $(document.createElement('EM'))
      .css({'margin-top': '10px'}).append($(document.createElement('A'))
      .addClass('aux-link')
      .attr('href','/representatives-by-map/')
      .attr('target','_target')
      .text('Representatives'))
      ;

    $('#toc').append(privacy_policy);
    $('#toc').append(document.createElement('BR'));
    $('#toc').append(representative_map);

    if ( this.enable_html_extractor > 0 ) {
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
      Self.prepare_table_presentation(Self,index,table);
    });

    if ( this.enable_html_extractor > 0 ) 
    {//{{{
      // Clean up the HTML extractor's cells.  Replace TD contents with text.
      jQuery.each($('div#html-extractor').find('table'),function(index,table){
        jQuery.each($(table).find('TR'), function(tr_index, tr) {
          jQuery.each($(tr).children(), function(td_index,td){
            var content = document.createTextNode($(td).text());
            $(td).text($(content).text());
          });
        });
      });
    }//}}}

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

      // Facebook Preview fix: Jump to real page after a few seconds.
      jQuery.each($('#maindoc-jump-link'),function(index,jumplink){
        Self.unconditional_maindoc_reload(jumplink);
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
      Self.raise_toc_on_mousemove(event);
    });

    $('div#content').click(function(event){
      Self.raise_toc_on_mousemove(event);
    });

    // Adjust menu size (position is fixed) 
    $(window).scroll(function(event){
      Self.handle_window_scroll(event);
    });

    setTimeout(function(){
      // If the parser was given an existing anchor, go to it, after this initialization is done..
      jQuery.each($('#page').find('#a-'+parser.hash.replace(/^\#/,'')).first(),function(target_index, target_anchor){
        $(target_anchor).click();
        $(target_anchor).parentsUntil('TD').parents().first().click();
      });
    },400);

  }/*}}}*/

}//}}}


$(document).ready(function(){
  var lecturer = new Lecturer();
  lecturer.jq_document_ready();
});

jQuery(document).ready(function() {

  // Copyright 2018, Antonio Victor Andrada Hilario
  // avahilario@gmail.com
  // I am releasing this to the public domain.
  // 6 July 2018
  // #StopTheKillings

  $ = jQuery;
  var preamble_y = 0;
  var toc = {};
  var scroll_y = 0;
  var preamble_offset = 0;
  var tocdiv = document.createElement('DIV');
  var timer_id = 0;
  var toc_aligner = function(){
    clearTimeout(timer_id);
    $('#toc').css({'top': scroll_y+'px'});
    setTimeout(100,toc_aligner);
  };
  // Add anchors to each article header

  $(window).scroll(function(event){
    scroll_y = $(window).scrollTop();
    $('#toc').css({
      'top'        : scroll_y+'px',
      'max-height' : ($(window).innerHeight()-40)+'px'
    });
  });

  // Generate empty TOC div
  $(tocdiv).attr('id','toc')
    .css({
      'width'            : '180px',
      'max-height'       : ($(window).innerHeight()-40)+'px',
      'background-color' : '#FFF',
      'padding'          : '5px',
      'overflow'         : 'scroll',
      'overflow-x'       : 'hidden',
      'display'          : 'block',
      'position'         : 'absolute',
      'top'              : $(window).scrollTop()+'px',
      'left'             : '-10px',
      'border'           : 'solid 3px #DDD'
    })
    .text("");
  $('div.site-inner').append(tocdiv);

  var parser = document.createElement('A');
  parser.href = document.location;

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
        'white-space': 'nowrap'
      })
      .css(link_color)
      .attr('href','#'+slug)
      .text(article_text.replace(/Article ([a-z]{1,})/gi,''))
      .click(function(event){
        var self = this;
        var anchor_id = $(self).attr('href').replace(/#/,'a-');
        event.preventDefault();
        event.stopPropagation();
        document.title = $(self).text();
        $('html, body').animate({
          scrollTop: $('#'+anchor_id).offset().top.toFixed(0)
        });
        document.location = $('#'+anchor_id).attr('href');
      });
    // Derive anchor target
    $(anchor).attr('name',slug)
      .attr('id','a-'+slug)
      .css({
        'text-decoration' : 'none',
        'color'           : 'black',
        'padding-top'     : '10px',
        'box-shadow'      : '0 0 0 0' 
      })
      .attr('href','/#'+slug)
      .append(article_text)
      .addClass('toc-anchor');
    // Add ID attribute to this H1 tag, replace text, and add ID to table body. 
    $(this)
      .empty()
      .append(anchor)
      .attr('id','h-'+slug)
      ;
    var column_index = 0;
    $('#h-'+slug+' ~ table').first()
      .attr('id',slug)
      // At this point, we can alter the "Section X" text inside tables with id {slug},
      // and turn them into anchors.
      .find('TD').each(function(tindex){
        column_index++;
        $(this).find('STRONG').each(function(sindex){
          var strong = $(this);
          var column_specifier = column_index & 1;
          var section_text = $(strong).text();
          var section_num = section_text.replace(/^section ([0-9a-z]{1,}).*/i,"$1");
          var section_anchor = $(document.createElement('A'))
            .attr('name','#'+slug+'-'+section_num+'-'+column_specifier)
            .attr('href','#'+slug+'-'+section_num+'-'+column_specifier)
            .css({
              'text-decoration' : 'none',
              'color'           : 'black',
              'padding-top'     : '10px',
              'box-shadow'      : '0 0 0 0' 
            })
            .addClass('toc-section')
            .text('SECTION '+section_num+'.');
          $(strong).empty().append(section_anchor);
        });
      });

    // Modify table cells: Mark cells by column (1987 Consti and Draft Provisions)
    // Replace references to articles ("in Article X...") with links to local anchors.
    // Append Article link (and an explicit line break) to the TOC container 
    $(tocdiv).append(link);
    $(tocdiv).append(document.createElement('BR'));
  });


  $('#toc').css({'top': $(window).scrollTop()+'px'});

  // If the parser was given an existing anchor, go to it.
  $('#toc').find('a[id=link-'+parser.hash.replace(/^#/,'')+']').click();

  // Since the site will only be serving the Constitution for a while,
  // best include the privacy policy link in the link box.
  var privacy_policy = $(document.createElement('EM')).css({'margin-top': '10px'}).append($(document.createElement('A'))
    .css({'color' : '#AAA'})
    .attr('href','/privacy-policy/')
    .attr('target','_target')
    .text('Privacy Policy'))
    ;
  $('#toc').append(privacy_policy);


});

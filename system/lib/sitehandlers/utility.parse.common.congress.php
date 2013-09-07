<?php

/*
 * Class CongressCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressCommonParseUtility extends LegislationCommonParseUtility {/*{{{*/
  
  var $temporary_document_container = array();
  var $link_title_classif_map = array();
  var $span_content_prefix_map = array();
  var $aux_information_links = array();
  var $member_contact_details = NULL;
  var $representative_name = NULL;
  var $current_member_classification = NULL;

  function __construct() {
    parent::__construct();
  }

   /** BEGIN 16th Congress (additional methods) **/

 function ru_header_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_header_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_header_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      $id    = array_element($this->current_tag['attrs'],'ID');
      $class = array_element($this->current_tag['attrs'],'CLASS');
      if ( 1 == preg_match('@(' . join('|',array(
        '__dummy__',
      )) . ')@', $class )) $skip = TRUE;
      if ( array_key_exists($id,array_flip(array(
        'pageheader',
      )))) $skip = TRUE;
    }
    if (is_array($this->current_tag) && !$skip ) {
      $this->stack_to_containers();
      if ( array_key_exists('cdata', $this->current_tag) ) {
        $this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
      }
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

 function ru_section_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_section_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_section_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      $id    = array_element($this->current_tag['attrs'],'ID');
      $class = array_element($this->current_tag['attrs'],'CLASS');
      if ( 1 == preg_match('@(' . join('|',array(
        'sitenav',
      )) . ')@', $class )) $skip = TRUE;
      if ( array_key_exists($id,array_flip(array(
        'sidebar',
        'pagetop',
      )))) $skip = TRUE;
    }
    if (is_array($this->current_tag) && !$skip ) {
      $this->stack_to_containers();
      if ( array_key_exists('cdata', $this->current_tag) ) {
        $this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
      }
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_nav_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_nav_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_nav_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      $id    = array_element($this->current_tag['attrs'],'ID');
      $class = array_element($this->current_tag['attrs'],'CLASS');
      if ( 1 == preg_match('@(' . join('|',array(
        'sitenav',
      )) . ')@', $class )) $skip = TRUE;
      if ( array_key_exists($id,array_flip(array(
        'sitenav',
      )))) $skip = TRUE;
    }
    if (is_array($this->current_tag) && !$skip ) {
      $this->stack_to_containers();
      if ( array_key_exists('cdata', $this->current_tag) ) {
        $this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
      }
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  /** END 16th Congress (additional methods) **/

  function ru_head_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_head_close(& $parser, $tag) {/*{{{*/
    array_pop($this->container_stack);
    return FALSE;
  }/*}}}*/

  function ru_body_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_body_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_body_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->cdata_cleanup();
    $this->push_tagstack();
    $cdata_lines = array(
      'text' => $this->current_tag['cdata'],
      'seq' => array_element($this->current_tag['attrs'],'seq')
    );
    if (0 < strlen(trim(join('',$cdata_lines['text']))))
    $this->add_to_container_stack($cdata_lines);
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    // FIXME:  Figure out a better way to filter Congress site DIV tags
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      $id    = array_element($this->current_tag['attrs'],'ID');
      $class = array_element($this->current_tag['attrs'],'CLASS');
      if ( 1 == preg_match('@(' . join('|',array(
        'footer_content',
        'subnav',
        'clearer',
        'footer',
        'footer_content',
        'main_right',
        'breadcrumb',
      )) . ')@', $class )) $skip = TRUE;
      if ( array_key_exists($id,array_flip(array(
        'nav_bottom',
      )))) $skip = TRUE;
    }
    if (is_array($this->current_tag) && !$skip ) {
      $this->stack_to_containers();
      if ( array_key_exists('cdata', $this->current_tag) ) {
        $this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
      }
    }
    $this->push_tagstack();
    
    return !$skip;
  }/*}}}*/

  function ru_br_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_close(& $parser, $tag) {/*{{{*/
    $me     = $this->pop_tagstack();
    $parent = $this->pop_tagstack();
    if ( array_key_exists('cdata', $parent) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
      $parent['cdata'][] = "\n[BR]";
    }
    $this->push_tagstack($parent);
    $this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    $this->pop_tagstack();
    $this->update_current_tag_url('SRC');
    // Add capability to cache images as well
    $this->current_tag['attrs']['HREF'] = $this->current_tag['attrs']['SRC'];
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
    } else {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( 1 == preg_match('@(nav_logo|faded)@',$this->current_tag['attrs']['CLASS']) ) $skip = TRUE;
    if ( !$skip ) {
      $image = array(
        'image' => $this->current_tag['attrs']['SRC'],
        'seq'   => $this->current_tag['attrs']['seq'],
      );
      $this->add_to_container_stack($image);
    } else {
      // $this->recursive_dump($this->current_tag,'(warning)');
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
      $this->current_tag['attrs']['ID'] = UrlModel::get_url_hash(array_element($this->current_tag['attrs'],'HREF'));
    } else {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      if ( FALSE == preg_match('@(legiscope-remote)@', $this->current_tag['attrs']['CLASS']) ) {
        $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
      }
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . " --- {$this->current_tag['tag']} " . array_element($this->current_tag['attrs'],'HREF') );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $link_data = $this->collapse_current_tag_link_data();
    $link_data['title'] = array_element($this->current_tag['attrs'],'TITLE');
    // Links to legislation metadata are displayed using Javascript popup
    // windows, whose source URL is embedded in a click event handler.
    // We subvert this by accessing the links directly: We intercept the
    // link event ourselves, replacing the link HREF attribute with the
    // event handler source URL.
    $onclick_event = array_element($this->current_tag['attrs'],'ONCLICK');
    if ( property_exists($this, 'current_member_classification') && !is_null($this->current_member_classification) ) {
      $link_data['classification'] = $this->current_member_classification;
    }
    if ( !is_null($onclick_event) ) {
      $event_url_match = array();
      $link_data['onclick'] = $onclick_event;
      $link_regex = '@([a-z_]*)\(([\'"]?([^\'"]*)[\'"]?)([,]*.*)\)@i';
      /* The regex above yields, given input
       * "pop_Window('../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15','param2','param3')";
       *Array (
         [0] => pop_Window('../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15','param2','param3')
         [1] => pop_Window
         [2] => '../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15'
         [3] => ../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15
         [4] => ,'param2','param3'
       )
      */
      if ( 1 == preg_match($link_regex,$onclick_event,$event_url_match) ) {
        $fixurl = array('url' => array_element($event_url_match,3));
        $link_data['onclick-param'] = UrlModel::normalize_url($this->page_url_parts, $fixurl);
        $this->current_tag['attrs']['onclick-param-url'] = $link_data['onclick-param'];
      }
      unset($this->current_tag['attrs']['ONCLICK']);
    }
    if ( !empty($link_data['url']) ) {
      if ( $this->add_to_container_stack($link_data) ) {
        // See system/lib/utility.rawparse.php add_to_container_stack(& $link_data, $target_tag);
        // The newly-added link is found at the top $this->container_stack[$found_index]['children'];
        $this->current_tag['found_index'] = array_element($link_data,'found_index','--');
        $this->current_tag['sethash']     = array_element($link_data,'sethash','--');
      }
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => join('', $this->current_tag['cdata']),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    if (0 < strlen(trim($paragraph['text']))) $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/


  /** Parser postprocessing (16th Congress) **/

  function emit_document_entries_js() {/*{{{*/
    // Script fragment used to generate dynamic lists of documents 
    // This default displays House Bill information, and must be
    // overridden for other document types.
    return <<<EOJ

function emit_document_entries(entries) {

  for ( var p in entries ) {
    var entry = entries[p];
    var links = entry && entry.links ? entry.links : {};
    var representative = entry && entry.representative ? entry.representative : null;
    var committee = entry && entry.committee ? entry.committee : null;
    var linktype_map = {
      'url_history' : 'History',
      'url_engrossed' : 'Engrossed',
      'filed' : 'Filed' 
    };

    jQuery('div[id='+entry.sn+']').children().remove();

    var link_container = jQuery(document.createElement('SPAN'))
      .addClass('republic-act-heading')
      .addClass('clear-both')
			.append(jQuery(document.createElement('B')).append(entry.sn))
			.append(': ')
			;

    for ( var t in links ) {
      var l = links[t];
			var cl = entry && entry.linkstate ? entry.linkstate[t] : 'uncached';
      jQuery(link_container)
        .append(jQuery(document.createElement('A'))
          .attr('href',l)
          .addClass('legiscope-remote')
					.addClass(cl)
          .append(linktype_map[t])
        )
				.append('&nbsp;')
				;
    }

    jQuery('#listing').append(
      jQuery(document.createElement('DIV'))
        .addClass('bill-container')
        .addClass('clear-both')
        .attr('id',entry.sn)
        .append(
          jQuery(document.createElement('HR'))
        )
        .append(link_container)
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-desc')
            .addClass('clear-both')
            .html(entry.description)
        )
      );

    
    for ( var m in entry.meta ) {
      var v = entry.meta[m];
      jQuery('div[id='+entry.sn+']')
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-meta')
            .addClass('clear-both')
            .append(jQuery(document.createElement('B')).html(m+": "))
            .append(v)
        )
    }

    jQuery('div[id='+entry.sn+']')
      .append(committee
        ? jQuery(document.createElement('SPAN'))
          .addClass('republic-act-meta')
          .addClass('clear-both')
          .append(jQuery(document.createElement('B')).html('Committee: '))
          .append(jQuery(document.createElement('A'))
            .attr('href',committee.url)
            .addClass('legiscope-remote')
            .html(committee.committee_name)
          )
        : entry.meta 
            ? jQuery(entry.meta).prop('main-committee') 
            : ''
      )
      .append(representative
        ? jQuery(document.createElement('SPAN'))
            .addClass('republic-act-meta')
            .addClass('clear-both')
            .append(jQuery(document.createElement('B')).html('Principal Author: '))
            .attr('title',(representative && representative.url) ? '' : 'Representative not recorded in database.')
            .append( (representative && representative.url)
              ? jQuery(document.createElement('A'))
                .attr('href',representative.url)
                .addClass('legiscope-remote')
                .html(representative.fullname)
              : ( entry.meta
                  ? jQuery(entry.meta).prop('principal-author')
                  : ''
                )
            )
          : ''
      );

  }
}


EOJ
  ;
  }/*}}}*/

  function seek_postparse_preprocess(& $parser, & $pagecontent, & $urlmodel, $document_type ) {/*{{{*/

    $debug_method = $this->extant_property_value('debug_method');
		$debug_method = FALSE;

		if ( !$urlmodel->in_database() || !(0 < strlen($urlmodel->get_url())) ) {
			return NULL;
		}

		extract($urlmodel->load_url_from_metalink_data(
			$this->filter_post('metalink')
		)); // $congress_tag, $fake_url_str, $have_parsed_data

		if ( $debug_method ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) Source URL: " . $urlmodel->get_urlhash() . " " . $urlmodel->get_url() );
			$this->syslog( __FUNCTION__, __LINE__, "(marker)        Age: " . intval(time() - $urlmodel->get_last_fetch()) / 3600 );
		}

    $this->start_offset = $this->filter_post('parse_offset',0);
    $this->parse_limit  = $this->filter_post('parse_limit',20);

    if ( !$have_parsed_data || $parser->update_existing ) {/*{{{*/// Stow raw parsed data 

      if ( $debug_method ) {/*{{{*/
        $state = $have_parsed_data ? "existing" : "de novo";
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Regenerating {$state}: " . $urlmodel->get_url() );
      }/*}}}*/

      $this->start_offset = NULL;
      $this->parse_limit  = NULL;

      $this->
        enable_filtered_doc(FALSE)-> // Conserve memory, do not store filtered doc.
        initialize_bill_parser($urlmodel)-> 
        set_parent_url($urlmodel->get_url())->
        parse_html(
          $urlmodel->get_pagecontent(),
          $urlmodel->get_response_header()
        );

      if ( $debug_method ) {/*{{{*/
        if ( is_null($this->parse_limit) ) {
					$this->syslog(__FUNCTION__, __LINE__, "(marker) Parsed bills: " . count($this->parsed_bills));
				} else {
          $this->recursive_dump($this->parsed_bills,"(marker) -- F --");
        }
        $this->syslog(__FUNCTION__, __LINE__, "(marker) Containers: " . count($this->containers_r()));
        $this->recursive_dump($this->containers_r(),"(marker) -- c --");
      }/*}}}*/

      if ( is_null($congress_tag) ) {/*{{{*/// Extract Congress number from page filter form
				// 16th Congress:  We use the Congress selector form to determine
				// congress_tag, if it does not yet exist
				$this->filter_nested_array($this->containers_r(),
					// 'children,attrs[tagname*=form][id*=form1]',0 // 15th Congress
					'children,attrs[tagname*=form][attrs:ACTION*='.$document_type.'$|i]',0 // 16th Congress
				);
				$form_data = nonempty_array_element($this->containers_r(),0);
				// Locate the SINGLE form that contains Congress selection items.

        // 16th Congress CMS
        // Find Congress number from {selected} OPTION tag
        $selected_congress = nonempty_array_element($form_data,'children');
        if ( $debug_method ) $this->recursive_dump($selected_congress,"(marker) -- RAW --");
        $this->filter_nested_array($selected_congress,'children[tagname*=select][id*=cstCombo]',0);
        // Filter for OPTION element having selected := 1
        $selected_congress = nonempty_array_element($selected_congress,0);
        if ( $debug_method ) $this->recursive_dump($selected_congress,"(marker) -- CST --");
        $this->filter_nested_array($selected_congress,'value[selected=1]',0);
        if ( $debug_method ) $this->recursive_dump($selected_congress,"(marker) -- FIN --");
        // Finally, assign
        $congress_tag = nonempty_array_element($selected_congress,0);
        if ( is_null($congress_tag) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(error)  Unable to determine Congress number from current page");
          return NULL;
        }
      }/*}}}*/

			$this->syslog(__FUNCTION__, __LINE__, "(marker) Congress: {$congress_tag}");

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($form_data,"(marker) -- C --");
      }/*}}}*/

      $this->parsed_bills = array_reverse($this->parsed_bills);

      $this->container_buffer = array(
        'partition_width' => $this->bill_set_size,
        'entries'         => $this->bill_head_entries,
        'parsed_bills'    => $this->parsed_bills,
        'congress_tag'    => $congress_tag,
        'form'            => array_merge(
          array('action' => array_element(nonempty_array_element($form_data,'attrs'),'ACTION')),
          $this->extract_form_controls(nonempty_array_element($form_data,'children'))
        )
      );

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($this->container_buffer['form'],"(marker) -- L --");
      }/*}}}*/

			// Place preparsed data into a unique ContentDocumentModel record,
			// one per unique UrlModel, that functions as a scratch database 
			// entry. Note that if the user sends a simple GET request (so that
			// $metalink is NULL), the ContentDocumentModel is still generated.
      $id = $urlmodel->add_preparsed_record(
				$this->container_buffer,
				$this->filter_post('metalink')
			);

      $error = $urlmodel->error();

      if ( !empty($error) || $debug_method ) {/*{{{*/
        $state = !empty($error) ? "Error {$error} stowing" : ($have_parsed_data ? "Reparsed" : "Stored");
        $this->syslog( __FUNCTION__, __LINE__, "(marker) {$state} {$this->bill_head_entries} entries: " . $urlmodel->get_url() );
      }/*}}}*/

			$test_url = NULL;
			unset($test_url);
    }/*}}}*/

    $offset = $this->start_offset; // Ordinal index (resolves to a single bill as though the partitions are unrolled) 
    $span   = intval($this->parse_limit); // Elements (bills) to return

    if ( 0 == $span ) {
      $span = 20;
      $parser->json_reply['clear'] = true;
    }

    $this->reset(TRUE,TRUE); // Eliminate XML parser, clear containers

    $this->container_buffer = json_decode($urlmodel->get_pagecontent(),TRUE);

    $partition_width  = nonempty_array_element($this->container_buffer,'partition_width');
    $entries          = nonempty_array_element($this->container_buffer,'entries');

    if ( is_null($congress_tag) ) $congress_tag = nonempty_array_element($this->container_buffer,'congress_tag');

    $partitions       = count($this->container_buffer['parsed_bills']);
    $partition_start  = floor($offset / $partition_width);
    $partition_offset = $offset % $partition_width;
    $partition_end    = floor(($offset + $span) / $partition_width);

    if ( $debug_method )
    $this->syslog(__FUNCTION__,__LINE__, "(marker) Getting n = {$span} from {$partition_start}.{$partition_offset} - {$partition_end}");

    $final_list = array();
    do {/*{{{*/
      $index = min($partition_start,$partitions-1);
      foreach ( array_reverse($this->container_buffer['parsed_bills'][$index]) as $entry ) {/*{{{*/
        if ( $partition_offset > 0 ) {/*{{{*/
          if ( $partition_start + 4 > $partitions )
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$entry['sn']}   {$partition_start}/{$partitions} SKIP");
          $partition_offset--;
          continue;
        }/*}}}*/
        $entry['description'] = ucfirst(strtolower($entry['description']));
        $final_list[] = $entry;
        if ( $partition_start + 4 > $partitions )
          $this->syslog(__FUNCTION__,__LINE__,"(marker) {$entry['sn']}   {$partition_start}/{$partitions}");
        if ( count($final_list) >= $span ) break;
      }/*}}}*/
      $partition_offset = 0;
      $partition_start++;
    }/*}}}*/
    while ((count($final_list) < $span) && ($partition_start < $partitions));

    if ( $debug_method )
    $this->syslog(__FUNCTION__,__LINE__, "(marker) Returning Congress {$congress_tag} " . count($final_list) . " entries from source with " . count($this->container_buffer['parsed_bills']) . " partitions");

    $this->container_buffer['parsed_bills'] = $final_list; 

    $parser->json_reply['state'] = ( $partition_end >= $partitions ) ? '0' : '1';

    return $congress_tag;

  }/*}}}*/

  function add_document_urlcaching_cssclass(& $pregenerated_list, $key = NULL, $depth = 0) {
    if ( $depth == 0 ) {
      $this->url_state_cache = array();
      $depth++;
      array_walk($pregenerated_list, create_function(
        '& $a, $k, $s', '$s->add_document_urlcaching_cssclass($a,$k,1);'
      ),$this);

      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Done with walk --");
      $updatable  = array();
      $chunk_size = 10;
      $urlmodel   = new UrlModel();
      while ( 0 < count($this->url_state_cache) ) {/*{{{*/
        $elements = array();
        while ( ( 0 < count($this->url_state_cache) ) && ( count($elements) < $chunk_size ) ) {/*{{{*/
          extract( array_shift($this->url_state_cache) );
          $elements[$urlhash] = array(
            'urlkind' => $urlkind,
            'sn'      => $sn,
            'cached'  => FALSE,
            'class'   => 'uncached',
          ); 
        }/*}}}*/
        $url = NULL;
        $urlmodel->
          where(array('AND' => array(
            'urlhash' => array_keys($elements),
          )))->
          recordfetch_setup();
        while ( $urlmodel->recordfetch($url,TRUE) ) {
          $urlhash = $url['urlhash'];
          $elements[$urlhash]['cached'] = TRUE;
          $elements[$urlhash]['class'] = $urlmodel->get_logseconds_css();
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Got URL {$url['url']} {$elements[$urlhash]['class']} --");
        }
        foreach ( $elements as $urlhash => $urlstate ) {
          extract($urlstate);
          $pregenerated_list[$sn]['linkstate'][$urlkind] = $class;
        }
        $reordered = $pregenerated_list[$sn]['links'];
        $pregenerated_list[$sn]['links'] = array_filter(array(
          'filed'             => nonempty_array_element($reordered,'filed'),
          'url_engrossed'     => nonempty_array_element($reordered,'url_engrossed'),
          'url_history'       => nonempty_array_element($reordered,'url_history'),
        ));

      }/*}}}*/
      unset($this->url_state_cache);
      return;
    }
    if ( $depth == 1 ) {
      $url_set = nonempty_array_element($pregenerated_list,'links',array());
      foreach ( $url_set as $url_kind => $url ) {
        $urlhash = UrlModel::get_url_hash($url);
        $this->url_state_cache[] = array(
          'urlhash' => $urlhash,
          'urlkind' => $url_kind,
          'sn' => $key,
        ); 
      } 
      return;
    }
    return;
  }

  function seek_postparse(& $parser, & $pagecontent, & $urlmodel, & $document_model, $document_type ) {

    // Partition large list into multiple processable chunks, transmit
    // these in bulk to the client browser as JSON fragments.

    $debug_method = $this->extant_property_value('debug_method');

    if ( $debug_method ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() . ". Length: " . $urlmodel->get_content_length() . ". Memory load: " . memory_get_usage(TRUE) );
      $this->recursive_dump($_POST,"(marker) --");
    }/*}}}*/

    $urlmodel->ensure_custom_parse();

    $parser->reset(TRUE)->structure_reinit();

    $pagecontent = NULL;

    // Perform actual parse operation

    $congress_tag = $this->seek_postparse_preprocess($parser,$pagecontent,$urlmodel,$document_type);

		if ( is_null($congress_tag) ) {/*{{{*/
			$url = $urlmodel->get_url();
			$url = 0 < strlen($url) ? "URL '{$url}'" : "requested link";
			$json_reply['retainoriginal' ] = TRUE;
			$json_reply['subcontent'] = <<<EOH
<div class="error">
<p>Unable to parse <span>{$url}</span>. Please check that the host is accessible, or enable <b>Seek</b> to force an update from the live site.</span
</div>

EOH;
			$pagecontent = NULL;
			return;
		}/*}}}*/

    $urlmodel->set_content(NULL)->set_id(NULL);

    $pregenerated_list = NULL;
    $emit_frame        = FALSE;
    $caching_method    = 'cache_parsed_records';

		if ( method_exists($document_model, $caching_method) ) { 
			// This method is invoked to cache new records found on the original site catalog page.
			// UPDATES to *individual* records are triggered from the 
			// local curator's catalog (by traversing catalog links).
			// This method should be implemented once for each document type;
			// a default implementation should be provided which returns
			// a nonfatal error (since we want the catalog to display regardless
			// of the result of the caching operation).
			//
			// The method also transforms the parsed data cache.
			$document_model->$caching_method(
				$this->container_buffer['parsed_bills'],
				$congress_tag,
				$parser->from_network
			);
		}
		else {
			$document_type = get_class($document_model);
			$this->syslog(__FUNCTION__,__LINE__,"(warning) Missing {$document_type}::{$caching_method}");
		}

    if ( 'fetch' == $this->filter_post('catalog') ) {
      $parser->json_reply['catalog'] = nonempty_array_element($this->container_buffer,'parsed_bills');
      if ( $debug_method ) $this->recursive_dump(nonempty_array_element($this->container_buffer,'parsed_bills'),"(marker) -- A --");
      $parser->json_reply['division'] = 'A';
		}
	 	else {
      $pregenerated_list = nonempty_array_element($this->container_buffer,'parsed_bills');
      $this->add_document_urlcaching_cssclass($pregenerated_list);
      if ( TRUE || $debug_method ) $this->recursive_dump($pregenerated_list,"(marker) -- B --");
      $pregenerated_list = addslashes(LegiscopeBase::safe_json_encode($pregenerated_list)); 
      $emit_frame = TRUE;
      $parser->json_reply['division'] = 'B';
    }

    // Store records not yet recorded in *DocumentModel backing store.
    // Generate POST links

    $actions        = nonempty_array_element($this->container_buffer,'form');
    $action         = nonempty_array_element($actions,'action');
    $trigger_pull   = NULL;
    $generated_link = array();

    if ( $debug_method ) $this->recursive_dump(nonempty_array_element($actions,'form_controls'),"(marker) -- - --");

    // This code depends on successful extraction of form_controls in
    // CongressHbListParseUtility
    $form_controls = nonempty_array_element($actions,'form_controls',nonempty_array_element($actions,'userset',array()));

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -+-+-+-+-+-- {$congress_tag}");
      $this->recursive_dump($actions,"(marker) -+-+-+-+-+--");
    }/*}}}*/

    foreach ( $form_controls as $k => $v ) {/*{{{*/
      $v = array_keys($v);
      foreach ( $v as $val ) {
        $link_class_selector = array('fauxpost','legiscope-content-tab');
        $link_class_selector = join(' ', $link_class_selector);
        $control_set = array(
					'_LEGISCOPE_'    => array(
						'add_trailing_queryslash' => TRUE,
						'skip_get'     => TRUE,
					),
          $k               => $val,
          'submitcongress' => 'Go',
        );
        $metalink_source = UrlModel::create_metalink("{$val}", $action, $control_set, $link_class_selector, TRUE);

				if ( $debug_method ) {
					$this->recursive_dump($metalink_source,"(marker) --+-+-+-+-+- {$val}");
					$this->recursive_dump($control_set,"(marker) --+-----+-+- {$val}");
				}

        extract($metalink_source);
        $generated_link[$hash] = $metalink;
        if ( is_null($congress_tag) ) $congress_tag = $val;
        if ( $congress_tag == $val ) $trigger_pull = "switch-{$hash}";
      }
    }/*}}}*/

		array_walk($generated_link,create_function(
			'& $a, $k', '$a = "<li>{$a}</li>";'));
    $generated_link = '<ul class="tab-links">' . join("", $generated_link) . '</ul>';
    $entries        = array_element($this->container_buffer,'entries');
    $bills          = NULL;
    $parse_offset   = intval($this->filter_post('parse_offset',0));
    $parse_limit    = intval($this->filter_post('parse_limit',20));

    $parser->json_reply['parse_offset'] = $parse_offset + $parse_limit;

    $emit_frame     = $emit_frame || ($parse_offset == 0);

    // FIXME: Use per-method session store
    $last_fetch     = $this->filter_post('last_fetch', $parser->from_network ? time() : 'null');

    if ( $debug_method || $emit_frame ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Offset: {$parse_offset}. " . ($emit_frame ? "Emitting" : "Not emitting") . " frame");

    $document_generator = $this->emit_document_entries_js();
    // Send markup container and script 

    if ( $emit_frame ) $pagecontent = str_replace("\r","\n",<<<EOH

<div class="flattened-post-forms" id="system-stats">
  Congress {$congress_tag}: {$entries} {$generated_link}
  <input class="reset-cached-links" type="button" value="Clear"> 
  <input class="reset-cached-links" type="button" value="Reset"> 
</div>

<div id="listing"></div>

<script type="text/javascript">

var parse_offset = 0;
var parse_limit = {$parse_limit};
var pregenerated_list = jQuery.parseJSON("{$pregenerated_list}");

{$document_generator}

function pull() {
  var trigger_pull = '{$trigger_pull}';
  if ( !(trigger_pull.length > 0) ) return;
  var active = jQuery('[id='+trigger_pull+']').attr('id').replace(/^switch-/,'content-');
  var linkurl = jQuery('[id='+trigger_pull+']').attr('href');
  jQuery.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : { url : linkurl, catalog : 'fetch', last_fetch : {$last_fetch}, parse_offset : parse_offset, parse_limit : parse_limit, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked'), fr: true, metalink : jQuery('#'+active).html() },
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
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      if ( data && data.state ) {
        if ( 0 == parseInt(data.state) ) {
          jQuery('#spider').prop('checked',null);
        }  
      }
      parse_offset = ( data && data.parse_offset ) ? data.parse_offset : 0;
      if ( data && data.catalog ) emit_document_entries(data.catalog);
      jQuery('#seek').prop('checked',null);
      if ( jQuery('#spider').prop('checked') ) {
        setTimeout((function(){ pull(); }),500);
      }
    })
  });
}

jQuery(document).ready(function(){
  jQuery('#subcontent').children().remove();
  if ( !jQuery('#spider').prop('checked') ) {
    emit_document_entries(pregenerated_list);
    return; 
  }
  pull();
});
</script>

EOH
);
    ///////////////////////////////////////////////////////////////////////////////////////////////////


  }


  /** Rejected tags **/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/


  // Journal parser callbacks

  function session_select_to_session_data(& $session_select, $session) {/*{{{*/
    if (1) {
      $session_item = $session_select;
      $this->filter_nested_array($session_item,'metalink[optval*=' . $session . ']',0);
      return array_filter($session_item);
    }
    return array_filter(array_map(create_function(
      '$a', 'return $a["metalink"];'
    ), $session_select));
  }/*}}}*/

  function session_select_to_linktext(& $session_select, $session) {/*{{{*/
    $session_item = $session_select;
    $this->filter_nested_array($session_item,'#[optval*=' . $session . ']',0);
    return array_filter(array_map(create_function(
      '$a', 'return str_replace($a["linktext"],$a["optval"],$a["markup"]);'
    ), $session_item));
  }/*}}}*/

  // Committee / Representative parser support methods

  function generate_related_source_links(& $parser, $tag) {/*{{{*/
    // Called 
    // Match classification text against 'title' attribute, then 'text'
    $this->current_tag();
    $cdata = join('', nonempty_array_element($this->current_tag,'cdata',array()));
    $classification = nonempty_array_element($this->current_tag['attrs'],'TITLE',$cdata);

    if ( is_null(array_element($this->current_tag,'sethash')) ) {

    } else if ( 1 == preg_match($this->link_title_classif_regex, $classification ) ) {
      // If the 'title' attribute matches telltale strings (above), we try to
      // match it's content to one of three categories - bill, representative, or
      // committee - and label the URL with that classification.
      $classification = preg_replace(
        array_keys($this->link_title_classif_map),
        array_values($this->link_title_classif_map),
        $classification
      );
      $key = NULL;
      $add_to_aux_information_links = FALSE; 
      switch ( $classification ) {/*{{{*/
        case 'history'        : $key = 'onclick-param'; break;
        case 'bill-url'       : $key = 'url'; break;
        case 'bill-engrossed' : $key = 'url'; break;
        default: if ( 0 < strlen($classification) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Unhandled link class {$classification}");
          if ($this->debug_tags) {
            $cstack_top = array_pop($this->container_stack);
            array_push($this->container_stack, $cstack_top);
            $this->recursive_dump($this->current_tag,"(marker) --- ");
            $this->recursive_dump($cstack_top,"(marker) -++ ");
          }
          $key = 'url';
          $add_to_aux_information_links = TRUE; 
        }
        break;
      }/*}}}*/
      $add_to_tempdoc = NULL;
      if ( is_null(($found_index = array_element($this->current_tag,'found_index'))) ) {
        $cstack_top = array_pop($this->container_stack);
        if ( is_array(($lasttag = array_element($cstack_top,'children'))) && (0 < count($lasttag)) ) {
          $lasttag = array_pop($cstack_top['children']);
          $lasttag['tag-class'] = $classification;
          if ( !is_null($key) && array_key_exists($key, $lasttag) ) {
            $add_to_tempdoc = array($classification => array_element($lasttag,$key,'--'));
          }
          if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - Tag class '{$classification}'" );
          array_push($cstack_top['children'],$lasttag);
          if ( $add_to_aux_information_links ) $this->aux_information_links[] = $lasttag;
        }
        array_push($this->container_stack, $cstack_top);
      } else {
        $link_data = array_pop($this->container_stack[$found_index]['children']);
        $link_data['tag-class'] = $classification;
        if ( !is_null($key) && array_key_exists($key, $link_data) ) {
          $add_to_tempdoc = array($classification => array_element($link_data,$key,'--'));
        }
        if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - Tag class '{$classification}'" );
        array_push($this->container_stack[$found_index]['children'], $link_data);
      }
      if ( !is_null($add_to_tempdoc) ) {
        $this->temporary_document_container = array_merge(
          $this->temporary_document_container,
          $add_to_tempdoc
        );
      }
    }
    return TRUE;
  }/*}}}*/

  protected function initialize_related_source_links_parse() {/*{{{*/
    $this->temporary_document_container = array();
    $this->link_title_classif_map = array(
      'about the committee'           => 'committee',
      'text of bill as filed'         => 'bill-url',
      'text of bill'                  => 'bill-engrossed',
      'history of bill'               => 'history',
      'about our member'              => 'representative',
      // 16th Congress sidebar links - Representatives
      'committee membership of'       => 'committee-membership',
      'house measures authored'       => 'authorship',
      'house measures co-authored'    => 'joint-author',
      'related articles and doc'      => 'related',
      // 16th Congress sidebar links - Committees
      'committee members'             => 'member_list',
      'bills referred'                => 'bills_referred',
      'bills sponsored'               => 'bills_cosponsored',
      'schedule of meetings'          => 'committee_meetings',
      'related article and documents' => 'comm_related',
    );
    $this->tag_class_map = array(
      'committee-membership' => 'Committee Membership',
      'authorship'           => 'Measures'            ,
      'joint-author'         => 'Joint Authorship'    ,
      'related'              => 'Related'             ,
      'member_list'          => 'Committee Membership',
      'committee_meetings'   => 'Meetings',
      'bills_referred'       => 'Bills Referred'      ,
      'bills_cosponsored'    => 'BIlls Cosponsored'   ,
      'comm_related'         => 'Related'             ,
    );

    $this->link_title_classif_regex = '@(' . join('|',array_keys($this->link_title_classif_map)) . ')@i';
    $this->link_title_classif_map = array_combine(
      array_map(create_function('$a','return "@(.*)({$a})(.*)@i";'), array_keys($this->link_title_classif_map)),
      array_values($this->link_title_classif_map)
    );

    $this->span_content_prefix_map = array(
      'status' => 'status',
      'principal author' => 'principal-author',
    );
    $this->span_content_prefix_regex = '@^(' . join('|',array_keys($this->span_content_prefix_map)) . '):(.*)@i';
    $this->span_content_prefix_map = array_combine(
      array_map(create_function('$a','return "@^(({$a}):(.*))@i";'), array_keys($this->span_content_prefix_map)),
      array_values($this->span_content_prefix_map)
    );
    $this->member_contact_details = array();
    $this->representative_name = NULL;
  }/*}}}*/

  function extract_aux_information_links() {/*{{{*/
    $debug_method = FALSE;
    $resource_links = array(); // Links to partitions/pages containing authorship and membership info.
    if ( 0 < count($this->aux_information_links) ) {/*{{{*/
      // Determine whether these links are already cached
      $cache_check = new UrlModel();
      $check_links = array();
      foreach ( $this->aux_information_links as $link ) {/*{{{*/
        $url = nonempty_array_element($link,'url');
        if ( empty($url) ) continue;
        $link_type = $link['tag-class'];
        $link['hash'] = UrlModel::get_url_hash($url);
        $link['text'] = nonempty_array_element($this->tag_class_map,$link['tag-class'],$link['text']);
        $link['cached'] = FALSE;
        $link['id'] = NULL;
        $link['a'] = <<<EOH
<a id="{$link['hash']}" href="{$url}" class="{class}" title="{$link['title']}">{$link['text']}</a>
EOH;
        $link['ls'] = <<<EOH
<li>{$link['a']}</li>
EOH;
        $link['a'] = <<<EOH
<span class="congress-member-source-links">{$link['a']}</span>
EOH;

        $resource_links[$link_type] = $link;  
        $check_links[$hash] = $link_type;
      }/*}}}*/
      $checkable_links = array_flip($check_links);
      while ( 0 < count($checkable_links) ) {/*{{{*/
        $link_hashes = array();
        while ( count($link_hashes) < 10 && 0 < count($checkable_links) ) {
          $link_hashes[] = array_pop($checkable_links);
        }
        $this->recursive_dump($link_hashes,"(marker) hashes");
        $cache_check->where(array('AND' => array(
          'urlhash' => $link_hashes
        )))->recordfetch_setup();
        // Mark all cached records.
        while ( $cache_check->recordfetch($link_hashes) ) {
          $urlhash = $link_hashes['urlhash'];
          if ( array_key_exists($urlhash, $check_links) ) {
            $resource_links[$check_links[$urlhash]]['cached'] = TRUE; 
            $resource_links[$check_links[$urlhash]]['id'] = $link_hashes['id']; 
          }
        }
      }/*}}}*/
      array_walk($resource_links,create_function(
        '& $a, $k', '$css = array("legiscope-remote","no-autospider"); $css[] = $a["cached"] ? "cached" : "uncached"; $a["a"] = str_replace("{class}",join(" ",$css), $a["a"]); $a["ls"] = str_replace("{class}",join(" ",$css), $a["ls"]);'
      ));
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) F 16th Congress resource links ------------------------------ " );
        $this->recursive_dump($resource_links,"(marker) -- CS");
      }
    }/*}}}*/
    return $resource_links;
  }/*}}}*/

  function generate_committee_rep_tabs($link_members, $tab_options, $tab_sources = array()) {/*{{{*/
    $debug_method = FALSE;
    $tab_containers = NULL;
    if (is_array($link_members) && is_array($tab_options) && (count($link_members) == count($tab_options))) {/*{{{*/

      // Store Joins between child page URLs and the current committee record
      // Generate tab markup
      $tab_links = array_combine(array_values($tab_options),array_values($link_members));
      array_walk($tab_links, create_function(
        '& $a, $k, $s', '$hash = UrlModel::get_url_hash($a); $a = "<li id=\"{$s[$k]}\"><a class=\"legiscope-content-tab\" id=\"{$hash}\" href=\"{$a}\">{$k}</a>";'
      ), array_flip($tab_options));

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($link_members, "(marker) - - -- -- - URLs");
        $this->recursive_dump($tab_options, "(marker) - - -- -- - Labels");
        $this->recursive_dump($tab_links, "(marker) - - -- -- - Links");
      }/*}}}*/

      $tab_links = join('',array_values($tab_links));

      $tab_containers = array();
      foreach ( array_flip($tab_options) as $id ) {/*{{{*/
        $properties = array('congress-committee-info-tab');
        if ( 0 < count($tab_containers) ) $properties[] = 'hidden';
        $properties = join(' ',$properties);
        $content = nonempty_array_element($tab_sources,$id);
        $tab_containers[] = <<<EOH
<div class="{$properties}" id="tab_{$id}">{$content}</div>

EOH;
      }/*}}}*/
      $tab_containers = join('',$tab_containers);

      $tab_containers = <<<EOH
<div class="theme-options clear-both">
  <hr/>
  <ul class="tab-links">{$tab_links}</ul>
  <div class="congress-committee-info-tabs">{$tab_containers}</div>
</div>

EOH;

    }/*}}}*/
    return $tab_containers;
  }/*}}}*/

  function std_committee_detail_panel_js() {/*{{{*/
    return <<<EOH

<script type="text/javascript">

var hits = 0;
function crawl_dossier_links() {
  jQuery('div[class*=congress-committee-info-tab]').each(function(){
    if ( !jQuery(this).hasClass('active') ) return true;
    hits = 0;
    jQuery(this).find("a[class*=uncached]").first().each(function(){
      hits++;
    });
    if ( hits == 0 ) {
      var next_set = 0;
      if ( jQuery('#spider').prop('checked') ) {
        jQuery('div[id=committee-listing]')
          .find('a[class*=traverse]')
          .first()
          .each(function(){
            next_set++;
          });
      }
      if ( 0 == next_set ) {
        jQuery('#time-delta').html('Done.');
        jQuery('#spider').prop('checked',false);
      } else {
        jQuery('div[id=committee-listing]')
          .find('a[class*=traverse]')
          .first()
          .each(function(){
            jQuery('#time-delta').html('Triggering next available.');
            jQuery(this).removeClass('traverse').click();
          });
      }
    } else {
      jQuery(this).find("a[class*=uncached]").first().each(function(){
        var self = jQuery(this);
        display_wait_notification();
        load_content_window(
          jQuery(this).attr('href'),
          true,
          jQuery(this),
          { url : jQuery(this).attr('href'), async : true },
          { success : function (data, httpstatus, jqueryXHR) {
              remove_wait_notification();
              jQuery(self).removeClass('uncached').addClass('cached');
              if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
              setTimeout((function() {
                crawl_dossier_links();
                enable_proxied_links('legiscope-remote');
              }),200);
            }
          }
        );
      });
    }
    return true;
  });
  return true;
}

function initialize_committee_detail_triggers() {
  jQuery('a[class*=legiscope-content-tab]').unbind('click').click(function(e) {
    e.stopPropagation();
    e.preventDefault();
    var self_id = jQuery(this).attr('id');
    var parent_id = '';
    var url = jQuery(this).attr('href');
     parent_id = jQuery(this).parentsUntil('ul').attr('id');
    if ( !parent_id || (0 == parent_id.length) ) {
      parent_id = 'parent-'+self_id;
      jQuery(this).parentsUntil('ul').attr('id',parent_id);
    }
    jQuery(this).parentsUntil('ul').parent().find('li').each(function(){
      jQuery(this).removeClass('active').attr('class',null);
      if ( jQuery(this).attr('id') == parent_id ) jQuery(this).addClass('active');
      return true;
    });
    jQuery('div[class=congress-committee-info-tabs]').children().each(function(){
      jQuery(this).addClass('hidden').removeClass('active');
    });
    jQuery('div[class=congress-committee-info-tabs]').find('div[id=tab_'+parent_id+']').each(function(){
      jQuery(this).removeClass('hidden').addClass('active');
      var tab_id = 'tab_'+parent_id; 
      if ( !jQuery(this).hasClass('loaded') ) {
        return load_content_window(
          url,
          jQuery('#seek').prop('checked'),
          jQuery('a[id='+self_id+']'),
          { url : url, defaulttab : parent_id, async : true, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked') },
          { success : (function(data, httpstatus, jqueryXHR) {
              jQuery('div[id='+tab_id+']').addClass('loaded').html(data && data.subcontent ? data.subcontent : 'No content retrieved');
              if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
              enable_proxied_links('legiscope-remote');
              setTimeout((function(){
                initialize_committee_detail_triggers();
                if (self_id == 'do_not_crawl') crawl_dossier_links();
              }),100);
            })
          }
        );
      }
    });
    return false;
  });
}

jQuery(document).ready(function(){
  initialize_committee_detail_triggers();
  enable_proxied_links('legiscope-remote');
});
</script>

EOH;
  }/*}}}*/

  function trigger_default_tab($trigger_tab) {/*{{{*/
    return <<<EOH

<script type="text/javascript">
jQuery(document).ready(function(){

  jQuery('div[class=congress-committee-info-tab]').children().remove();
  jQuery('div[class=congress-committee-info-tabs]').children().append(jQuery(document.createElement('IMG'))
    .attr('src','data:image/gif;base64,R0lGODlhEAAQALMAAP8A/7CxtXBxdX1+gpaXm6OkqMnKzry+womLj7y9womKjwAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQBCgAAACwAAAAAEAAQAAAESBDICUqhmFqbZwjVBhAE9n3hSJbeSa1sm5HUcXQTggC2jeu63q0D3PlwAB3FYMgMBhgmk/J8LqUAgQBQhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAES3DJuUKgmFqb5znVthQF9h1JOJKl96UT27oZSRlGNxHEguM6Hu+X6wh7QN2CRxEIMggExumkKKLSCfU5GCyu0Sm36w3ryF7lpNuJAAAh+QQBCgALACwAAAAAEAAQAAAESHDJuc6hmFqbpzHVtgQB9n3hSJbeSa1sm5GUIHRTUSy2jeu63q0D3PlwCx1lMMgQCBgmk/J8LqULBGJRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYyhmFqbpxDVthwH9n3hSJbeSa1sm5HUMHRTECy2jeu63q0D3PlwCx0FgcgUChgmk/J8LqULAmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYSgmFqb5xjVthgG9n3hSJbeSa1sm5EUgnTTcSy2jeu63q0D3PlwCx2FQMgEAhgmk/J8LqWLQmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJucagmFqbJ0LVtggC9n3hSJbeSa1sm5EUQXSTYSy2jeu63q0D3PlwCx2lUMgcDhgmk/J8LqWLQGBRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuRCimFqbJyHVtgwD9n3hSJbeSa1sm5FUUXSTICy2jeu63q0D3PlwCx0lEMgYDBgmk/J8LqWLw2FRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuQihmFqbZynVtiAI9n3hSJbeSa1sm5FUEHTTMCy2jeu63q0D3PlwCx3lcMgIBBgmk/J8LqULg2FRhV6z2q0VF94iJ9pOBAA7')
    .addClass('center')
    .attr('id','busy-notification-wait')
  );
  update_representatives_avatars();
  enable_proxied_links('legiscope-remote');
  jQuery('li[id={$trigger_tab}]').find('a').click();

});
</script>

EOH;
    return $pagecontent; 
  }/*}}}*/


}/*}}}*/


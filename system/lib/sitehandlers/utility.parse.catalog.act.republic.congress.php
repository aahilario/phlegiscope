<?php

/*
 * Class CongressRepublicActCatalogParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressRepublicActCatalogParseUtility extends CongressCommonParseUtility {
  
  var $bill_head_entries = 0; // Read: Total number of entries parsed
  var $bill_set_size     = 10; // Write: Partition list of bills into this many entries per partition
  var $start_offset      = 0; // Write: Start to parse at this entry
  var $parse_limit       = 20; // 2000; // Write: Limit to number of bill entries to parse
  var $parsed_bills      = array();
  var $container_buffer  = array();

  ////////////////////////////////////////////////////////////////////////

  private $debug_ctors            = FALSE;
  private $is_bill_head_container = FALSE;
  private $bill_set_counter       = 0;
  private $bill_subset_counter    = 0;

  private $linkmap;
  private $meta;
  private $target_container_uuid = NULL;

  function __construct() {/*{{{*/
    parent::__construct();
    // Republic Act listing structure (16th Congress):
    //  <td style="border:1px solid #ccc !important;">1. 
    //    <strong>RA10148</strong> 
    //    <a title="text of RA in PDF format" href="http://www.congress.gov.ph/download/ra_15/RA10148.pdf" target="_blank">[PDF, 30k]</a>
    //    <p style="margin:5px 4px 5px 15px;">AN ACT GRANTING PHILIPPINE CITIZENSHIP TO MARCUS EUGENE DOUTHIT</p>
    //    <p style="margin:5px 4px 5px 15px;">Approved by the President on: March 12, 2011</p>
    //    <p style="margin:5px 4px 5px 15px;">Origin: House (HB02307 / SB00000)</p>
    //  </td>
    if ( !is_null($this->bill_set_size) ) $this->parsed_bills = array(0 => array());
  }/*}}}*/

  function __destruct() {/*{{{*/
    if ( $this->debug_ctors ) $this->syslog( __FUNCTION__,__LINE__,"(marker) ----------------------- > Memory load: " . memory_get_usage(TRUE) );
    unset($this->parent_url             );// = NULL;
    unset($this->meta                   );// = NULL;
    unset($this->linkmap                );// = NULL;
    unset($this->bill_cache             );// = NULL;
    gc_collect_cycles();
    if ( $this->debug_ctors ) $this->syslog( __FUNCTION__,__LINE__,"(marker) ----------------------- < Memory load: " . memory_get_usage(TRUE) );
  }/*}}}*/

  // LI tags wrapped individual House Bill entries in the 15th Congress CMS.
  // TR tags serve the same purpose for the new (WordPress?) CMS.

  function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->is_bill_head_container = FALSE;
    $this->add_cdata_property();
    if ( 0 == $this->bill_head_entries ) {
      // Clear previously parsed stream contents
      $this->container_stack = array();
      $this->containers = array();
    }
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    // Parsing of bill entries moved into this method
    $this->current_tag();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    if ( $this->is_bill_head_container ) {
      $this->current_tag();
      $this->bill_head_entries++;
      if (!is_null($this->start_offset)) {
        if ( $this->bill_head_entries <= $this->start_offset ) { 
          $this->no_store = TRUE;
          return FALSE;
        }
        $this->start_offset = NULL;
        $this->no_store = FALSE;
      }
      if (!is_null($this->parse_limit)) {
        // Stop parsing entries once the parse limit counter reaches zero
        if ( $this->parse_limit > 0 ) $this->parse_limit--;
        if ( $this->parse_limit == 0 ) $this->set_freewheel(TRUE)->set_terminated(TRUE);
      }

      $container_item_hash = $this->stack_to_containers();

      if ( !is_null($container_item_hash) ) {
        extract($container_item_hash); // container, hash_value
        //$this->reorder_with_sequence_tags($container);
        $billinfo = nonempty_array_element($container,'children');

        if ( !is_null($billinfo) ) {/*{{{*/
          $current_bill = array();

          while ( 0 < count($billinfo) ) {/*{{{*/

            $container = array_shift($billinfo);

            if ( array_key_exists('bill-head', $container) ) {/*{{{*/

              $hb_number    = join('', array_element($container,'bill-head'));
              $current_bill = $this->get_parsed_housebill_template($hb_number, NULL); 

              continue;

            }/*}}}*/

            if ( array_key_exists('meta', $container) ) {/*{{{*/
              $matches = array();
              if ( 1 == preg_match('@^('.join('|',$this->meta).'):(.*)$@i',join(' ',$container['meta']), $matches) ) {/*{{{*/
                $matches[1] = trim(preg_replace(array_map(create_function('$a','return "@{$a}@i";'),$this->meta),array_keys($this->meta),$matches[1]));
                $matches[2] = trim($matches[2]);
                $current_bill['meta'][$matches[1]] = $matches[2];
              }/*}}}*/
              $matches = NULL;
              continue;
            }/*}}}*/

            if ( array_key_exists('desc', $container) ) {/*{{{*/
              $current_bill['description'] = join('',$container['desc']);
              continue;
            }/*}}}*/

            if ( array_key_exists('onclick', $container) ) {/*{{{*/
              $matches = array();
              $popup_regex = "display_Window\('(.*)','(.*)'\)";
              if ( 1 == preg_match("@{$popup_regex}@i", $container['onclick'], $matches) ) {
                $matches = array_element($matches,1);
                if ( !is_null($matches) ) {
                  $matches = array('url' => $matches);
                  $matches = UrlModel::normalize_url($this->parent_url, $matches);
                  $current_bill['links']['url_history'] = $matches;
                }
              }
              $matches = NULL;
              $mapped = NULL;
              continue;
            }/*}}}*/

            if ( array_key_exists('url', $container) ) {/*{{{*/
              $linktype = preg_replace(array_keys($this->linkmap),array_values($this->linkmap), array_element($container,'text')); 
              $current_bill['links'][$linktype] = array_element($container,'url');
              $linktype = NULL;
              continue;
            }/*}}}*/

          }/*}}}*/

          //$sn = nonempty_array_element($current_bill,'sn');//$this->current_tag['attrs']['seq']
          if ( is_null($this->bill_set_size) ) {/*{{{*/
            $this->parsed_bills[] = $current_bill;
          }/*}}}*/
          else {/*{{{*/
            // Partition parsed bill list
            if ( $this->bill_subset_counter >= $this->bill_set_size ) {
               $this->bill_subset_counter = 0;
              $this->bill_set_counter++;
              $this->parsed_bills[$this->bill_set_counter] = array();
            }
            $this->parsed_bills[$this->bill_set_counter][] = $current_bill;
            // $this->syslog(__FUNCTION__,__LINE__,"(marker) {$current_bill['sn']}");
            $this->bill_subset_counter++;
          }/*}}}*/
          $this->unset_container_by_hash($hash_value);
        }/*}}}*/

      }
      // Ensure that child tags are removed from transient memory. 
      return FALSE;
    }
    return TRUE;
  }/*}}}*/

  // Methods superseding 15th Congress CMS parser tag handlers.
  function ru_strong_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }/*}}}*/
  function ru_strong_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_strong_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $span_class = 'bill-head'; // Hardwire this, to maintain compatibility with ru_span_close replacement ru_tr_close
    $span_type  = array(
      $span_class => is_array($this->current_tag['cdata']) ? array(join('',array_filter($this->current_tag['cdata']))) : array(),
      'seq'       => array_element($this->current_tag['attrs'],'seq')
    );
    $content = array_element($span_type[$span_class],0);
    if ( 1 == preg_match('@^([A-Z]*)([0-9]*)@i',$content) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Found {$content}"); 
      $this->is_bill_head_container = TRUE;
    }
    $this->push_tagstack();
    $this->add_to_container_stack($span_type);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  // P tags may wrap either 'desc' lines (the bill descriptive text)
  // or 'meta' lines (which identify the principal author, main referral committee,
  // and bill status lines)
  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $content = join('',array_filter($this->current_tag['cdata']));
    // 'meta' lines are preceded by a short prefix string ('Status', 'Main Referral', 'Principal Author')
    $span_class = ( 1 == preg_match('@^([A-Z ]{4,32}):@i', $content) )
      ? 'meta'
      : 'desc'
      ;
    $span_type  = array(
      $span_class => is_array($this->current_tag['cdata']) ? array($content) : array(),
      'seq'       => array_element($this->current_tag['attrs'],'seq')
    );
    $this->push_tagstack();
    $this->add_to_container_stack($span_type);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    parent::ru_a_close($parser,$tag);
    if ( array_key_exists('ONCLICK',$this->current_tag['attrs']) )
      return FALSE;
    return TRUE;
  }/*}}}*/

  function ru_form_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    if ($this->debug_method) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) --- {$this->current_tag['attrs']['ACTION']}");
      $this->recursive_dump($this->page_url_parts,"(marker) -p-");
      $this->recursive_dump(UrlModel::parse_url($this->current_tag['attrs']['ACTION']),"(marker) -z-");
    }
    $this->update_current_tag_url('ACTION',FALSE);
    if ($this->debug_method) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -+- {$this->current_tag['attrs']['ACTION']}");
    }
    $attrs['ACTION'] = isset($this->current_tag['attrs']['ACTION']) ? $this->current_tag['attrs']['ACTION'] : NULL;
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  //////////////////////////////////////////////////////////////////////////
  // Post-parse methods

  function & initialize_bill_parser(& $urlmodel) {/*{{{*/

    $this->parent_url   = UrlModel::parse_url($urlmodel->get_url());

    $this->meta = array(
      'origin'           => 'Origin',
      'approval-date'    => 'Approved by the President on',
    );

    $this->linkmap = array(
      '@(\[(.*)text of ra(.*)\])@i'  => 'published',
    );

    return $this;

  }/*}}}*/

  function get_parsed_housebill_template( $hb_number, $congress_tag = NULL ) {/*{{{*/
    return array(
      'sn'           => $hb_number,
      'description'  => NULL,
      'congress_tag' => $congress_tag,
      'meta'         => array(
        'approval-date'  => NULL,
        'origin'         => NULL,
      ),
      'links'        => array(),
    );
  }/*}}}*/

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel, & $document_model) {/*{{{*/

    $this->seek_postparse($parser, $pagecontent, $urlmodel, $document_model, 'ra');

  }/*}}}*/

  //////////////////////////////////////////////////////////////////////////
  // Pre-16th Congress

  function seek_postparse_ra_15(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // FIXME: DEPRECATED METHOD, see also CongressRaListParseUtility system/lib/sitehandlers/utility.parse.list.ra.congress.php 
    $debug_method    = FALSE;
    $restore_url     = NULL;
    $content_changed = FALSE;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Pagecontent postparser invocation for " . $urlmodel->get_url() );

    $urlmodel->ensure_custom_parse();

    if ( !is_null($parser->metalink_url) ) {
      $restore_url = $urlmodel->get_urlhash();
      $url_hash = UrlModel::get_url_hash($parser->metalink_url);
      $urlmodel->fetch($url_hash,'urlhash');
      $this->syslog( __FUNCTION__, __LINE__, "(warning) Switching metalink URL #" . $urlmodel->get_id() . " {$parser->metalink_url} <- " . $urlmodel->get_url() );
      $pagecontent = $urlmodel->get_pagecontent();
    }

    $ra_linktext = $parser->trigger_linktext;

    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $this->recursive_dump(($target_form = array_values($this->get_containers(
      'children[attrs:ACTION*=index.php\?d=ra$]'
    ))),"(debug) Extracted content");

    if (is_null($target_form[0])) {/*{{{*/
      $this->recursive_dump($target_form,'(warning) Form Data');
      $this->recursive_dump($this->get_containers(),'(warning) Structure Change');
      $this->syslog(__FUNCTION__,__LINE__,"(warning) ------------------ STRUCTURE CHANGE ON {$urlmodel} ! ---------------------");
      if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'url');
      return;
    }/*}}}*/

    $target_form = $target_form[0]; 

    $this->recursive_dump(($target_form = $this->extract_form_controls($target_form)),
      $debug_method ? '(marker) Target form components' : '' );

    // $this->recursive_dump($target_form,__LINE__);
    extract($target_form); // $target_form --> userset, select_name => n, n => <select opts>, form_controls => 
    $this->recursive_dump($select_options, $debug_method ? '(marker) SELECT values' : '');

    $metalink_data = array();

    // Extract Congress selector for use as FORM submit action content
    $replacement_content = '';

    $action = UrlModel::parse_url($urlmodel->get_url());
    $action['query'] = "d=ra";
    $action = UrlModel::recompose_url($action,array(),FALSE);

    if ( $debug_method ) $this->recursive_dump($select_options,"(marker) -- SELECT OPTIONS - --");

    foreach ( $select_options as $select_option ) {/*{{{*/
      // Take a copy of the rest of the form controls
      if ( empty($select_option['value']) ) continue;

      $link_class_selector = array("fauxpost");
      if (1 == intval(array_element($select_option,'selected'))) $link_class_selector[] = "selected";
      $link_class_selector = join(' ', $link_class_selector);

      $control_set = is_array($form_controls) ? $form_controls : array();
      $control_set['_LEGISCOPE_']['skip_get'] = TRUE;
      $control_set[$select_name]     = $select_option['value'];
			// 16th Congress POST parameter
      $control_set['submitcongress'] = 'go';

      extract( UrlModel::create_metalink($select_option['text'], $action, $control_set, $link_class_selector, TRUE) );
      $replacement_content .= $metalink;
    }/*}}}*/

    // ----------------------------------------------------------------------
    // Coerce the parent URL to index.php?d=ra
    $parent_url   = UrlModel::parse_url($urlmodel->get_url());
    $parent_url['query'] = 'd=ra';
    $parent_url   = UrlModel::recompose_url($parent_url,array(),FALSE);
    $test_url     = new UrlModel();

    $pagecontent = "{$replacement_content}<br/><hr/>";
    $urlmodel->increment_hits(TRUE);

    $test_url->fetch($parent_url,'url');
    $page = $urlmodel->get_pagecontent();
    $test_url->set_pagecontent($page);
    $test_url->set_response_header($urlmodel->get_response_header())->increment_hits()->stow();
    $test_url->ensure_custom_parse();

    $ra_listparser = new CongressRaListParseUtility();
    $ra_listparser->debug_tags = FALSE;
    $ra_listparser->set_parent_url($urlmodel->get_url())->parse_html($page,$urlmodel->get_response_header());
    $ra_listparser->debug_tags = FALSE;
    $ra_listparser->debug_operators = FALSE;

    $this->recursive_dump(($ra_list = $ra_listparser->get_containers(
      '*[item=RA][bill-head=*]'
    )),$debug_method ? "(marker) Extracted content" : "");

    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__,"(warning) Long operation. Parsing list of republic acts. Entries: " . count($ra_list));

    $parent_url    = UrlModel::parse_url($parent_url);
    $republic_act  = new RepublicActDocumentModel();
    $republic_acts = array();
    $sn_stacks     = array();
    $stacked_count = 0;

    $target_congress = preg_replace("@[^0-9]*@","",$parser->trigger_linktext);

    while ( 0 < count($ra_list) ) {/*{{{*/

      $ra            = array_pop($ra_list);
      $url           = UrlModel::normalize_url($parent_url, $ra);
      $urlhash       = UrlModel::get_url_hash($url);
      $ra_number     = $ra['bill-head'];
      $approval_date = NULL;
      $origin        = NULL;

      if ( !array_key_exists('meta', $ra) ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$ra_number} {$url}");
        continue;
      }/*}}}*/

      $ra_meta = $ra['meta'];
      $ra_meta = preg_replace(
        array(
          "@Origin:@iU",
          "@Approved by(.*) on (.*)@iU",
        ),
        array(
          ';ORIGIN:$1',
          ';APPROVALDATE:$2',
        ),
        $ra_meta
      );

      $match_parts = array();
      preg_match_all('@([^;:]*):([^;]*)@',$ra_meta,$match_parts);

      if ( is_array($match_parts) && (0 < count($match_parts)) ) {/*{{{*/
        $ra_meta       = array_combine($match_parts[1], $match_parts[2]);
        $approval_date = trim(array_element($ra_meta,'APPROVALDATE'));
        $origin        = trim(array_element($ra_meta,'ORIGIN'));
      }/*}}}*/

      if ( FALSE == strtotime($approval_date) ) $approval_date = NULL; 

      // There are a few hundred Republic Acts enumerated per Congress.
      // We take one memory-intensive iteration pass to stack RA series numbers,
      // and a second one to both pop nonexistent RA entries from that stack
      // and create missing records in the database.

      if ( 0 < strlen($ra_number) ) {/*{{{*/// Stow the Republic Act record

        $now_time        = time();

        $republic_acts[$ra_number] = array(
          'congress_tag'  => $target_congress,
          'sn'            => $ra_number,
          'origin'        => $origin,
          'description'   => $ra['desc'],
          'url'           => $url,
          'approval_date' => $approval_date,
          //'searchable'    => 'FALSE', // $searchable,$test_url->fetch($urlhash,'urlhash'); $searchable = $test_url->in_database() ? 1 : 0;
          'last_fetch'    => $now_time,
          '__META__' => array(
            'urlhash' => $urlhash,
          ),
        );
        $sn_stacks[] = $ra_number;
      }/*}}}*/

    }/*}}}*/

    $sorted_desc = array();

    ksort($republic_acts);

    $ra_template = <<<EOH

<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{url}" class="legiscope-remote {cache_state}" id="{urlhash}">{sn}</a></span>
<span class="republic-act-desc">{description}</span>
<span class="republic-act-meta">Origin of legislation: {origin}</span>
<span class="republic-act-meta">Passed into law: {approval_date}</span>
</div>

EOH;

    // Deplete the stack, generating markup for entries that exist
    while ( 0 < count( $sn_stacks ) ) {/*{{{*/
      $sn_stack = array();
      $this->syslog(__FUNCTION__,__LINE__,"(warning) ---------- mark " . count($sn_stacks) . " -----------");
      while ( (count($sn_stack) < 20) && (0 < count($sn_stacks)) ) {
        $sn = array_pop($sn_stacks);
        $sn_stack[$sn] = $sn; 
      }  
      krsort($sn_stack);

      if ( !empty($target_congress) ) {/*{{{*/
        $republic_act->where(array('AND' => array(
          'congress_tag' => $target_congress,
          'sn' => array_keys($sn_stack)
        )))->recordfetch_setup(); 
        // Remove entries from $republic_acts 
        $ra = array();
        while ( $republic_act->recordfetch($ra,TRUE) ) {/*{{{*/
          $ra_number = $ra['sn'];
          $urlhash = $republic_acts[$ra_number]['__META__']['urlhash'];
          // Generate markup for already-cached entries
          $sorted_desc[$ra_number] = preg_replace(
            array(
              '@{urlhash}@i',
              '@{cache_state}@i',
            ),
            array(
              $urlhash,
              'cached',
            ),
            $republic_act->substitute($ra_template)
          );
          unset($sn_stack[$ra_number]);
          unset($republic_acts[$ra_number]);
          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Extant {$ra_number}");
          $ra = array();
        }/*}}}*/
        krsort($sn_stack);
        if ( 0 == count($sn_stack) ) continue;
        // The entries enumerated in sn_stack are NOT yet in the database, so we shove them in. 
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- stowables: " . count($sn_stack));
        $this->recursive_dump($sn_stack,"(marker) -- to stow --");
      }/*}}}*/
      $sn_stack = array_flip($sn_stack);
      while ( 0 < count($sn_stack) ) {/*{{{*/
        $sn = array_pop($sn_stack);
        if ( array_key_exists($sn,$republic_acts) ) {
          unset($republic_acts[$sn]['__META__']);
          $id = $republic_act->
            set_id(NULL)->
            // Execute setters, to allow us to execute substitute()
            set_contents_from_array($republic_acts[$sn],TRUE);
          if ( !empty($target_congress) ) {
            $id = $republic_act->stow();
          }
          $sorted_desc[$sn] = preg_replace(
            array(
              '@{urlhash}@i',
              '@{cache_state}@i',
            ),
            array(
              $urlhash,
              0 < intval($id) ? 'cached' : 'uncached',
            ),
            $republic_act->substitute($ra_template)
          );
          if ( 0 < intval($id) ) {
            unset($republic_acts[$sn]);
            if ( !empty($target_congress) )
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Stowed #{$id} {$sn}.{$target_congress}");
          } else {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Failed to stow {$sn}.{$target_congress}");
          }
        } else {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Cannot find {$sn}.{$target_congress}");
        }
      }/*}}}*/
    }/*}}}*/

    $this->recursive_dump($republic_acts,"(marker) -Remainder Uncommitted-");

    krsort($sorted_desc,SORT_STRING);

    $pagecontent .= join("\n",$sorted_desc);

    if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'urlhash');

    $this->syslog(__FUNCTION__,__LINE__,'---------- DONE ----------- ' . strlen($pagecontent));


  }/*}}}*/

}


<?php

/*
 * Class CongressRaListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressHbListParseUtility extends CongressCommonParseUtility {
  
  var $bill_head_entries = 0; // Read: Total number of entries parsed
  var $bill_set_size     = 10; // Write: Partition list of bills into this many entries per partition
  var $start_offset      = 0; // Write: Start to parse at this entry
  var $parse_limit       = 20; // 2000; // Write: Limit to number of bill entries to parse
  var $parsed_bills      = array();
  var $container_buffer       = array();

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
    // House Bill listing structure:
    // <li>
    //   <span>HB00001</span>
    //   <a>[History]</a>
    //   <a>[Text As Filed 308k]</a>
    //   <span>AN ACT EXEMPTING ALL MANUFACTURERS AND IMPORTERS OF HYBRID VEHICLES FROM THE PAYMENT OF CERTAIN TAXES AND FOR OTHER PURPOSES </span>
    //   <span>Principal Author: SINGSON, RONALD VERSOZA</span>
    //   <span>Main Referral: WAYS AND MEANS</span>
    //   <span>Status: Substituted by HB05460</span>
    // </li>
    //
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

  // LI tags wrap individual House Bill entries

  function ru_li_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->is_bill_head_container = FALSE;
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ( 0 == $this->bill_head_entries ) {
       $this->container_stack = array();
      $this->containers = array();
    }
    $this->push_container_def($tag, $attrs);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_li_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_li_close(& $parser, $tag) {/*{{{*/
    // Parsing of bill entries moved into this method
    $this->current_tag();
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

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    if ( 'bill-head' == nonempty_array_element($attrs,'CLASS') ) {
      $this->is_bill_head_container = TRUE;
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $span_class = array_element($this->current_tag['attrs'],'CLASS');
    $span_class = strtolower(0 < strlen($span_class) ? $span_class : 'desc'); 
    $span_type  = array(
      $span_class => is_array($this->current_tag['cdata']) ? array(join('',array_filter($this->current_tag['cdata']))) : array(),
      'seq'       => array_element($this->current_tag['attrs'],'seq')
    );
    $this->push_tagstack();
    $this->add_to_container_stack($span_type);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    parent::ru_a_close($parser,$tag);
    if ( array_key_exists('ONCLICK',$this->current_tag['attrs']) )
      return FALSE;
    return TRUE;
  }/*}}}*/

  //////////////////////////////////////////////////////////////////////////
  // Post-parse methods

  function & initialize_bill_parser(& $urlmodel) {/*{{{*/

    $this->parent_url   = UrlModel::parse_url($urlmodel->get_url());

    $this->meta = array(
      'status'           => 'Status',
      'principal-author' => 'Principal Author',
      'main-committee'   => 'Main Referral',
    );

    $this->linkmap = array(
      '@(\[(.*)as filed(.*)\])@i'  => 'filed',
      '@(\[(.*)engrossed(.*)\])@i' => 'engrossed',
    );

    return $this;

  }/*}}}*/

  function get_parsed_housebill_template( $hb_number, $congress_tag = NULL ) {/*{{{*/
    return array(
      'sn'            => $hb_number,
      'description'   => NULL,
      'congress_tag'  => $congress_tag,
      'meta'         => array(
        'main-committee'   => array('mapped' => NULL),
        'principal-author' => array('mapped' => NULL),
        'status'           => NULL,
      ),
      'links'        => array(),
    );
  }/*}}}*/

  function seek_postparse_d_billstext_preprocess(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;
    // Obtain pre-cached JSON document, if available
    $metaorigin   = UrlModel::get_url_hash($urlmodel->get_url());
    $shadow_url   = new UrlModel($urlmodel->get_url(),FALSE);
    $congress_tag = NULL;

    if ( !is_null($metalink = $this->filter_post('metalink')) ) {/*{{{*/
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Current content hash: " . $urlmodel->get_content_hash());
      $faux_url = GenericParseUtility::get_faux_url_s($shadow_url, $metalink);
      $metaorigin = UrlModel::get_url_hash($faux_url);
      $congress_tag = nonempty_array_element($metalink,'congress');
      if ( $debug_method ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Fake URL, real content: " . $urlmodel->get_url());
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Target congress: {$congress_tag} <- (" . gettype($metalink) . "){$metalink}" );
				$this->recursive_dump($metalink,"(marker) -");
      }/*}}}*/
    }/*}}}*/

    $shadow_url->add_query_element('metaorigin', $metaorigin);
    $have_parsed_data = $shadow_url->set_url($shadow_url->get_url(),TRUE);

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Meta-URL: " . $shadow_url->get_url() );

    $this->debug_tags   = FALSE;

    $this->start_offset = $this->filter_post('parse_offset',0);
    $this->parse_limit  = $this->filter_post('parse_limit',20);

    if ( !$have_parsed_data || $parser->update_existing ) {/*{{{*/// Stow raw parsed data 

      if ( $debug_method ) {/*{{{*/
        $state = $have_parsed_data ? "existing" : "de novo";
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Regenerating {$state}: " . $shadow_url->get_url() );
      }/*}}}*/

			$this->start_offset = NULL;
			$this->parse_limit  = NULL;

      // Parse content stored in shadow_url
      $this->
        enable_filtered_doc(FALSE)-> // Conserve memory, do not store filtered doc.
        set_parent_url($urlmodel->get_url())->
        initialize_bill_parser($urlmodel)-> 
        parse_html(
          $urlmodel->get_pagecontent(),
          $urlmodel->get_response_header()
        );

      // Clear content from memory
      $urlmodel->
        set_content(NULL)->
        set_id(NULL);

      if ( $debug_method ) {/*{{{*/
        if ( !is_null($this->parse_limit) ) {
          $this->recursive_dump($this->parsed_bills,"(marker) -- F --");
        }
        $this->syslog(__FUNCTION__, __LINE__, "(marker) Containers: " . count($this->containers_r()));
      }/*}}}*/

      $this->filter_nested_array($this->containers_r(),
        'children,attrs[tagname*=form][id*=form1]',0
      );
      $form_data = nonempty_array_element($this->containers_r(),0);

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($form_data,"(marker) -- C --");
      }/*}}}*/

      $this->parsed_bills = array_reverse($this->parsed_bills);

      $this->container_buffer = array(
        'partition_width' => $this->bill_set_size,
        'entries'         => $this->bill_head_entries,
        'parsed_bills'    => $this->parsed_bills,
        'congress_tag'    => $congress_tag,
      ); 

      $this->container_buffer['form'] = array_merge(
        array('action' => array_element(nonempty_array_element($form_data,'attrs'),'ACTION')),
        $this->extract_form_controls(nonempty_array_element($form_data,'children'))
      );

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($this->container_buffer['form'],"(marker) -- L --");
      }/*}}}*/

      $id = $shadow_url->
        set_pagecontent_c(json_encode($this->container_buffer))->
        stow();

      $error = $shadow_url->error();

      if ( !empty($error) || $debug_method ) {/*{{{*/
        $state = !empty($error) ? "Error {$error} stowing" : ($have_parsed_data ? "Reparsed" : "Stored");
        $this->syslog( __FUNCTION__, __LINE__, "(marker) {$state} {$this->bill_head_entries} entries: " . $shadow_url->get_url() );
      }/*}}}*/

    }/*}}}*/

    $offset = $this->start_offset; // Ordinal index (resolves to a single bill as though the partitions are unrolled) 
    $span   = $this->parse_limit; // Elements (bills) to return

    $this->reset(TRUE,TRUE); // Eliminate XML parser, clear containers

    $this->container_buffer = json_decode($shadow_url->get_pagecontent(),TRUE);

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
    $i = $partition_start;
    do {/*{{{*/
      $index = min($i,$partitions-1);
      foreach ( array_reverse($this->container_buffer['parsed_bills'][$index]) as $entry ) {/*{{{*/
        if ( $partition_offset > 0 ) {/*{{{*/
          if ( $i + 4 > $partitions )
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$entry['sn']}   {$i}/{$partitions} SKIP");
          $partition_offset--;
          continue;
        }/*}}}*/
        $entry['description'] = ucfirst(strtolower($entry['description']));
        $final_list[] = $entry;
        if ( $i + 4 > $partitions )
          $this->syslog(__FUNCTION__,__LINE__,"(marker) {$entry['sn']}   {$i}/{$partitions}");
        if ( count($final_list) >= $span ) break;
      }/*}}}*/
      $partition_offset = 0;
      $i++;
    }/*}}}*/
    while ((count($final_list) < $span) && ($i < $partitions));

    if ( $debug_method )
    $this->syslog(__FUNCTION__,__LINE__, "(marker) Returning " . count($final_list) . " entries from source with " . count($this->container_buffer['parsed_bills']) . " partitions");


    $this->container_buffer['parsed_bills'] = $final_list; 

    $parser->json_reply['state'] = ( $partition_end >= $partitions ) ? '0' : '1';

    return $congress_tag;

  }/*}}}*/


}


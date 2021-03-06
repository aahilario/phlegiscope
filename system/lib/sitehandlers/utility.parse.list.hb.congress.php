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
    // House Bill listing structure (15th Congress, up to 23 July 2013):
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
    // House Bill listing structure (16th Congress):
    // <td>
    //   <strong>HB00001</strong>
    //   <a>[History]</a>
    //   <a>[Text As Filed 308k]</a>
    //   <p>AN ACT EXEMPTING ALL MANUFACTURERS AND IMPORTERS OF HYBRID VEHICLES FROM THE PAYMENT OF CERTAIN TAXES AND FOR OTHER PURPOSES </p>
    //   <p>Principal Author: SINGSON, RONALD VERSOZA</p>
    //   <p>Main Referral: WAYS AND MEANS</p>
    //   <p>Status: Substituted by HB05460</p>
    // </td>
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
    if (1) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) --- {$this->current_tag['attrs']['ACTION']}");
      $this->recursive_dump($this->page_url_parts,"(marker) -p-");
      $this->recursive_dump(UrlModel::parse_url($this->current_tag['attrs']['ACTION']),"(marker) -z-");
    }
    $this->update_current_tag_url('ACTION',FALSE);
    if (1) {
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

  function seek_postparse_d_billstext(& $parser, & $pagecontent, & $urlmodel, & $document_model) {/*{{{*/
    // system/lib/sitehandlers/utility.parse.common.congress.php
    $this->debug_method = TRUE;
    $this->seek_postparse($parser, $pagecontent, $urlmodel, $document_model, 'billstext');

  }/*}}}*/

  function seek_postparse_hb_history(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog(__FUNCTION__,__LINE__,"(critical) Metalink data: " . count($parser->metalink_data));
    $this->recursive_dump($parser->metalink_data,"(critical)"); 
    $this->syslog(__FUNCTION__,__LINE__,"(critical) Content: " . $urlmodel->get_pagecontent());

    // Parse and use content of History links
    $url = $urlmodel->get_url();
    $parser->json_reply['subcontent'] = <<<EOH
<span class="error">Parser error treating <a href="{$url}" class="legiscope-remote" target="_blank">{$url}</a></span>
EOH;
    $document     = new HouseBillDocumentModel();
    $bill_number  = $urlmodel->get_query_element('bill_no');
    $congress_tag = $urlmodel->get_query_element('congress');
    $content      = $urlmodel->get_pagecontent();

    $joins = $document->get_joins();
    $this->syslog(__FUNCTION__,__LINE__,"(marker) Joins for " . get_class($document));
    $this->recursive_dump($joins,"(marker)"); 

    // Fetch the document (which must be preparsed, else we trigger a fetch)
    $document->
      join_all()->
      where(array('AND' => array(
        'congress_tag' => $congress_tag,
        'sn' => $bill_number
      )))->
      recordfetch_setup();
    $hb = array();
    while ( $document->recordfetch($hb,TRUE) ) {
      $sn = $hb['sn'];
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Item {$sn} url {$hb['url']} " );
      $this->recursive_dump($hb,"(marker) {$sn}"); 
    }

    $parser->json_reply['subcontent'] = "House Bill {$bill_number}";

  }/*}}}*/

	function get_urlpath_tail_sn_filter($urlpath) {/*{{{*/
		// Invoked from get_document_sn_match_from_urlpath(UrlModel & $urlmodel)
		return strtoupper(preg_replace('@[^HB0-9]@i','',array_pop($urlpath)));
	}/*}}}*/

  function generate_descriptive_markup(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

		// Generate descriptive markup for an already-stored RA/Act record.
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(critical) Invoked for " . $urlmodel->get_url() );

    $pagecontent = '&nbsp;';

    if ( !$urlmodel->in_database() ) $urlmodel->stow();

    $bill_number  = $urlmodel->get_query_element('bill_no');
    $congress_tag = $urlmodel->get_query_element('congress');

    $conditions = $this->get_document_sn_match_from_urlpath($urlmodel, nonempty_array_element($parser->metalink_data,'congress_tag',NULL));

		if (FALSE == $conditions) {
      $this->syslog(__FUNCTION__,__LINE__,"(critical) SQL filter condition not constructed. Trying alternate.");
      $conditions = $this->fetch_document_sn_congress_session_conds($bill_number, $congress_tag, NULL);
    }

		if (FALSE == $conditions) {
      $this->syslog(__FUNCTION__,__LINE__,"(critical) Unable to construct SQL condition clauses.");
    }
    else {
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(critical) SQL filter condition");
        $this->recursive_dump($conditions,"(critical)");
      }
			$this->generate_pagecontent_using_ocr($pagecontent, $conditions, 'HouseBillDocumentModel');
		}

    $parser->json_reply['httpcode'] = 200;
    $parser->json_reply['contenttype'] = 'text/html';
    $parser->json_reply['subcontent'] = $pagecontent;
    $pagecontent = NULL;

  }/*}}}*/



}


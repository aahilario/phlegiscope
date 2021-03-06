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

  function ru_a_open(& $parser, & $attrs, $tag) {
    $this->pop_tagstack();
    $this->update_current_tag_url('HREF',TRUE);
    $this->push_tagstack();
    return parent::ru_a_open($parser,$attrs,$tag);
  }
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

}


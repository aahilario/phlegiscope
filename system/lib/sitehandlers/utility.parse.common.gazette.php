<?php

/*
 * Class GazetteCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GazetteCommonParseUtility extends GenericParseUtility {
  
  var $articles_found = array();
  var $navigation_links = array();
  var $article = array();
  var $article_bookmarks = array();

  function __construct() {
    parent::__construct();
  }

  function parse_html(& $raw_html, $only_scrub = FALSE) {
    $status = !is_null(parent::parse_html($raw_html, $only_scrub));
    $this->mark_container_sequence();
    // Cleanup articles found
    return $this;
  }

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->pop_tagstack();
    $this->update_current_tag_url('HREF');
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    array_walk($this->current_tag['cdata'],create_function(
      '& $a, $k', '$a = trim(preg_replace(array("@\s+@i","@\s+ñ@i"),array(" ","ñ"),$a));'
    ));
    $link_data = $this->collapse_current_tag_link_data();
    if ( !empty($link_data['url']) && !empty($link_data['text']) ) {
      $this->add_to_container_stack($link_data);
      if ( 1 == preg_match('@(bookmark)@i', array_element($this->current_tag['attrs'],'REL')) ) {
        unset($link_data['sethash']);
        unset($link_data['found_index']);
        unset($link_data['seq']);
        $sn = $this->extract_act_sn_from_parsed_tagdata(array($link_data));
        // Only store links for which the SN is recognized by
        // RepublicActDocumentModel::standard_sn($s) 
        if ( !is_null($sn) ) {/*{{{*/
          $this->article_bookmarks[$sn] = array_merge(
            $link_data,
            array(
              'id' => NULL,
              'sn' => $sn,
              'document' => NULL,
              'seq' => $this->current_tag['attrs']['seq'],
              'urlhash' => UrlModel::get_url_hash($link_data['url']),
            )
          );
        }/*}}}*/
      }
    }
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->current_tag();
    if ( 1 == preg_match('@(' . join('|',array(
      'entry-content',
      'nav-next',
      'nav-previous',
    )) . ')@', array_element($this->current_tag['attrs'],'CLASS')) ) {
      $this->push_container_def($tag, $attrs);
    }
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if (1 == preg_match('@(' . join('|',array(
      'entry-content',
    )) . ')@', array_element($this->current_tag['attrs'],'CLASS'))) {
      return $this->embed_container_in_parent($parser,$tag);
    }
    else if (1 == preg_match('@(' . join('|',array(
      'nav-next',
      'nav-previous',
    )) . ')@', array_element($this->current_tag['attrs'],'CLASS'))) {
      if ( is_array($data = $this->stack_to_containers(TRUE)) ) {
        // Save recordset cursor links ("Newer entries", "Older entries")
        $this->unset_container_by_hash(nonempty_array_element($data,'hash_value'));
        $data = nonempty_array_element($data,'container');
        $this->reorder_with_sequence_tags($data);
        array_walk($data['children'],create_function(
          '& $a, $k, $s', 'if ( array_key_exists("tagname", $a) ) $s->reorder_with_sequence_tags($a["children"]);'
        ),$this);
        $data = nonempty_array_element($data,'children');
        $this->filter_nested_array($data,'#[url*=^http://|i]');
        foreach ( $data as $seq => $url ) $this->navigation_links[$seq] = $url;
      }
    }
    return FALSE;
  }/*}}}*/

  function ru_meta_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_meta_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_script_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_script_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_script_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if ( C('DEBUG_GAZETTE_SOURCE_JAVASCRIPT') ) {
      $this->recursive_dump($this->current_tag['cdata'], 
        "(marker) Scr");
      $this->add_to_container_stack($this->current_tag);
    }
    return FALSE;
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_h2_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_h2_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_h2_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/

  function ru_h1_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_h1_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_h1_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/

  function ru_b_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_b_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_b_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_strong_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_strong_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_strong_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_i_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_i_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_i_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_em_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_em_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_em_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    if ( 1 == preg_match('@(nginx)@i', $cdata) ) {
      $this->pop_tagstack();
      $this->current_tag['discard'] = TRUE;
      $this->push_tagstack();
    }
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if (is_array($this->current_tag) && array_key_exists('discard',$this->current_tag) && ($this->current_tag['discard'] === TRUE)) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      unset($this->current_tag['_cdata_next_']);
      $this->push_tagstack();
    }
    return $this->standard_cdata_container_close();
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    return $this->add_cdata_property();
  }  /*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/


  /** New structure 16th Congress ca. July 2013 **/

  function ru_article_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }  /*}}}*/
  function ru_article_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_article_close(& $parser, $tag) {/*{{{*/
    if ( is_array($data = $this->stack_to_containers()) ) {
      $this->unset_container_by_hash(nonempty_array_element($data,'hash_value'));
      $data = nonempty_array_element($data,'container');
      if ( 1 == preg_match('@category-republic-acts@i',$data['class']) ) {

        $this->mark_container_placeholders($data);

        // Extract SN from first 20 lines
        $header_slice = 20;
        $header = array_slice($data['children'],0,$header_slice);
        $sn = $this->extract_act_sn_from_parsed_tagdata($header);

        // Extract Congress number, Session Number, and SB and HB precursors 
        // (where applicable) from document routing [text] lines
        $header_slice = 40;
        $header = array_slice($data['children'],0,$header_slice);
        $this->filter_nested_array($header,'children[class*=entry-content]',0);
        $header = nonempty_array_element($header,0,array());

        array_walk($header,create_function(
          '& $a, $k', '$a = (1 == preg_match("@(\[BR\])@i",array_element($a,"text"))) ? explode("[BR]",array_element($a,"text")) : array(array_element($a,"text"));'
        ));
        $header = array_values(array_filter($header));
        $header = array_values(array_merge(
          array_element($header,0,array()),
          array_element($header,1,array())
        ));
        // Remove unchanged entries
        array_walk($header,create_function(
          '& $a, $k, $s', '$r = preg_replace(array_keys($s),array_values($s),trim($a)); $a = (trim($r) == trim($a)) ? NULL : explode("|",$r);'
        ),
        $s = array(
          '@(s\.(.*)no\.([^0-9]*)([0-9]*))@i' => 'sb_precursor|$4',
          '@(h\.(.*)no\.([^0-9]*)([0-9]*))@i' => 'hb_precursor|$4',
          '@Third@i'    => '3',
          '@Second@i'   => '2',
          '@First@i'    => '1',
          '@Regular@i'  => 'R',
          '@Special@i'  => 'S',
          '@((.*) (R|S) Session)@i' => 'session|$2$3',
          '@Sixteen@i'   => '16',
          '@Fifteen@i'   => '15',
          '@Fourteen@i'  => '14',
          '@Thirteen@i'  => '13',
          '@Twel(v|f)@i' => '12',
          '@Eleven@i'    => '11',
          '@Ten@i'       => '10',
          '@Nin@i'       => '9',
          '@Eigh@i'      => '8',
          '@Seven@i'     => '7',
          '@Six@i'       => '6',
          '@Fif@i'       => '5',
          '@(([0-9]*)th Congress)@i' => 'congress_tag|$2',
          '@(an act .*)@i'          => 'description|$1',
          '@((republic )*act no.*)@i'          => 'title|$1',
        ));

        $header = array_values(array_filter($header));
        // Recreate the array with six possible keys: description, title, session, congress_tag, sb_precursor, and hb_precursor.
        if ( !is_array($header) || (0 == count($header)) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) No headers for document {$sn}. Odd.");
        }
        else {

          $header = array_combine(
            array_map(create_function('$a', 'return $a[0];'), $header),
            array_map(create_function('$a', 'return $a[1];'), $header)
          );

          $header = array_filter($header);

          if ( $debug_method ) { 
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$sn} -+-+-+---------------------");
            //$this->recursive_dump($header,"(marker) {$sn} -+-+-+--------");
            $this->recursive_dump($header,"(marker) {$sn} -+-+-+--------");
            $this->syslog(__FUNCTION__,__LINE__,"(marker) ++ Body contains " . count($children) . " elements");
          }
        }

        // We are assuming (probably correctly) that the set of SNs
        // in <article> tags on a page are unique.
        $this->filter_nested_array($data['children'],'children[tagname=div][class*=entry-content]',0);
        $data = $data['children'][0];
        $this->articles_found[] = array_merge(
          $header,
          array_key_exists($sn, $this->article_bookmarks) ? $this->article_bookmarks[$sn] : array(),
          array(
            'sn' => $sn,
            'document' => json_encode($data), // JSON-encoded [children] element
            'children' => $data, // FIXME: Eliminate this if possible
          )
        );
      }
      return FALSE;
    } 
    return TRUE; 
  }/*}}}*/

  protected function extract_act_sn_from_parsed_tagdata($header) {/*{{{*/
    $this->filter_nested_array($header,'text[text*=(republic)*.*act|i]',0);
    // Hack for encoding gaffe ('O' for '0') 
    $header = trim(array_element($header,0));
    $sn = preg_replace('@[^RA0-9]@i','',str_replace('O','0',preg_replace('@((.*)republic(.*)act([^0-9]*)([0-9O ]*)(.*))@i','RA$5', $header)));
    return RepublicActDocumentModel::standard_sn($sn);
  }/*}}}*/


}

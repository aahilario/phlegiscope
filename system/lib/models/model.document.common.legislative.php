<?php

/*
 * Class LegislativeCommonDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class LegislativeCommonDocumentModel extends UrlModel {
  
  function __construct() {
    parent::__construct();
  }

  // Catalog list partitioning

  function prepare_markup_source(& $bill_cache, $in_iterator = FALSE, $entry_key = NULL) {/*{{{*/
    if ( !$in_iterator ) {
      // Method invoked after caching content, to add entries in $bill_cache
      // that are used (by e.g. CongressCommonParseUtility::emit_document_entries_js)
      // to generate additional DHTML behavior.

      array_walk($bill_cache,create_function(
        '& $a, $k, $s','$a = $s->prepare_markup_source($a, TRUE, $k);'
      ),$this);

      return TRUE;
    }

    $links     = nonempty_array_element($bill_cache,'links');
    $linktext  = NULL; // Irrelevant, since we only need the $hash and $metadata result
    $classname = join(' ',array('legiscope-remote','fauxpost'));
    foreach ( $links as $attrname => $href ) {
      $href = rtrim($href,'/');
      $control_set = array('_LEGISCOPE_' => array(
        // This results in a link that triggers a 
        // server-side GET requesting the resource identified
        // by it's URL, with no subsequent POST; the
        // Congress number and document SN is also sent 
        // when the link is triggered.
        'get_before_post' => TRUE,
        'skip_get'        => TRUE,
        'congress_tag'    => $bill_cache['congress_tag'],
        'sn'              => $bill_cache['sn'],
      )); 
      $parts = UrlModel::create_metalink($linktext, $href, $control_set, $classname, TRUE);
      extract($parts); // $metalink, $hash, $metadata
      $bill_cache['metalink'][$attrname] = array(
        'hash' => $hash,
        'metadata' => $metadata,
        'classname' => $classname,
      );
      // Trim trailing slash
      $bill_cache['links'][$attrname] = $href;
    }
    return $bill_cache;
  }/*}}}*/


  // OCR-related, document formatting methods

  function test_document_ocr_result($url_accessor = NULL) {/*{{{*/

    // Reload this Document and test for presence of OCR version of doc_url PDF.
    // Return TRUE if the document is associated with at least one converted record (stored as a set of ContentDocuments)
    //        NULL
    //        FALSE 

    $debug_method = FALSE;

		if ( !$this->in_database() ) {
			$this->syslog(__FUNCTION__,__LINE__,"(critical) Model not loaded. Cannot proceed.");
		 	return NULL;
		}

		if ( is_null($url_accessor) ) $url_accessor = 'get_doc_url';

    $faux_url         = new UrlModel();
		$document_source  = str_replace(' ','%20',$this->$url_accessor());
    $faux_url_hash    = UrlModel::get_url_hash($document_source);
    $url_stored       = $faux_url->retrieve($faux_url_hash,'urlhash')->in_database();
    $pdf_content_hash = $faux_url->get_content_hash();
		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Using contents of UrlModel #" . $faux_url->get_id() . " {$document_source}");

    // Retrieve all OCR content records associated with this Senate document.
    $document_id      = $this->get_id();
    $this->
      join(array('ocrcontent'))->
      where(array('AND' => array(
        'id' => $document_id,
        '{ocrcontent}.`source_contenthash`' => $pdf_content_hash,
      )))->
      recordfetch_setup();

    $matched = 0;
    $remove_entries = array();
    while ( $this->recordfetch($r,TRUE) ) {/*{{{*/
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Record #{$r['id']} {$r['sn']}.{$r['congress_tag']}");
      $ocrcontent = $this->get_ocrcontent();
      if ( $debug_method ) $this->recursive_dump($ocrcontent,"(marker) ->");
      $data = nonempty_array_element($ocrcontent,'data');
      $meta = nonempty_array_element($data,'content_meta');
      $data = nonempty_array_element($data,'data');
      
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Test data = {$data}");
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Test meta = {$meta}");
      }
      // Both the content and content_meta document source must be nonempty
      if ( (0 < mb_strlen(preg_replace('@[^-A-Z0-9.,: ]@i', '', $data))) && (0 < strlen($meta)) ) {
        $matched++;
      }
      else {
        // Otherwise both the Join and ContentDocument records are removed.
        $remove_entry = array_filter(array(
          'join' => nonempty_array_element($ocrcontent['join'],'id'),
          'data' => nonempty_array_element($ocrcontent['data'],'id'),
        ));
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Marking invalid Join {$r['sn']}.{$r['congress_tag']}");
        $this->recursive_dump($remove_entry,"(marker)");
        if ( 0 < count($remove_entry) ) $remove_entries[] = $remove_entry;
      }
    }/*}}}*/
    while ( 0 < count($remove_entries) ) {/*{{{*/
      $remove_entry = array_shift($remove_entries);
      if ( !is_null(nonempty_array_element($remove_entry,'join')))
      $this->get_join_instance('ocrcontent')->
        retrieve($remove_entry['join'],'id')->
        remove();
      if ( !is_null(nonempty_array_element($remove_entry,'data')))
      $this->get_foreign_obj_instance('ocrcontent')->
        retrieve($remove_entry['data'],'id')->
        remove();
    }/*}}}*/
    if ( 0 == $matched ) {/*{{{*/
      // No record matched
      $ocr_result_file = LegiscopeBase::get_ocr_queue_stem($faux_url) . '.txt';
      if ( file_exists($ocr_result_file) && is_readable($ocr_result_file) ) {/*{{{*/

        $properties = stat($ocr_result_file);
        $properties['sha1'] = hash_file('sha1', $ocr_result_file);

        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR output file {$ocr_result_file} present.");
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR output file size: {$properties['size']}" );
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR data model: " .
            get_class($this->get_join_instance('ocrcontent')));
        }

        $ocr_data = new ContentDocumentModel();
        $ocr_data_id = $ocr_data->retrieve($properties['sha1'],'content_hash')->get_id();

        if ( is_null($ocr_data_id) ) {/*{{{*/
          $data = array(
            'data' => file_get_contents($ocr_result_file),
            'content_hash' => $properties['sha1'],
            'content_type' => 'ocr_result',
            'content_meta' => $document_source,
            'last_fetch' => $this->get_last_fetch(),
            'create_time' => time(),
          );
          $ocr_data_id = $ocr_data->
            set_contents_from_array($data)->
            fields(array_keys($data))->
            stow();
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Created OCR data record ID: #" . $ocr_data_id  );
        }/*}}}*/

        if ( 0 < intval($ocr_data_id) ) {/*{{{*/
          $join = array( $ocr_data_id => array(
            'source_contenthash' => $pdf_content_hash,
            'last_update' => time(),
            'create_time' => time(),
          ));
          $join_result = $this->create_joins('ContentDocumentModel',$join);
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Created Join: #" . $join_result  );
        }/*}}}*/

      }/*}}}*/
    }/*}}}*/
    else {
      if ( !$this->get_searchable() ) {
        $this->set_searchable(TRUE)->fields(array('searchable'))->stow();
      }
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Found {$matched} Join objects for OCR source " . $faux_url->get_url());
    }

    return $matched > 0;
  }/*}}}*/

  function reconstitute_ocr_text() {/*{{{*/
		$debug_method = FALSE;
    $t = nonempty_array_element($this->get_ocrcontent(),'data');
    if ( is_null($t) || !is_array($t) ) return FALSE;
    $t = nonempty_array_element($t,'data');
    if ( !is_string($t) ) return FALSE;
    $t = preg_split('@(\n|\f|\r)@i', $t);
		if ( $debug_method ) {
			$this->syslog( __FUNCTION__,__LINE__, "(critical) Result of split: " . gettype($t));
			$this->recursive_dump($t,"(critical)");
		}
    if ( !is_array($t) ) return FALSE;
    $final = array();

    $whole_line = array();
    $last_line  = 0;
    $current_line = 0;

    while ( 0 < count($t) ) {/*{{{*/

      $line = array_shift($t);

      // Strip trailing noise
      $line = preg_replace('@([^A-Z0-9;:,.-]{1,})$@i','', $line);

      $non_alnum        = mb_strlen(preg_replace('@[^A-Z0-9;:,. -]@i','',$line));
      $just_alnum       = mb_strlen(preg_replace('@[A-Z0-9. ]@i','',$line));
      $ratio = 1.0;
      if ( 0 < mb_strlen($line) ) {
        $ratio = ( floatval($just_alnum) / floatval(mb_strlen($line)) );
        if ( $ratio > 0.2 ) $line = "[noise]";
      }

      $maybe_pagenumber = 1 == preg_match('@^([0-9]{1,})$@', $line);
      $line_number = array();
      $is_numbered_line = 1 == preg_match('@^([0-9]{1,}[ ]{1,})@i',$line,$line_number);
      $is_end_of_line   = 1 == preg_match('@([:;. -]{1,}|([:;][ ]{1,}or)|[.][0-9]{1,})$@i', $line);
      $is_section_head  = 1 == preg_match('@^(SEC|Art)@i', $line);
      $introduction     = 1 == preg_match('@^(EXPLANATORY[ ]*NOTE)@i',$line);

      if ( $maybe_pagenumber ) {
        $replacement = ' ';//'---------- [Page $2] --------'
        $line = preg_replace('@^(([0-9]{1,})[ ]*)$@i', $replacement, $line);
      }
      else if ( $is_numbered_line ) {
        $replacement = ' ';//'[Line $2]'
        $line = preg_replace('@^(([0-9]{1,})[ ]*)@i', $replacement, $line);
      }

      if ( $line == '[noise]' ) {
        $is_end_of_line |= TRUE;
      } else
        $whole_line[] = $line;

      $add_space_beforeline = ( $is_section_head || $introduction );
      $add_space_afterline  = ( $is_end_of_line || $introduction );

      if ( $add_space_afterline || $add_space_afterline ) {
        $line = trim(join('',$whole_line));
        for ( $p = $last_line ; $p < $last_line ; $p++ ) {
          $final[$p] = NULL;
        }
        $last_line = $current_line ;
        $whole_line = array();
      }
      else $line = NULL;

      if ( $add_space_beforeline ) $final[$current_line++] = '[emptyline]';
      $final[$current_line++] = $line;
      if ( $add_space_afterline ) $final[$current_line++] = '[emptyline]';

    }/*}}}*/

    // Remove multiple adjacent [emptyline] entries.
    $final      = array_filter($final);
    $prev_empty = FALSE;
    $t          = array();
    while ( 0 < count($final) ) {
      $line = array_shift($final);
      if ( $line == '[emptyline]' ) {
        if ( $prev_empty ) continue;
        $prev_empty = TRUE;
        $line = '';
      }
      else $prev_empty = FALSE;
      $t[] = array('text' => $line);
    }
    // $this->recursive_dump($final,"(error)");
    $ocrcontent = $this->get_ocrcontent();
    $ocrcontent['data']['data'] = $t;
    $this->set_ocrcontent($ocrcontent);
  }/*}}}*/
  
  function line_formatter($s) {/*{{{*/
    return preg_replace(
      array(
        "@\s@",
        '@\[([ ]*)(.*)([ ]*)\]@i',
        '@^[ ]*(BOOK|TITLE|CHAPTER)([ ]*)(.*)@i',
        '@^[ ]*ART(ICLE)*([ ]*)(.*)@i',

        '@^[ ]*(SEC(TION|\.)([ ]*)([0-9]*)[.]*)@i',
        '@^[ ]*\(([a-z]{1}|[ivx]{1,4}|[0-9]{1,})[.]?\)([\s]*)@i',
        '@^[ ]*([0-9]{1,})([.])@i',
        '@^[ ]*([a-z])([.][\s])@i',

        '@(Republic Act N(o.|umber)([ ]*)([0-9]{4,}))@i',
        '@(Senate Bill N(o.|umber)([ ]*)([0-9]{4,}))@i',
        '@(House Bill N(o.|umber)([ ]*)([0-9]{4,}))@i',
      ),
      array(
        ' ',
        '<span class="document-section document-heading-1">$2</span>',
        '<span class="document-section document-heading-1">$1 $3</span>',
        '<span class="document-section document-heading-1">Article $3</span>',

        '<span class="document-section document-heading-2">Section $4</span>.',
        '<span class="document-section document-heading-3">($1) </span>',
        '<span class="document-section document-heading-4">$1. </span>',
        '<span class="document-section document-heading-4">$1. </span>',

        '[{RA-$4}]',
        '[{SB-$4}]',
        '[{HB-$4}]',
      ),
      $this->iconv($s)
    );
  }/*}}}*/

  function format_document($content) {/*{{{*/
    if (is_array($content) && (0 < count($content))) {
      $content = array_values(array_filter(array_map(create_function(
        '$a', 'return str_replace("[BR]","<br/>",nonempty_array_element($a,"text"));'
      ),$content)));
      // Replace line headings
      array_walk($content,create_function(
        '& $a, $k, $s', '$a = $s->line_formatter($a);'
      ), $this);
      $content = array_filter(array_map(create_function(
        '$a', 'return "<p>{$a}</p>";'
      ),$content));
      return  join("\n",$content);
    }
    return NULL;
  }/*}}}*/

  function prepare_ocrcontent() {/*{{{*/
    if (!($this->in_database())) {
      $this->syslog(__FUNCTION__,__LINE__,"(critical) Uninitialized record.");
    } 
    else if (is_null($this->get_ocrcontent())) {
      $this->set_ocrcontent('No OCR conversion available.');
    } else {
      $ocrcontent = $this->get_ocrcontent();
      $ocrcontent = nonempty_array_element($ocrcontent,'data');
      $ocr_record_id = nonempty_array_element($ocrcontent,'id');
      if ( is_null($ocr_record_id) || !(0 < intval($ocr_record_id)) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) No OCR result stored.");
        $this->set_ocrcontent('No OCR available');
        return FALSE;
      }
      if ( !(0 < mb_strlen(mb_ereg_replace('@[^-A-Z0-9;:. ]@i', '', nonempty_array_element($ocrcontent,'data')))) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) Invalid OCR content. Remove ContentDocument #{$ocr_record_id}");
        $ocrcontent = $this->get_ocrcontent();
        $ocrcontent['data']['data'] = 'No OCR available'; 
        $this->set_ocrcontent($ocrcontent);
        return FALSE;
      }
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) OCR content record #{$ocr_record_id} available.");
      $ocrcontent = NULL;
      unset($ocrcontent);
      $this->reconstitute_ocr_text();

      if (1) {
        $ocrcontent = $this->get_ocrcontent();
        $content = nonempty_array_element($ocrcontent,'data');
        $content = nonempty_array_element($content,'data');
        $content = $this->format_document($content);
        if ( !is_null($content) ) {
          $ocrcontent['data']['data'] = $content;
          $this->set_ocrcontent($ocrcontent);
        }
        $content = NULL;
        $ocrcontent = NULL;
      }

      return TRUE;
    }
    return FALSE;
  }/*}}}*/


}


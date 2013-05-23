<?php

/*
 * Class RepublicActParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepublicActParseUtility extends SenateCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
    $this->pop_tagstack();
		$text = array(
			'text' => str_replace(array('[BR]',"\n"),array(''," "),join('',$this->current_tag['cdata'])),
		);
		$this->add_to_container_stack($text);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function non_session_linked_document_prepare_content(& $uncached_document_content) {
    // Method called from non_session_linked_content_parser
    $this->syslog(__FUNCTION__,__LINE__, "(marker) Preparing markup" ); 
    // $this->recursive_dump($uncached_document_content,"(marker) - - -");
    // The Republic Act document pager returns a minimal amount of information.
    // - Republic Act No.
    // - PDF link
    // - PDF link text
    // - Approval date
    // Information is extracted to yield this array, modified in place:
    //   (array) 942 =>
    //     (string) url => http://www.senate.gov.ph/lis/bill_res.aspx?congress=15&q=SRN-942
    //     (string) text => SRN-942
    //     (string) title => SRN-942
    //     (string) desc => Resolution Calling for the Manila Zoo Administration to Effect the Immediate Transfer of its Lone Elephant to a Proper Sanctuary Filed
    //     (string) sn => SRN-942
    //     (integer) create_time => 1368620100
    //   (array) 941 =>
    //     (string) url => http://www.senate.gov.ph/lis/bill_res.aspx?congress=15&q=SRN-941
    //     (string) text => SRN-941
    //     (string) title => SRN-941
    //     (string) desc => Resolution Amending Section 18 of the Senate Rules of Procedure Governing Inquiries in Aid of Legislation Filed on February 5, 2013 by
    //     (string) sn => SRN-941
    //     (integer) create_time => 1368620100
    $content = array(
      'url'         => NULL, // http://www.senate.gov.ph/lis/bill_res.aspx?congress                                                                                    = 15&q = SRN-941
      'text'        => NULL, // SRN-941
      'title'       => NULL, // SRN-941
      'desc'        => NULL, // Resolution Amending Section 18 of the Senate Rules of Procedure Governing Inquiries in Aid of Legislation Filed on February 5, 2013 by
      'sn'          => NULL, // SRN-941
      'create_time' => NULL, // 1368620100
    );
    $altered_record = $content;
    $senate_ra_url_regex = 'http://www.senate.gov.ph/republic_acts/ra([ ]*)([0-9]*).pdf';
    $approval_date_regex = 'Approved(.*)on(.*)$';
    foreach ( $uncached_document_content as $seq => $markup ) {
      if ( array_key_exists('url', $markup) ) {
        $url_regexmatch = array();
        $url = urldecode($markup['url']);
        $this->syslog(__FUNCTION__,__LINE__,"(marker) URL: {$url}");
        $altered_record['url'] = $url;
        if ( 1 == preg_match("@{$senate_ra_url_regex}@i", $url, $url_regexmatch) ) {
          $text = explode('[BR]',$markup['text']);
          $suffix = str_pad(ltrim($url_regexmatch[2],'0'),5,'0',STR_PAD_LEFT);
          $altered_record['sn']          = "RA{$suffix}";
          $altered_record['text']        = "RA{$suffix}"; // Used by caller to reorder entries
          $altered_record['sn_suffix']   = $suffix;
          $altered_record['create_time'] = time();
          $altered_record['title']       = trim($text[0]);
          $altered_record['desc']        = trim($text[1]);
        }
        $uncached_document_content[$seq] = $altered_record;
        $altered_record = $content;
        continue;
      }
      $action_match = array();
      $text = $markup['text'];
      if ( 1 == preg_match("@{$approval_date_regex}@i", $text, $action_match) ) {
        $altered_record['approval_date'] = $action_match[2];
      }
      $uncached_document_content[$seq] = NULL;
    }
    $uncached_document_content = array_filter($uncached_document_content);
    $this->recursive_dump($uncached_document_content,"(marker) - - -");
    return TRUE;
  }

}


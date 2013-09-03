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

  function non_session_linked_document_prepare_content(& $uncached_document_content) {/*{{{*/
    // Method called from non_session_linked_content_parser
    $debug_method = FALSE;
    if ($debug_method) $this->syslog(__FUNCTION__,__LINE__, "(marker) Preparing markup" ); 
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
        if ($debug_method) $this->syslog(__FUNCTION__,__LINE__,"(marker) URL: {$url}");
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
    if ($debug_method) $this->recursive_dump($uncached_document_content,"(marker) - - -");
    return TRUE;
  }/*}}}*/

  /** Higher-level page parsers **/

  private function fetch_legislation_links(& $ra_parser, RepublicActDocumentModel & $republic_act) {/*{{{*/

    // Extract table of legislation for this page

    $test_url = new UrlModel();

    $sub_list = $ra_parser->get_containers('children[tagname=div][class*=alight|i]');
    $sub_list = array_values($sub_list);

    $empty_ra_def = array(
      'link'     => array(), 
      'desc'     => NULL, 
      'linktext' => NULL,
      'aux'      => array(),
    );

    $republic_acts = array($empty_ra_def);
    $current_id = NULL;

    if ( 1 == count($sub_list) ) foreach ( $sub_list[0] as $tag ) {/*{{{*/

      if ( is_null($tag) ) continue;
      if ( array_key_exists('image', $tag) ) continue; // Skip PDF icon link image; irrelevant

      $ra = array_pop($republic_acts);

      // if ( is_null($current_id) && array_key_exists('attrs', $tag) && array_key_exists('ID', $tag['attrs']) )
      //  $current_id = $tag['attrs']['ID'];

      $is_texttag = array_key_exists('text', $tag);
      $is_linktag = array_key_exists('attrs', $tag) && array_key_exists('HREF', $tag['attrs']);
      
      if ( $is_texttag && !empty($ra['aux']) ) {/*{{{*/
        // The current tag already has link text; push a new, empty def onto the stack
        // $this->syslog(__FUNCTION__,__LINE__,"-- Skip to new entry.  Record now is currently for {$ra['linktext']}:");
        array_push($republic_acts, $ra);
        // $this->recursive_dump($ra,'(warning)');
        array_push($republic_acts, $empty_ra_def);
        $ra = array_pop($republic_acts);
        // Try to find a republic act serial number in this line.
        $matches = array();
        preg_match('@(Republic Act No)*([^0-9]*)([0-9]*)(.*)@i',$tag['text'],$matches);
        if ( is_numeric($matches[3]) ) {
          $ra['linktext'] = 'RA' . str_pad(ltrim($matches[3],'0'),5,'0',STR_PAD_LEFT);
          $ra['desc'] = trim($matches[4]);
          $republic_act->fetch($ra['linktext'], 'sn');
          $found = $republic_act->in_database();
          $ra['cached_sn'] = $found ? $republic_act->get_id() : NULL;
          if ( $found ) {
            $ra['link'][] = $republic_act->get_url();
            $ra['desc'] = $republic_act->get_description();
          }
        }
      }/*}}}*/
      // $ra['components'][] = $tag;
      if ( $is_texttag ) {
        // We're expecting metadata lines, specifically something resembling
        // - Approved by ... [DATE]
        $ra['aux'][] = $tag['text'];
        array_push($republic_acts, $ra);
        continue;
      }

      if ( $is_linktag ) {
        // Extract parts of an A tag containing republic act links
        $link = $tag['attrs']['HREF'];
        $ra['desc'] = str_replace(array('[BR]',"\n"),array(''," "),join('',$tag['cdata']));
        $test_url->fetch($link, 'url');
        $id = $test_url->in_database() ? $test_url->get_id() : NULL;
        $ra['link'][$id] = $link; 
        $matches = array();
        preg_match('@(Republic Act No)*([^0-9]*)([0-9]*)(.*)@i',$ra['desc'],$matches);
        $ra['linktext'] = 'RA' . str_pad(ltrim($matches[3],'0'),5,'0',STR_PAD_LEFT);
        $ra['desc'] = trim($matches[4]);

        $republic_act->fetch($ra['linktext'], 'sn');
        $found = $republic_act->in_database();
        $ra['cached_sn'] = $found ? $republic_act->get_id() : NULL;
        $id = NULL;
        if ( $found ) {
          $test_url->fetch($republic_act->get_url(), 'url');
          $found = $test_url->in_database();
          $id = $test_url->get_id();
        }
        $ra['link'][$id] = $republic_act->get_url();
        array_push($republic_acts, $ra);
        continue;
      }
      array_push($republic_acts, $ra);
    }/*}}}*/

    // Make the RA serial number be the resultset array key
    $republic_acts = array_combine(
      array_map(create_function('$a', 'return $a["linktext"];'),$republic_acts),
      $republic_acts
    );

    return $republic_acts;
  }/*}}}*/

  function parse_pdf_sys_republic_act(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/lis/pdf_sys.aspx?congress=15&type=republic_act 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for {$urlmodel} ---------------------------------------" );

    $republic_act = new RepublicActDocumentModel();
    $ra_parser    = new RepublicActParseUtility();
    $test_url     = new UrlModel();

    $ra_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $pagecontent,
        $urlmodel->get_response_header()
      );

    $pagetitle    = 'Republic Acts'; 
    $pagecontent  = ''; // join('',$ra_parser->get_filtered_doc());

    // Extract pager

    $pages = array();
    $pager_links = $ra_parser->extract_pager_links(
      $pages,
      $parser->cluster_urldefs,
      '1cb903bd644be9596931e7c368676982');

    $pagecontent .= join('', $pager_links);

    // Extract Congress selector

    $extracted_links = array();
    $congress_switcher = $ra_parser->extract_pager_links(
      $extracted_links,
      $parser->cluster_urldefs,
      '9f35fc4cce1f01b32697e7c34b397a99');

    $pagecontent .= "&nbsp;&nbsp;Congress: " . join('',$congress_switcher) . '<br/><br/>';

    $pages = array_flip(array_values(array_merge(
      array($urlmodel->get_url()),
      $pages
    )));

    $on_this_page = $this->fetch_legislation_links($ra_parser, $republic_act); 

    $dumped = FALSE;

    $linkset = array(); 
    foreach ( $pages as $page_url => $d ) {/*{{{*/
      $test_url->fetch($page_url,'url');
      if ( $test_url->in_database() ) {
        $url_id = $test_url->get_id();
        $this->syslog(__FUNCTION__,__LINE__, "(marker) ? Testing URL #{$url_id} {$page_url}");
        $pages[$page_url] = $urlmodel->get_id() != $url_id
          ? $url_id
          : NULL // We want to skip this page
          ;
        if ( is_null($pages[$page_url]) ) {
          $this->syslog(__FUNCTION__,__LINE__, "(marker) - Skipping URL #{$url_id} {$page_url}");
          $republic_acts = $on_this_page;
        } else {
          $content_length = $test_url->get_content_length();
          $this->syslog(__FUNCTION__,__LINE__, "(marker) * Loading URL #{$url_id} {$content_length} octets {$page_url}:");
          $ra_parser->
            reset()->
            set_parent_url($urlmodel->get_url())->
            parse_html($test_url->get_pagecontent(),$test_url->get_response_header());
          $republic_acts = $this->fetch_legislation_links($ra_parser, $republic_act); 
        }
      }
      krsort($republic_acts);
      if ( !$dumped ) {
        $dumped = TRUE;
        // $this->recursive_dump($republic_acts,'(marker) -');
      }
      foreach ( $republic_acts as $ra_number => $ra ) {
        // RA10174 =>
        //   link =>
        //      0 => http://www.senate.gov.ph/republic_acts/ra%2010174.pdf
        //      1 => http://www.congress.gov.ph/download/ra_15/RA10174.pdf
        //   desc => An Act Establishing the Peoples Survival Fund to Provide Long-Term Finance Streams to Enable the Government to Effectively Address the Problem of Climate Change
        //   linktext => RA10174
        //   aux =>
        //      0 => Approved by the President on August 16, 2012
        //   cached_url => 663
        //   cached_sn => 3501
        $links = array();
        foreach ($ra['link'] as $url_id => $url) {
          $host = UrlModel::parse_url($url,PHP_URL_HOST);
          $linktext = (0 == count($links)) ?  $ra['linktext'] : $host;
          $cached = 0 < intval($url_id) ? ' cached' : ' uncached';
          $urlhash = UrlModel::get_url_hash($url);
          $actual_link = <<<EOH
<a id="{$urlhash}" class="legiscope-remote{$cached}" href="{$url}">{$linktext}</a>
EOH;
 
          $links[] .= (0 == count($links))
            ? $actual_link
            : <<<EOH
Alternate {$actual_link}
EOH
            ;

        }
        if ( 0 < count($links) ) $linkset[$ra_number] .= '<li>' . join('&nbsp;',$links) . '</li>';
      }
      $republic_acts = array();
    }/*}}}*/
    krsort($linkset);
    $linkset = join("\n",$linkset);
    $linkset = <<<EOH
<ul class="link-cluster">
{$linkset}
</ul>
EOH;

    $pagecontent .= <<<EOH

{$linkset}

<script type="text/javascript">
jQuery(document).ready(function(){ jQuery('#doctitle').html('{$pagetitle}');});
</script>
EOH;


  }/*}}}*/

  function republic_act_user_template() {/*{{{*/
    return <<<EOH
<h1>{sn}</h1>
<h2>{title}</h2>
<span><b>Source: </b>{url}</span>
<hr/>
<h2>{description}</h2>
<hr/>
<div>{content}</div>
<div>
<h2>OCR Content</h2>
{ocrcontent.data}
</div>
EOH;
  }/*}}}*/

  function republic_act_user_template_ocrcap() {/*{{{*/
    return <<<EOH
<h1>{sn}</h1>
<h2>{title}</h2>
<span><b>Source: </b>{url}</span>
<h2>{description}</h2>
<hr/>
<h2>OCR Content</h2>
<div>{ocrcontent.data}</div>
EOH;
  }/*}}}*/

  function generate_admin_content(RepublicActDocumentModel & $republic_act) {/*{{{*/

    // Convert [content] or [ocrcontent.data] field to paragraphs
		$ocrcontent = nonempty_array_element($republic_act->get_ocrcontent(),'data',array());
		$ocrcontent = nonempty_array_element($ocrcontent,'data');
		$no_local_content = json_encode(array(
			0 => array('text' => 'No Data for ' . $republic_act->get_sn()),
			1 => array('text' => 'Only the original document is available at {{2}}'),
			2 => array(
				'url' => $republic_act->get_url(),
				'text' => $republic_act->get_url(),
			),
		));	

		if ( !is_null($republic_act->get_content()) ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) Content found, length " . mb_strlen($republic_act->get_content()));
			$content = @json_decode($republic_act->get_content(),TRUE);
      if ( FALSE == $content ) {
        $content = "No parsed content.";
      }
    }
    else if ( !is_null($ocrcontent) ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) Using OCR version");
			$content = explode("\n", str_replace(array("\r\n"),array("\n"),$ocrcontent));
    } 
    else {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) NO CONTENT. Using placeholder.");
			$content = $no_local_content;
		}

    if (is_array($content) && (0 < count($content))) {
      $content = $republic_act->format_document($content);
      $republic_act->set_content($content);
      $content = NULL;
      unset($content);
    }  
    else {
      $republic_act->set_content('No parsed content.');
    }

    if ( method_exists($republic_act,'prepare_ocrcontent') ) {
      $republic_act->prepare_ocrcontent();
      if (0) {
        $ocrcontent = $republic_act->get_ocrcontent();
        $content = nonempty_array_element($ocrcontent,'data');
        $content = nonempty_array_element($content,'data');
        $content = $republic_act->format_document($content);
        if ( !is_null($content) ) {
          $ocrcontent['data']['data'] = $content;
          $republic_act->set_ocrcontent($ocrcontent);
        }
        $content = NULL;
        $ocrcontent = NULL;
      }
    }
    else {/*{{{*/
      if (!is_null($republic_act->get_ocrcontent())) {
        $ocrcontent = $republic_act->get_ocrcontent();
        $ocrcontent = nonempty_array_element($ocrcontent,'data');
        $ocr_record_id = nonempty_array_element($ocrcontent,'id');
        if ( is_null($ocr_record_id) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) No OCR result available.");
          $republic_act->set_ocrcontent('No OCR available');
        }
        else {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) OCR content record #{$ocr_record_id} available.");
        }
      }
      else {
        $republic_act->set_ocrcontent('No OCR conversion available.');
      }
    }/*}}}*/

    $republic_act->permit_html_tag_delimiters = TRUE;
		return $republic_act->substitute($this->republic_act_user_template());
  }/*}}}*/

  function generate_descriptive_markup(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

		// Generate descriptive markup for an already-stored RA/Act record.
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $pagecontent = NULL;

    if ( !$urlmodel->in_database() ) $urlmodel->stow();

    $urlpath = urldecode($urlmodel->get_url());
    $urlpath = explode('/',UrlModel::parse_url($urlpath,PHP_URL_PATH));
    $ra = strtoupper(preg_replace('@[^RA0-9]@i','',array_pop($urlpath))); 
    $match = array();
    if ( 1 == preg_match('@([A-Z]{1,})([0-9]{1,})@i',$ra,$match) ) {
      $republic_act = new RepublicActDocumentModel();
      array_shift($match);
      $match = array_values($match);
      $match[1] = ltrim($match[1],'0'); 
      $ra_regex = join('(.*)',$match);
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Seek Republic Act {$ra} {$ra_regex}" );
      $republic_act->
        join_all()->
        where(array('AND' => array('`a`.sn' => "REGEXP '({$ra_regex})'")))->
        recordfetch_setup();
      $record = array();
			$ocr_queue_list = array();
      while ( $republic_act->recordfetch($record,TRUE) ) {
        if ( is_null($pagecontent) ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Republic Act {$ra} #{$record['id']}" );
          if ( $debug_method ) $this->recursive_dump($record,"(marker)");
          $pagecontent = $this->generate_admin_content($republic_act);
        }
				if ( is_array($record['content']) ) {
					$this->syslog( __FUNCTION__, __LINE__, "(marker) Document already stored.");
					if ( $debug_method ) $this->recursive_dump($record['content'],"(marker)");
				} 
				else if ( FALSE == @json_decode(nonempty_array_element($record,'content'),TRUE) ) {
					$this->syslog( __FUNCTION__, __LINE__, "(marker) Enqueue.");
					$ocr_queue_list[UrlModel::get_url_hash($record['url'])] = $record['id'];
				}
      }
			$ocr_dequeue_method = 'test_document_ocr_result';
			if ( method_exists($republic_act,$ocr_dequeue_method) ) {
        if ( $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) OCR queue check. Elements: " . count($ocr_queue_list));
          $this->recursive_dump($ocr_queue_list,"(marker)");
        }
				while ( 0 < count($ocr_queue_list) ) {
					$ocr_item = array_shift($ocr_queue_list);
					if ( $republic_act->set_id(NULL)->retrieve($ocr_item,'id')->in_database() ) {
						$republic_act->$ocr_dequeue_method('get_url');
					}
				}
			}
			else {
					$this->syslog( __FUNCTION__, __LINE__, "(marker) No method {$ocr_dequeue_method}.");
			}
    }

		if ( $debug_method ) {/*{{{*/
			$urlhash = UrlModel::get_url_hash($urlmodel->get_url());
			$urlmodel->
				where(array('AND' => array('urlhash' => $urlhash)))->
				recordfetch_setup();

			while ( $urlmodel->recordfetch($urlhash,TRUE) ) {
				$this->recursive_dump($urlhash,"(marker) + UrlModel ");
			}
		}/*}}}*/

    $parser->json_reply['httpcode'] = 200;
    $parser->json_reply['contenttype'] = 'text/html';
    $parser->json_reply['subcontent'] = $pagecontent;
    $pagecontent = NULL;

  }/*}}}*/

}


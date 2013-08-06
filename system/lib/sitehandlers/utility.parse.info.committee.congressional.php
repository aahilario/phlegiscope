<?php

/*
 * Class CongressionalCommitteeInfoParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeInfoParseUtility extends CongressCommonParseUtility {
  
  var $committee_information = array();
  var $current_component = NULL;
  var $committee_name = NULL;

  private $map_by_header = array();

  function __construct() {
    parent::__construct();
    $this->map_by_header = array(
      'jurisdiction'     => 'jurisdiction',
      'committee office' => 'office_address',
      'chairperson'      => 'chairperson',
      'membership'       => 'membership',
    );
    $this->committee_information = array(
      'representative' => array(),
    );
  }

  function ru_div_close(& $parser, $tag) {/*{{{*/
    // Hack to mark DIV containers of membership roster URLs in 
    // this->committee_information_page()
    $this->pop_tagstack();
    if ('main-ol' == array_element($this->current_tag['attrs'],'ID')) {
      unset($this->current_tag['attrs']['ID']);
      unset($this->current_tag['attrs']['CLASS']);
      $this->current_tag['children'][] = array(
        'header' => '__LEGISCOPE__',
        'seq' => $this->current_tag['attrs']['seq'],
      );
    }
    $this->push_tagstack();
    return parent::ru_div_close($parser,$tag);
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
    // FIXME:  Figure out a better way to filter Congress site DIV tags
    $ok = FALSE;
    $this->current_tag();
    $parent = NULL;
    $text = trim(join('',array_element($this->current_tag,'cdata')));
    if ( $this->debug_tags ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - Got {$text}");
    if ( !is_null($this->current_component) ) {/*{{{*/
      if ( !strlen(trim($text['header'])) ) {
      }
      else if ( !array_key_exists($this->current_component,$this->committee_information) ) {
        $this->committee_information[$this->current_component] = $text; 
      }
      else if ( is_array($this->committee_information[$this->current_component]) ) {
        $this->committee_information[$this->current_component][] = $text;
      }
      else {
        $this->committee_information[$this->current_component] = array($this->committee_information[$this->current_component]);
        $this->committee_information[$this->current_component][] = $text;
      }
    }/*}}}*/
    else {/*{{{*/
      // 15th Congress
      if ( 1 == preg_match('@\[BR\]@',$text) ) {
        $text = explode('[BR]', $text);
        if ( is_array($text) ) array_walk($text,create_function(
          '& $a, $k', '$a = trim($a);'
        ));
        $text = array_filter($text);
      }
      $text = array(
        'text' => $text,
        'header' => '__LEGISCOPE__',
        'seq'  => $this->current_tag['attrs']['seq'],
      );
      if ( $this->tag_stack_parent($parent) && !is_null($parent) && ('DIV' == array_element($parent,'tag')) && ('padded' == array_element($parent['attrs'],'CLASS'))) { 
        if ( !empty($text['text']) && !('meta' == array_element($this->current_tag['attrs'],'CLASS')) ) {
          $ok = TRUE;
        }
      }
      // Retain headings ("Member for the Majority", etc.)
      $ok |= (1 == preg_match('@(' . join('|',array(
          'silver_hdr',
          'mem_info',
        )) . ')@i', array_element($this->current_tag['attrs'],'CLASS'))
      );
      if ( $ok ) $this->add_to_container_stack($text);
    }/*}}}*/
    return $ok;
  }/*}}}*/

  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $text = trim(join('',array_element($this->current_tag,'cdata')));
    $paragraph = array(
      'text' => $text, 
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    // Label a SPAN[class*=mem-head] for selection in
    // this->committee_information_page()
    if (1 == preg_match('@(' . join('|',array(
        'mem-head',
      )) . ')@i', array_element($this->current_tag['attrs'],'CLASS'))
    ) {
      $paragraph['header'] = 'committee name';
    }
    if (0 < strlen(trim($paragraph['text'])))
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  function ru_strong_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->current_component = NULL;
    return TRUE;
  }/*}}}*/
  function ru_strong_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_strong_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    // These line prefixes are captured BEFORE the paragraph tag is closed. 
    $text = array(
      'header' => trim(join('',array_element($this->current_tag,'cdata'))),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    $match = array();
    if ( 1 == preg_match('@^('.join('|',array_keys($this->map_by_header)).')@i', $text['header'], $match) ) {
      $key = array_element($this->map_by_header, strtolower($match[1]));
      if ( $this->debug_tags ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - Matched {$match[1]} <- {$key}");
      $this->current_component = $key;
      return FALSE; // Discard container
    }
    return TRUE;
  }/*}}}*/

  function parse_committee_information_page_15(UrlModel & $urlmodel, CongressionalCommitteeDocumentModel & $committee) {/*{{{*/
    // 15th Congress parser
    // Take children of DIVs with CSS class 'padded', and filter for the one
    // containing the string 'JURISDICTION' in a STRONG tag (key for entry being 'header')
    $debug_method = FALSE;

    $this->committee_information = array();

    $selector  = 'children[tagname*=p|div|i]'; 
    $structure = array_values(array_intersect_key(
      $this->get_containers($selector),
      array_flip(array_keys(array_filter($this->get_containers("{$selector}{#[header*=jurisdiction|committee office|legiscope|committee name|i]}"))))
    ));
    $roster_list = array_values(array_intersect_key(
      $this->get_containers($selector),
      array_flip(array_keys(array_filter($this->get_containers("{$selector}[id*=main-ol]"))))
    ));

    $all_elements = array();
    ksort($roster_list);
    while ( 0 < count($structure) )   foreach ( array_pop($structure) as $seq => $cell ) $all_elements[$seq] = $cell;
    while ( 0 < count($roster_list) ) foreach ( array_pop($roster_list) as $seq => $cell ) $all_elements[$seq] = $cell;

    unset($structure);
    unset($roster_list);

    ksort($all_elements);

    // Replace string fragments of URL title with code-friendly keys

    $map_by_title = array( // Sections in the Committee Information page
      'member_list'        => 'complete roster' , // JOIN
      'bills_referred'     => 'bills referred'  , // JOIN
      'committee_meetings' => 'schedule of meetings'   , // Unmapped
      'chairperson'        => 'more info about' , // JOIN
      'member_entry'       => 'about our member',
      'contact_form'       => 'contact form'    ,
    );

    $map_by_membership_class = array( // Sections in the membership roster page
      'member-majority'  => 'MEMBER FOR THE MAJORITY',
      'member-minority'  => 'MEMBER FOR THE MINORITY',
      'vice-chairperson' => 'VICE CHAIRPERSON',
    );

    $map_by_header = array(
      'jurisdiction'     => 'jurisdiction',
      'committee office' => 'office_address',
    );

    $committee_information = array();

    // Extract nested array entries
    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - --- --- - - -");

    // Remap some names
    array_walk($all_elements,create_function(
      '& $a, $k, $s', 'if ( array_key_exists("title",$a) ) { $key = preg_replace(array_map(create_function(\'$a\',\'return "@((.*)({$a})(.*))@i";\'),$s),array_keys($s),$a["title"]); if (array_key_exists($key,$s)) { $a["title"] = $key; $a["text"] = "std-label"; } if ( array_key_exists("image",$a) ) $a = NULL; }'
    ),$map_by_title);
    array_walk($all_elements,create_function(
      '& $a, $k, $s', 'if ( is_array($a) && array_key_exists("text",$a) && is_string($a["text"]) ) { $key = preg_replace(array_map(create_function(\'$a\',\'return "@((.*)({$a})(.*))@i";\'),$s),array_keys($s),$a["text"]); if (array_key_exists($key,$s)) { $a["title"] = $key; $a["text"] = "std-label"; } }'
    ),$map_by_membership_class);

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - --- --- - - ->");
      $this->recursive_dump($all_elements,"(marker) - - --");
    }/*}}}*/

    $roster_entry_stack = array();
    $capture_next_image = FALSE;

    while ( 0 < count($all_elements) ) {/*{{{*/
      $cell = array_pop($all_elements);
      if ( $debug_method ) $this->recursive_dump($cell,"(marker) + + + +");
      if ( !is_array($cell) ) continue;
      // Ignore image URLs
      if ( array_key_exists('image', $cell) ) {
        if ( $capture_next_image == FALSE ) continue;
        $committee_information[$capture_next_image] = $cell['image'];
        continue;
      }
      // Process JURISDICTION and CHAIRPERSON chunks
      $header = array_element($cell,'header');
      if ( 1 == preg_match('@CHAIRPERSON@i', $header) ) continue;
      // Store jurisdiction / address data
      if ( 1 == preg_match('@^('.join('|',array_keys($map_by_header)).')@i',$header) ) {/*{{{*/
        $new_key = preg_replace(
          array_map(create_function('$a','return "@^((.*){$a}(.*))@i";'), array_keys($map_by_header)),
          array_values($map_by_header),
          $header
        );
        $text = array_pop($all_elements);
        $next = array_pop($all_elements);
        if ( array_element($next,'header') == 'committee name') {
          $committee_information['committee_name'] = array_element($next,'text');
        } else if (array_key_exists('url',$next) && !(array_element($next,'text') == 'std-label')) {
          $committee_information['committee_name'] = array_element($next,'text');
        } else {
          array_push($all_elements, $next);
        }
        $text = array_element($text,'text');
        if ( !array_key_exists($new_key,$committee_information) && (is_array($text) || (0 < strlen($text))) )
          $committee_information[$new_key] = $text;
        continue;
      }/*}}}*/
      $title = array_element($cell,'title');
      // Store roster entries
      if ( 1 == preg_match('@^('.join('|',array_keys($map_by_membership_class)).')@i',$title) ) {/*{{{*/
        $committee_information[$title] = $roster_entry_stack;
        $roster_entry_stack = array();
        continue;
      }/*}}}*/
      // Store meta URLs
      $capture_next_image = FALSE;
      if ( array_key_exists('url',$cell) && (array_element($cell,'text') == 'std-label') ) {/*{{{*/
        switch ( array_element($cell, 'title') ) {
          case 'member_entry':
            $roster_entry_stack[] = $cell['url'];
          case 'contact_form':
            $committee_information[$title] = array_element($cell, 'onclick-param');
            break;
          case 'chairperson':
            $capture_next_image = 'chairperson-avatar';
            $committee_information[$title] = array_element($cell,'url');
            break;
          default:
            $committee_information[$title] = array_element($cell,'url');
            break;
        }
      }/*}}}*/
    }/*}}}*/

    $this->committee_information = array_filter($committee_information);

    if ( $debug_method ) $this->recursive_dump($this->committee_information,"(marker) - - - - - - -");

    if ( array_key_exists('committee_name', $this->committee_information) ) {
      $committee_name = array_element($this->committee_information,'committee_name');
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name raw   {$committee_name}");
      $committee_name = LegislationCommonParseUtility::committee_name_regex($committee_name);
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name regex {$committee_name}");
      $committee->fetch(array('committee_name' => "REGEXP '({$committee_name})'"),'AND');
    }

    return $this;
  }/*}}}*/

  function ru_h2_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_h2_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_h2_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if ( 'mainheading' == array_element($this->current_tag['attrs'],'CLASS') ) {
      $committee_name = trim(join('',array_element($this->current_tag,'cdata')));
      $this->committee_information['committee_name'] = $committee_name;
      if ( $this->debug_tags ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - Committee {$committee_name}");
    }
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    $url = $this->current_tag['attrs']['HREF'];
    $url = str_replace('/?','?',$url);
    $this->current_tag['attrs']['HREF'] = $url;
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
    if ( !is_null($this->current_component) ) {
      if ( $this->current_component == 'chairperson' ) {
        // Take only the link
        $this->committee_information['representative']['bio_url']  = $link_data['url'];
        $this->committee_information['representative']['fullname'] = $link_data['text'];
        $this->current_component = NULL;
      }
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  "--- {$this->current_tag['tag']}" );
    return TRUE;
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
    $skip = TRUE;
    $this->pop_tagstack();
    if ( 1 == preg_match('@(chairperson)@',$this->current_component) ) $skip = FALSE;
    if ( !$skip ) {
      $this->committee_information['representative']['avatar_url'] = $this->current_tag['attrs']['SRC'];
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function parse_committee_information_page(UrlModel & $urlmodel, CongressionalCommitteeDocumentModel & $committee) {/*{{{*/
    // 16th Congress parser
    // 2013 July 28: Obtain jurisdiction and office address from markup.
    $debug_method = TRUE;

    $map_by_title = array( // Sections in the Committee Information page
      'member_list'        => 'complete roster' , // JOIN
      'bills_referred'     => 'bills referred'  , // JOIN
      'committee_meetings' => 'schedule of meetings'   , // Unmapped
      'chairperson'        => 'more info about' , // JOIN
      'member_entry'       => 'about our member',
      'contact_form'       => 'contact form'    ,
    );

    $map_by_membership_class = array( // Sections in the membership roster page
      'member-majority'  => 'MEMBER FOR THE MAJORITY',
      'member-minority'  => 'MEMBER FOR THE MINORITY',
      'vice-chairperson' => 'VICE CHAIRPERSON',
    );

		$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - - - - - - -");
    if ( $debug_method ) $this->recursive_dump($this->committee_information,"(marker) - - Parsed CommInfo - - - - -");

    if ( array_key_exists('committee_name', $this->committee_information) ) {
      $committee_name = array_element($this->committee_information,'committee_name');
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name raw   {$committee_name}");
      $committee_name = LegislationCommonParseUtility::committee_name_regex($committee_name);
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name regex {$committee_name}");
			$constraints = array('committee_name' => "REGEXP '({$committee_name})'");
			$committee->fetch($constraints,'AND');
    }

    return $this;
  }/*}}}*/

  function get_committee_name() {/*{{{*/
    $attrname = preg_replace('@^get_@i', '', __FUNCTION__);
    return array_element($this->committee_information, $attrname);
  }/*}}}*/

  function get_chairperson() {/*{{{*/
    $attrname = preg_replace('@^get_@i', '', __FUNCTION__);
    return array_element($this->committee_information, $attrname);
  }/*}}}*/

  function generate_default_markup(& $pagecontent, & $committee_record, & $committee, $s) {/*{{{*/

    $this->syslog(__FUNCTION__,__LINE__,"(marker)  Generating page");
    $this->recursive_dump(array_keys($s), "(marker) - - -");
    extract($s);

    $chairperson          = array_element($committee_record,'representative');
    $chairperson          = array_element($chairperson,'data');
    $chairperson_avatar   = array_element($chairperson,'avatar_image');
    $chairperson_image    = array_element($chairperson,'avatar_url');
    $chairperson_bio_url  = array_element($chairperson,'bio_url');
    $chairperson_fullname = array_element($chairperson,'fullname');
    $office_address       = join('<br/>',$committee->get_office_address());
    $contact_details      = nonempty_array_element($chairperson,'contact_json',array());

    if ( is_string($contact_details) ) $contact_details = @json_decode($contact_details,TRUE);

    // Representative contact details markup
    if ( is_array($contact_details) ) {/*{{{*/
      $contact_details = array_filter($contact_details);
      array_walk($contact_details, create_function(
        '& $a, $k', '$a = "<b>{$k}</b>: {$a}";'
      ));
      $contact_details = join('<br/>', $contact_details); 
    }/*}}}*/

    // Selection tabs
    $tab_options     = array(
      'bills_referred'     => 'Bills Referred',
      'member_list'        => 'Committee Members',
      'committee_meetings' => 'Meetings',
    );
    ksort($tab_options);
    $link_members = array_intersect_key(
      $this->committee_information,
      $tab_options
    );
    ksort($link_members);

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($tab_options, "(marker) - - -- -- - T");
      $this->recursive_dump($link_members, "(marker) - - -- -- - M");
    }/*}}}*/

    if (is_array($link_members) && is_array($tab_options) && (count($link_members) == count($tab_options))) {/*{{{*/

      // Store Joins between child page URLs and the current committee record
      // Generate tab markup
      $tab_links = array_combine(array_values($tab_options),array_values($link_members));
      array_walk($tab_links, create_function(
        '& $a, $k, $s', '$hash = UrlModel::get_url_hash($a); $a = "<li id=\"{$s[$k]}\"><a class=\"legiscope-content-tab\" id=\"{$hash}\" href=\"{$a}\">{$k}</a>";'
      ), array_flip($tab_options));

      $tab_links = join('',array_values($tab_links));

      $tab_containers = array();
      foreach ( array_flip($tab_options) as $id ) {/*{{{*/
        $properties = array('congress-committee-info-tab');
        if ( 0 < count($tab_containers) ) $properties[] = 'hidden';
        $properties = join(' ',$properties);
        $tab_containers[] = <<<EOH
<div class="{$properties}" id="tab_{$id}"></div>

EOH;
      }/*}}}*/
      $tab_containers = join('',$tab_containers);

      $this->recursive_dump($tab_links, "(marker) - - -- -- - Links");
    }/*}}}*/

    $pagecontent = <<<EOH
<h2>{$committee_name}</h2>
<hr/>
{$jurisdiction}
<br/>
<br/>
<div class="committee-contact-block-right clear-both">{$office_address}</div>
<div class="clear-both"></div>
<hr/>
<img
  class="legiscope-remote bio-avatar"
  width="120" height="120" border="1" 
  href="{$chairperson_image}" 
  alt="{$chairperson_fullname}" 
  src="{$chairperson_avatar}"
>
<div class="committee-contact-block">
  <a class="legiscope-remote" href="{$chairperson_bio_url}">{$chairperson_fullname}</a><br/>
  {$contact_details}
</div>
<br/>
<div class="theme-options clear-both">
  <hr/>
  <ul>{$tab_links}</ul>
  <div class="congress-committee-info-tabs">{$tab_containers}</div>
</div>

EOH;
    $pagecontent .= $this->std_committee_detail_panel_js(); 
    // bills_referred
    // committee_meetings
  }/*}}}*/

  function std_committee_detail_panel_js() {/*{{{*/
    return <<<EOH

<script type="text/javascript">
function initialize_committee_detail_triggers() {
  jQuery('a[class*=legiscope-content-tab]').click(function(e) {
    e.stopPropagation();
    e.preventDefault();
    var parent_id = jQuery(this).parent('li').attr('id');
    var self_id = jQuery(this).attr('id');
    var url = jQuery(this).attr('href');
    jQuery('div[class=congress-committee-info-tabs]').children().each(function(){
      jQuery(this).addClass('hidden');
    });
    jQuery('div[class=congress-committee-info-tabs]').find('div[id=tab_'+parent_id+']').each(function(){
      jQuery(this).removeClass('hidden');
      var tab_id = 'tab_'+parent_id; 
      if ( !jQuery(this).hasClass('loaded') ) {
        load_content_window(
          url,
          jQuery('#seek').prop('checked'),
          jQuery('a[id='+self_id+']'),
          { url : url, defaulttab : parent_id, async : true },
          { success : (function(data, httpstatus, jqueryXHR) {
              jQuery('div[id='+tab_id+']').addClass('loaded').html(data && data.subcontent ? data.subcontent : 'No content retrieved');
              if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
              setTimeout((function(){initialize_committee_detail_triggers();}),300);
            })
          }
        );
      }
    });
    return false;
  });
  initialize_remote_links();
}
jQuery(document).ready(function(){
  initialize_committee_detail_triggers();
});
</script>

EOH;
  }/*}}}*/

  function dump_member_list(& $pagecontent, UrlModel & $urlmodel, CongressionalCommitteeDocumentModel & $committee) {/*{{{*/

    $debug_method = FALSE;

    $doc_parser = new CongressMemberBioParseUtility();
    $doc_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    if ( $debug_method ) $this->recursive_dump($doc_parser->get_containers(),"(marker) - - - Raw");

    extract($doc_parser->extract_committee_membership_and_bills('children[tagname=div][id=main-ol]'));

    if ( $debug_method ) $this->recursive_dump($membership_role, "(marker) -+-+- Raw");
    // Partition the membership/role list
    $pagecontent = '';
    foreach ($membership_role as $classification => $membership_list) {

      $classification = ucwords($classification);

      $pagecontent .= <<<EOH
<span><b>{$classification}</b></span>

EOH;
      $pagecontent .= $doc_parser->generate_committee_membership_markup($membership_list);
      $pagecontent .= <<<EOH
<br/>

EOH;
    }

  }/*}}}*/

  function dump_referred_bill_listing(& $pagecontent, UrlModel & $urlmodel, CongressionalCommitteeDocumentModel & $committee) {/*{{{*/

    $debug_method = FALSE;

    $m = new RepresentativeDossierModel();
    $doc_parser = new CongressMemberBioParseUtility();
    $doc_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    extract($doc_parser->extract_committee_membership_and_bills());

    if ( $debug_method ) {/*{{{*/
      $committee_name = $committee->get_committee_name();
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - -- - BEGIN Bills '{$committee_name}' n = " . count($bills));
      $this->recursive_dump($bills,'(marker) - - - - - - Bills - - ->');
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - -- - END Bills '{$committee_name}' n = " . count($bills));
    }/*}}}*/

    $legislation_links = array();

    $pagecontent = $doc_parser->generate_legislation_links_markup($m, $legislation_links, $bills);

  }/*}}}*/

  function committee_information_page(& $stdparser, & $pagecontent, & $urlmodel) {/*{{{*/

    // Committee name URLs have changed.  We will need to use $stdparser->trigger_linktext to
    // select the committee root record.
    // - 15th Congress: http://www.congress.gov.ph/committees/search.php?congress=15&id=0501 
    // - 16th Congress: http://www.congress.gov.ph/committees/search.php?id=0505

    /* Parse a committee information page to obtain these items of information:
     * - Committee jurisdiction and contact information
     * - List of members
     * - Bills referred to the Committee
     * - Committee meetings
     */
    $debug_method = TRUE;

    $urlmodel->ensure_custom_parse();
    $committee      = new CongressionalCommitteeDocumentModel();
    $admincontact   = new CongressionalAdminContactDocumentModel();
    $representative = new RepresentativeDossierModel();

    $congress_tag   = $urlmodel->get_query_element('congress',C('LEGISCOPE_DEFAULT_CONGRESS'));

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for Congress {$congress_tag} " . $urlmodel->get_url() );

    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $pagecontent = str_replace('[BR]','<br/>',join('',$this->get_filtered_doc()));

    $stdparser->json_reply['retainoriginal'] = TRUE;
    $stdparser->json_reply['subcontent'] = $pagecontent;

    if ( $congress_tag == 15 ) {
      $this->parse_committee_information_page_15($urlmodel, $committee);
    }
    else {
      $this->parse_committee_information_page($urlmodel, $committee);
    }

    $committee_id     = $committee->get_id();
    $committee_name   = $committee->get_committee_name();

    //////////////////////////////////////////////////////////////////////
    // Bail out if a committee ID cannot be derived from parsed content
    //
    if ( is_null($committee_id) ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Parsed attributes " . get_class($committee));
      $this->recursive_dump($this->committee_information,"(marker) - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - No committee ID found, cannot proceed.");
      return;
    }/*}}}*/

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - Committee Name: {$committee_name} #{$committee_id}");

    // Fetch representative record
    $representative_name    = $this->committee_information['representative']['fullname'];
    $representative_record  = $representative->fetch_by_fullname($representative_name);
    $representative_id      = array_element($representative_record,'id');
    $representative_joinrec = NULL;

    if ( !is_null($representative_id) ) {/*{{{*/
      $representative_joinrec = array($representative_id => array(
        'role' => 'chairperson',
        'congress_tag' => $congress_tag, 
      ));
      $this->recursive_dump($representative_joinrec,"(marker) -- JR --");
    }/*}}}*/

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - Chairperson for {$congress_tag}th Congress {$committee_name} #{$committee_id}: {$representative_record['fullname']} (#{$representative_id})");

    $committee_record = NULL;

    if ( !is_null($committee_id) && (0 < strlen($committee_name)) ) {/*{{{*/// Retrieve existing committee record

      $join_conditions = array_filter(array(
        'committee_name' => "REGEXP '".LegislationCommonParseUtility::committee_name_regex($committee_name)."'",
        '{representative}.`role`' => 'chairperson',
        '{representative}.`congress_tag`' => $congress_tag,
        '{representative_representative_dossier_model}.`id`' => $representative_id,
      ));

      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Load Committee record with constraints:" );
        $this->recursive_dump($join_conditions, "(marker) - - -- JC -- - ");
      }

      $committee->debug_final_sql = FALSE;
      $committee->
        join(array('representative'))->
        where(array('AND' => $join_conditions))->
        recordfetch_setup();
      $committee->debug_final_sql = FALSE;
      $r = array();

      while ( $committee->recordfetch($r) ) {/*{{{*/
        if ( is_null($committee_record) ) {
          $committee_record = $r;
          $committee_id = array_element($r,'id');
        }
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($committee) . " JOIN search result");
          $this->recursive_dump($committee_record, "(marker) - - -- -- - Z");
        }/*}}}*/
      }/*}}}*/

      if ( !is_null($representative_id) && (is_null($committee_record) || is_null(array_element($committee_record['representative'],'join'))) ) {
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Creating chairperson Join to {$representative_record['fullname']} (#{$representative_id}) for {$congress_tag}th Congress {$committee_name} #{$committee_id}");
        $rep_join_record = $committee->create_joins('RepresentativeDossierModel', $representative_joinrec); 
        if ( $debug_method ) $this->recursive_dump($rep_join_record,"(marker) -- RJ --");
        // Reload or update committee record as needed
        if ( is_null($committee_record) ) {/*{{{*/// Reload
          $match_constraints = array(
            'id' => $committee_id,
            '{representative}.`role`' => 'chairperson',
            '{representative}.`congress_tag`' => $congress_tag,
            '{representative_representative_dossier_model}.`id`' => $representative_id,
          );
          $committee_record = $committee->
            join_all()->
            fetch($match_constraints,'AND');
          if ( $debug_method ) {/*{{{*/
            $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - Reloaded committee record #{$committee_id}");
            $this->recursive_dump($match_constraints, "(marker) - - - MC");
            $this->recursive_dump($committee_record, "(marker) - - - CR");
          }/*}}}*/
        }/*}}}*/
        else {/*{{{*/// Update
          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - Updating in-memory committee record #{$committee_id}");
          $committee_record['representative'] = array(
            'join' => array_merge(
              $representative_joinrec[$representative_id],
              array('id' => $representative_id)
            ),
            'data' => $representative_record,
          );
        }/*}}}*/
      }

      // Transfer office address record to joined record
      if ( is_null(array_element($committee_record,'office_contact')) ) {/*{{{*/
      }/*}}}*/
      else if ( !is_null(array_element($committee_record['office_contact'],'data')) ) {/*{{{*/
      }/*}}}*/
      else if ( !is_string($committee_record['office_address']) ) {
      }
      else if ( !(FALSE == @json_decode($committee_record['office_address'],TRUE)) ) {/*{{{*/
        // TODO: Create contact_info (office address) Join record and referent record as needed.
        // TODO: Modify update()/insert() to recognize this request format, ignoring preexisting 'data' and 'join' records.
        $data = array(
          'join' => array(
            'congress_tag' => $congress_tag,
            'create_time' => time(),
          ),
          'data' => array(
            'office_address' => array_element($committee_record,'office_address'),
            'office_designation' => 'main-office',
            'congress_tag' => $congress_tag,
            'create_time' => time(),
          ),
        );
        $committee->set_contents_from_array(array(
          'office_contact' => $data
        ));
      }/*}}}*/
    }/*}}}*/

    $updated = FALSE;
    $jurisdiction = array_element($this->committee_information,'jurisdiction');
    if ( !is_null($jurisdiction) && $committee->in_database() && !($jurisdiction == $committee->get_jurisdiction()) ) {
      $committee->set_jurisdiction($jurisdiction);
      $updated = TRUE;
    }

    $office_address = array_element($this->committee_information,'office_address');
    $office_address = explode('[BR]',$office_address);
    if ( is_array($office_address) ) {/*{{{*/
      array_walk($office_address,create_function('& $a, $k', '$a = trim(preg_replace("@[^-A-Z0-9ñ,. ]@i","",$a));'));
      $office_address = array_values(array_filter($office_address));
    }/*}}}*/
    $this->committee_information['office_address'] = $office_address;

    if ( !is_null($office_address) && $committee->in_database() && is_null($committee->get_office_address()) ) {
      $committee->set_office_address($office_address);
      $updated = TRUE;
    }

    if ( $updated ) {
      $committee_id = $committee->set_last_fetch(time())->stow();
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - Stowed changes to {$committee_name} #{$committee_id}");
    }

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($representative) . " JOIN search result");
      $this->recursive_dump($representative_record, "(marker) - - -- -- - R");

      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Parsed attributes " . get_class($committee));
      $this->recursive_dump($this->committee_information,"(marker) - - - - -");

      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($committee) . " JOIN search result");
      $this->recursive_dump($committee_record, "(marker) - - -- -- - C");
    }/*}}}*/

    //////////////////////////////////////////////////////////////////////
    // Create URL-independent joins between Committees and Representative records
    $membership_roles = array_intersect_key($this->committee_information,array_flip(array(
      'member-minority',
      'member-majority',
      'vice-chairperson',
    )));

    // A list of Representative bio URLs results from the parsing stage above.
    if ( 0 < count($membership_roles) ) foreach ( $membership_roles as $role => $urls ) {/*{{{*/
      unset($this->committee_information[$role]);
      $rep_ids = array();
      while ( 0 < count($urls) ) {/*{{{*/
        $rep_urls = array();
        while ( count($rep_urls) < 10 && 0 < count($urls) ) {
          $url           = array_pop($urls);
          $rep_urls[]    = $url;
          $rep_ids[$url] = NULL;
        } 
        // If a Join matching the role and committee already exists, NULL out the
        // corresponding entry in $rep_urls; otherwise assign the representative record ID 
        $representative->debug_method = FALSE;
        if ( $representative->join_all()->where(array('AND' => array('bio_url' => $rep_urls)))->recordfetch_setup() ) {/*{{{*/

          $representative->debug_method = FALSE;

          $rep_urls = array();

          while ( $representative->recordfetch($rep_urls,TRUE) ) {/*{{{*/

            $bio_url = array_element($rep_urls, 'bio_url');
            $rep_id  = intval(array_element($rep_urls,'id'));

            if ( !($rep_id > 0) ) continue;

            $data_component = $representative->get_committees('data');
            $join_component = $representative->get_committees('join');

            if (
              (array_element($data_component,'committee_name') == $committee_name) && 
              (array_element($join_component,'role') == $role) ) {
                // WARNING: If URL formats change so that the 'congress' query component is lost, 
                // then this will fail to capture the Congress number for the join.
                if ( $debug_method ) $this->syslog( __FUNCTION__,__LINE__,"(marker) - - - - Omitting already-linked rep {$rep_id} ({$rep_urls['fullname']} - ".$representative->get_bio_url().")");
                unset($rep_ids[$bio_url]);
              }

            if ( is_null(array_element($rep_ids,$bio_url)) && array_key_exists($bio_url, $rep_ids) ) 
              $rep_ids[$bio_url] = array(
                'id' => $rep_id,
                'fullname' => array_element($rep_urls,'fullname','--'),
                'congress_tag' => UrlModel::query_element('congress',$bio_url)
              ); 

          }/*}}}*/

          $rep_ids = array_filter($rep_ids);

          while ( 0 < count($rep_ids) ) {/*{{{*/
            // Create missing Committee - Representative joins. Pop IDs from the list generated above.
            $rep_id = array_pop($rep_ids);
            $r = array(array_element($rep_id,'id') => array(
              'role' => $role,
              'congress_tag' => array_element($rep_id,'congress_tag','--'),
            ));

            if ( $debug_method )
              $this->syslog( __FUNCTION__,__LINE__,"(marker) - - - - Adding link to {$role} representative #{$rep_id['id']} ({$rep_id['fullname']})");
            $committee->create_joins('representative',$r);
          }/*}}}*/

        }/*}}}*/
      }/*}}}*/
    }/*}}}*/

    $committee_detail_panel = $this->filter_post('defaulttab');

    if ( !is_null($committee_detail_panel) ) {
      switch ($committee_detail_panel) {
      case 'bills_referred': 
        return $this->dump_referred_bill_listing($pagecontent, $urlmodel, $committee);
        break;
      case 'committee_meetings':
        //$pagecontent = 'Committee Meetings';
        break;
      case 'member_list':
        return $this->dump_member_list($pagecontent, $urlmodel, $committee);
        break;
      default: 
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - -- - Unhandled request for committee detail panel '{$committee_detail_panel}'");
      }
      return;
    }

    $s = compact('committee_name', 'jurisdiction', 'tab_links');

    if ( $debug_method ) $this->recursive_dump($s, "(marker) - - -- -- - COMPACT");

    $this->generate_default_markup(
      $pagecontent, 
      $committee_record, 
      $committee,
      $s
    );

    $pagecontent .= <<<EOH

<script type="text/javascript">
jQuery(document).ready(function(){

  jQuery('div[class=congress-committee-info-tab]').children().remove();
  jQuery('div[class=congress-committee-info-tabs]').children().append(jQuery(document.createElement('IMG'))
    .attr('src','data:image/gif;base64,R0lGODlhEAAQALMAAP8A/7CxtXBxdX1+gpaXm6OkqMnKzry+womLj7y9womKjwAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQBCgAAACwAAAAAEAAQAAAESBDICUqhmFqbZwjVBhAE9n3hSJbeSa1sm5HUcXQTggC2jeu63q0D3PlwAB3FYMgMBhgmk/J8LqUAgQBQhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAES3DJuUKgmFqb5znVthQF9h1JOJKl96UT27oZSRlGNxHEguM6Hu+X6wh7QN2CRxEIMggExumkKKLSCfU5GCyu0Sm36w3ryF7lpNuJAAAh+QQBCgALACwAAAAAEAAQAAAESHDJuc6hmFqbpzHVtgQB9n3hSJbeSa1sm5GUIHRTUSy2jeu63q0D3PlwCx1lMMgQCBgmk/J8LqULBGJRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYyhmFqbpxDVthwH9n3hSJbeSa1sm5HUMHRTECy2jeu63q0D3PlwCx0FgcgUChgmk/J8LqULAmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuYSgmFqb5xjVthgG9n3hSJbeSa1sm5EUgnTTcSy2jeu63q0D3PlwCx2FQMgEAhgmk/J8LqWLQmFRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJucagmFqbJ0LVtggC9n3hSJbeSa1sm5EUQXSTYSy2jeu63q0D3PlwCx2lUMgcDhgmk/J8LqWLQGBRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuRCimFqbJyHVtgwD9n3hSJbeSa1sm5FUUXSTICy2jeu63q0D3PlwCx0lEMgYDBgmk/J8LqWLw2FRhV6z2q0VF94iJ9pOBAAh+QQBCgALACwAAAAAEAAQAAAESHDJuQihmFqbZynVtiAI9n3hSJbeSa1sm5FUEHTTMCy2jeu63q0D3PlwCx3lcMgIBBgmk/J8LqULg2FRhV6z2q0VF94iJ9pOBAA7')
    .addClass('center')
    .attr('id','busy-notification-wait')
  );
  jQuery('li[id=bills_referred]').find('a').click();

});
</script>

EOH;

  }/*}}}*/

}

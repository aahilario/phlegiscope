<?php

/*
 * Class CongressMemberListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressMemberListParseUtility extends CongressCommonParseUtility {

  var $member_list = array();
  
  function __construct() {
    parent::__construct();
  }

   /** **/

 function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->is_bill_head_container = FALSE;
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tag, $attrs);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    // Parsing of name + bailiwick rows moved into this method
    $this->current_tag();
    // Get container contents 
    $cells = NULL;
    $container_item_hash = $this->stack_to_containers();
    if ( !is_null( $container_item_hash ) ) {
      extract($container_item_hash);
      $cells = nonempty_array_element($container,'children');
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    if ( is_array($cells) && ( 0 < count($cells) ) ) {
      $row = array();
      while ( 0 < count($cells) ) {
        $cell = array_shift($cells);
        if ( !is_array($cell) ) continue;
        if ( array_key_exists('url', $cell) ) {
          $row['url'] = $cell['url'];
          $row['fullname'] = $cell['text'];
          $row['title'] = $cell['title'];
          continue;
        }
        if ( array_key_exists('text', $cell) && array_key_exists('fullname',$row) ) {
          $row['bailiwick'] = $cell['text'];
        }
      }
      $this->member_list[] = $row;
      return FALSE;
    }
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $cell_type  = array(
      'text' => trim(join('',array_filter($this->current_tag['cdata']))),
      'seq'  => array_element($this->current_tag['attrs'],'seq')
    );
    $this->push_tagstack();
    if ( 0 < strlen($cell_type['text']) ) $this->add_to_container_stack($cell_type);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  /** **/

  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

		$debug_method = TRUE;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $member = new RepresentativeDossierModel();

    $urlmodel->ensure_custom_parse();
    $this->debug_tags = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $pagecontent      = array();
    $surname_initchar = '--';
    $section_break    = NULL;
    $districts        = array();

    $lc_surname_initchar = '';

    array_walk($this->member_list,create_function(
      '& $a, $k, $s', '$a["parsed_name"] = $s->parse_name($a["fullname"]); $given = explode(" ",$a["parsed_name"]["given"]); $given = array_shift($given); $a["fullname_regex"] = "{$a["parsed_name"]["surname"]}(.*){$given}";'
    ), $member);

    $member_matched = NULL;
    $now = time();

    foreach ( $this->member_list as $item ) {/*{{{*/

      $bio_url     = $item['url'];
      $fullname    = $this->reverse_iconv($item['fullname']);

			if ( !(0 < strlen($fullname) ) ) {
				$this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping entry");
				$this->recursive_dump($item,"(marker) - ");
			}

      $parsed_name = $item['parsed_name'];

      $member->fetch(array(
        'fullname' => "REGEXP '({$item['fullname_regex']})'"
      ),'AND');

      $member_matched = $member->in_database() ? $member->get_id() : NULL;

      if ( is_null($member_matched) ) {/*{{{*/
        $data = array( 
          'id'           => NULL,
          'avatar_url'   => NULL,
          'avatar_image' => NULL,
          'member_uuid'  => NULL,
          'contact_json' => NULL,
          'fullname'     => $fullname,
          'bio_url'      => $bio_url,
          'firstname'    => array_element($parsed_name,'given'),
          'mi'           => array_element($parsed_name,'mi'),
          'surname'      => array_element($parsed_name,'surname'),
          'namesuffix'   => array_element($parsed_name,'suffix'),
          'bailiwick'    => $item['bailiwick'],
          'last_fetch'   => time(),
        );
        $member->set_contents_from_array($data);
        $member->set_member_uuid($member->generate_member_uuid());
        $member_id = $member->stow();
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) ---+-+-+-+ {$fullname} [{$member_id}]");
          $this->recursive_dump($item["parsed_name"],"(marker) - ");
        }
      }/*}}}*/
      else {/*{{{*/
        $member_uuid = $member->get_member_uuid();
        if ( empty($member_uuid) ) {
          $member_uuid = $member->generate_member_uuid();
        }
        $data = array(
          'bio_url'     => $bio_url,
          'firstname'   => array_element($parsed_name,'given'),
          'mi'          => array_element($parsed_name,'mi'),
          'surname'     => array_element($parsed_name,'surname'),
          'namesuffix'  => array_element($parsed_name,'suffix'),
          'bailiwick'   => $item['bailiwick'],
          'member_uuid' => $member_uuid,
        );
        $member_matched = $member->
          set_contents_from_array($data)->
          fields(array_keys($data))->
          stow();

        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) ---------- {$fullname} [{$member_matched}]");
          $this->recursive_dump($item["parsed_name"],"(marker) - ");
        }
      }/*}}}*/
      $debug_method = FALSE;

      if (0) {/*{{{*/
        $qt = join(' ',array_filter(array($parsed_name['given'],$parsed_name['suffix'],$parsed_name['mi'])));
        $qt = "{$parsed_name['surname']}, {$qt}";
        if ( !($qt == trim($fullname)) ) { 
          $j = join('|',$parsed_name);
          $this->syslog(__FUNCTION__,__LINE__,"(marker) ---------- {$fullname} [{$qt}]");
          $this->recursive_dump($parsed_name,"(marker) - ");
        }
      }/*}}}*/

      // $this->recursive_dump($parsed_name, "(marker)");

      $name_regex = '@^([^,]*),(.*)(([A-Zñ]?)\.)*( (.*))@i';
      $name_match = array();
      preg_match($name_regex, $fullname, $name_match);
      $name_match = array(
        'first-name'     => trim($name_match[2]),
        'surname'        => trim($name_match[1]),
        'middle-initial' => trim($name_match[6]),
      );
      $name_index = "{$name_match['surname']}-" . UrlModel::get_url_hash($bio_url);
      $district = $this->reverse_iconv($item['bailiwick']); 
      if ( 1 == preg_match('@^party list@i',$district) ) $district = 'Zz - Party List -';
      else {
        $district_regex = '@^([^,]*),(.*)@';
        $district_match = array();
        preg_match($district_regex, $district, $district_match);
        // $this->recursive_dump($district_match,__LINE__);
        $sub_district = trim($district_match[2]);
        $district = trim($district_match[1]);
      }
      $district_id = preg_replace(array('@(ñ)@i','@([ ]{1,})@i','@[^A-Z-]@i'),array('n','-',''),strtolower($district));
      $district = <<<EOH
<h1 class="domain-reset-children" id="mapto-{$district_id}">{$district}</h1>
EOH;
      if ( strlen($district) > 0 && !array_key_exists($district, $districts) ) $districts[$district] = array('00' => preg_replace('@Zz@','', $district)); 

      $fullname = "{$name_match['first-name']} {$name_match['middle-initial']} {$name_match['surname']}";
      $surname_first = strtoupper(substr(trim($name_match['surname']),0,1));
      if ( !(strlen($surname_first) > 0) ) continue;
      if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
      if ( $surname_first != $surname_initchar ) {/*{{{*/
        $surname_initchar = $surname_first;
        $lc_surname_initchar = strtolower($surname_first);
        $section_break = <<<EOH
<br/><h1 class="surname-reset-children" id="surname-reset-{$lc_surname_initchar}">{$surname_first}</h1>
EOH;
      }/*}}}*/
      $urlhash = UrlModel::get_url_hash($bio_url);
      $link_attributes = array("human-element-dossier-trigger");
      if ( !is_null($member_matched) ) $link_attributes[] = "cached";
      else $link_attributes[] = 'trigger';
      $time_since_fetch = $urlmodel->get_logseconds_since_last_fetch($now,$member->get_last_fetch());
      $link_attributes[] = $urlmodel->get_logseconds_css($time_since_fetch);
      $link_attributes[] = "surname-cluster-{$lc_surname_initchar}";
      $link_attributes = join(' ', $link_attributes);
      $candidate_entry = <<<EOH
<span><a href="{$bio_url}" class="{$link_attributes}" id="{$urlhash}">{$fullname}</a></span>

EOH;
      $pagecontent[$name_index] = "{$section_break}{$candidate_entry}";
      $districts[$district][$name_index] = $candidate_entry; 
      $section_break = NULL;
    }/*}}}*/

    $districts = array_map(create_function('$a', 'ksort($a); return join("<br/>",$a);'), $districts);

    ksort($pagecontent);
    ksort($districts);

    $pagecontent = join('<br/>', $pagecontent);
    $districts = join('<br/>', $districts);

    $cache_state_reset_link = <<<EOH

EOH;
    $pagecontent = <<<EOH
<input class="reset-cached-links" type="button" value="Reset"> 
<input class="reset-cached-links" type="button" value="Traverse"> 
<div class="float-left link-cluster" id="committee-listing">{$pagecontent}</div>
<div class="float-left link-cluster">{$districts}</div>
<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('input[class=reset-cached-links]').unbind('click').click(function(e) {
    if (jQuery(this).val() == 'Traverse') {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('cached').addClass('uncached').addClass("traverse");
      });
    } else {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('uncached').removeClass('traverse').addClass("cached");
      });
    } 
  });

  jQuery('h1[class*=surname-reset-children]').unbind('click').click(function(e){
    var linkset = jQuery(this).attr('id').replace(/^surname-reset-/,'surname-cluster-');
    if ( jQuery(this).hasClass('on') ) {
      jQuery(this).removeClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('cached').removeClass('uncached').addClass("traverse");
      });
    } else {
      jQuery(this).addClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('uncached').removeClass('traverse').addClass("cached");
      });
    }
  });

  jQuery('h1[class*=domain-reset-children]')
    .unbind('click')
    .unbind('mouseover')
    .unbind('mouseout')
    .mouseout(function(e){
      var mapregion = jQuery(this).attr('id').replace(/^mapto-/,'');
      jQuery('[id='+mapregion+']').attr('fill',jQuery('[id='+mapregion+']').attr('oldfill'));
    })
    .mouseover(function(e){
      var mapregion = jQuery(this).attr('id').replace(/^mapto-/,'');
      jQuery('[id='+mapregion+']').attr('fill','blue');
    });
  initialize_dossier_triggers();
});
</script>
EOH;
/*
 * Javascript fragment to trigger cycling
 *   setTimeout(function(){
 *   jQuery('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
 *   },1000);
 */


  }/*}}}*/

  function seek_congress_memberlist_15th(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $member = new RepresentativeDossierModel();

    $urlmodel->ensure_custom_parse();
    $this->debug_tags = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $pagecontent      = array();
    $surname_initchar = NULL;
    $section_break    = NULL;
    $districts        = array();

    $this->recursive_dump(($member_list = $this->get_containers(
      'children[tagname=div][id*=content_body_right|content_body_left|i]'
    )),'Extracted rows');

    $lc_surname_initchar = '';

    foreach ( $member_list as $item ) {/*{{{*/
      foreach( $item as $tag ) {/*{{{*/
        if ( !array_key_exists('url',$tag) ) continue;
        $bio_url = $tag['url'];
        $member->fetch($bio_url,'bio_url');

        $cdata = $this->reverse_iconv($tag['text']);

        if ( is_null($member->get_firstname()) ) {/*{{{*/
          $parsed_name = $member->parse_name($cdata);
          $data = array(
            'fullname'   => $cdata,
            'firstname'  => $parsed_name['given'],
            'mi'         => $parsed_name['mi'],
            'surname'    => $parsed_name['surname'],
            'namesuffix' => $parsed_name['suffix'],
          );
          $member->
            set_contents_from_array($data)->
            fields(array_keys($data))->
            stow();
        }/*}}}*/

        $name_regex = '@^([^,]*),(.*)(([A-Zñ]?)\.)*( (.*))@i';
        $name_match = array();
        preg_match($name_regex, $cdata, $name_match);
        $name_match = array(
          'first-name'     => trim($name_match[2]),
          'surname'        => trim($name_match[1]),
          'middle-initial' => trim($name_match[6]),
        );
        $name_index = "{$name_match['surname']}-" . UrlModel::get_url_hash($tag['url']);

        $district = $this->reverse_iconv($tag['title']); 
        if ( empty($district) ) $district = 'Zz - Party List -';
        else {
          $district_regex = '@^([^,]*),(.*)@';
          $district_match = array();
          preg_match($district_regex, $district, $district_match);
          $this->recursive_dump($district_match,__LINE__);
          $sub_district = trim($district_match[2]);
          $district = trim($district_match[1]);
        }
        $district_id = preg_replace(array('@(ñ)@i','@([ ]{1,})@i','@[^A-Z-]@i'),array('n','-',''),strtolower($district));
        $district = <<<EOH
<h1 class="domain-reset-children" id="mapto-{$district_id}">{$district}</h1>
EOH;
        $this->syslog(__FUNCTION__,__LINE__,"(marker) {$district}");
        if ( strlen($district) > 0 && !array_key_exists($district, $districts) ) $districts[$district] = array('00' => preg_replace('@^Zz@','', $district)); 

        $fullname = "{$name_match['first-name']} {$name_match['middle-initial']} {$name_match['surname']}";
        $surname_first = strtoupper(substr(trim($name_match['surname']),0,1));
        if ( !(strlen($surname_first) > 0) ) continue;
        if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
        if ( $surname_first != $surname_initchar ) {/*{{{*/
          $surname_initchar = $surname_first;
          $lc_surname_initchar = strtolower($surname_first);
          $section_break = <<<EOH
<br/><h1 class="surname-reset-children" id="surname-reset-{$lc_surname_initchar}">{$surname_first}</h1>
EOH;
        }/*}}}*/
        $urlhash = UrlModel::get_url_hash($bio_url);
        $link_attributes = array("human-element-dossier-trigger");
        if ( $member->in_database() ) $link_attributes[] = "cached";
        else $link_attributes[] = 'trigger';
        $link_attributes[] = "surname-cluster-{$lc_surname_initchar}";
        $link_attributes = join(' ', $link_attributes);
        $candidate_entry = <<<EOH
<span><a href="{$bio_url}" class="{$link_attributes}" id="{$urlhash}">{$fullname}</a></span>

EOH;
        $pagecontent[$name_index] = "{$section_break}{$candidate_entry}";
        $districts[$district][$name_index] = $candidate_entry; 
        $section_break = NULL;
      }/*}}}*/
    }/*}}}*/

    $districts = array_map(create_function('$a', 'ksort($a); return join("<br/>",$a);'), $districts);

    ksort($pagecontent);
    ksort($districts);

    $pagecontent = join('<br/>', $pagecontent);
    $districts = join('<br/>', $districts);

    $cache_state_reset_link = <<<EOH

EOH;
    $pagecontent = <<<EOH
  <input class="reset-cached-links" type="button" value="Clear"> 
  <input class="reset-cached-links" type="button" value="Reset"> 
  <div class="float-left link-cluster" id="congresista-list">{$pagecontent}</div>
  <div class="float-left link-cluster">{$districts}</div>
<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('input[class=reset-cached-links]').click(function(e) {
    if (jQuery(this).val() == 'Reset') {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    } 
  });
  jQuery('h1[class=surname-reset-children]').click(function(e){
    var linkset = jQuery(this).attr('id').replace(/^surname-reset-/,'surname-cluster-');
    if ( jQuery(this).hasClass('on') ) {
      jQuery(this).removeClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      jQuery(this).addClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    }
  });
  initialize_dossier_triggers();
});
</script>
EOH;
/*
 * Javascript fragment to trigger cycling
 *   setTimeout(function(){
 *   jQuery('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
 *   },1000);
 */


  }/*}}}*/

}


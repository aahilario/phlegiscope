<?php

/*
 * Class iReportParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class iReportParseUtility extends RawparseUtility {
  
  var $cellcounter = 0;

  function __construct() {
    parent::__construct();
  }

  // ------- Document HEAD ---------

  function ru_head_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_head_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_meta_open(& $parser, & $attrs) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_meta_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_title_open(& $parser, & $attrs) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_title_cdata(& $parser, & $cdata) {/*{{{*/
    return FALSE;
  }/*}}}*/
  function ru_title_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('cdata', $this->current_tag) ) {
      $title = array(
        'title' => join('',$this->current_tag['cdata']),
        'seq'   => 0,
      ); 
      $this->add_to_container_stack($title);
    }
    $this->push_tagstack();
    return FALSE;
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_body_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_body_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_table_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_tr_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->cellcounter = 0;
    return TRUE;
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_script_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_script_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_applet_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_applet_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_x_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_x_cdata(& $parser, & $cdata) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "CDATA: {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_x_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    return $this->current_tag['attrs']['seq'] == 2 ? FALSE : TRUE;
  }/*}}}*/

  function ru_frameset_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_frameset_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_frameset_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_html_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->push_container_def($tagname, $attrs);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_html_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_html_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->stack_to_containers();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_form_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->update_current_tag_url('ACTION');
    $attrs['ACTION'] = $this->current_tag['attrs']['ACTION'];
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_form_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_form_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $form = array(
      'form' => $this->current_tag['attrs']['NAME'],
      'action' => $this->current_tag['attrs']['ACTION'],
      'method' => $this->current_tag['attrs']['METHOD'],
      'seq' => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($form);
    return TRUE;
  }/*}}}*/

  function ru_frame_open(& $parser, & $attrs) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_frame_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $this->update_current_tag_url('SRC');
    $target = $this->current_tag['attrs']['SRC'];
    if ( !is_null($target) ) {
      $link_data = array(
        'frame-url' => $target,
        'frame-name' => $this->current_tag['attrs']['NAME'],
      );
      $this->links[] = $link_data;
      $this->add_to_container_stack($link_data);
    }
    return TRUE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    //$this->current_tag['attrs']['ONCLICK'] = NULL;
    //$this->current_tag['attrs']['ONFOCUS'] = NULL;
    //$this->current_tag['attrs']['ONCLICK'] = "window.status = '{$this->current_tag['attrs']['ONCLICK']}';";
    //$this->current_tag['attrs']['ONFOCUS'] = "window.status = '{$this->current_tag['attrs']['ONFOCUS']}';";
    $attrs = array_filter($this->current_tag['attrs']);
    $this->current_tag['attrs'] = $attrs;
    $this->update_current_tag_url('SRC');
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_input_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    //$this->current_tag['attrs']['ONCLICK'] = NULL;
    //$this->current_tag['attrs']['ONFOCUS'] = NULL;
    //$this->current_tag['attrs']['ONCLICK'] = "window.status = '{$this->current_tag['attrs']['ONCLICK']}';";
    //$this->current_tag['attrs']['ONFOCUS'] = "window.status = '{$this->current_tag['attrs']['ONFOCUS']}';";
    $attrs = array_filter($this->current_tag['attrs']);
    $this->current_tag['attrs'] = $attrs;

    $this->update_current_tag_url('SRC');
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_input_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_input_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $input_data = array(
      'input-type'    => array_element($this->current_tag['attrs'],'TYPE'),
      'name'          => array_element($this->current_tag['attrs'],'NAME'),
      'value-default' => array_element($this->current_tag['attrs'],'VALUE'),
      'seq'           => array_element($this->current_tag['attrs'],'seq'),
    );
    if ( $input_data['input-type'] == 'radio' ) {
      $input_data['checked'] = array_key_exists('CHECKED', $this->current_tag['attrs'])
        ? 'true'
        : 'false'
        ;
    }
    $this->add_to_container_stack($input_data);
    return TRUE;
  }/*}}}*/

  function ru_select_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    //$this->current_tag['attrs']['ONCLICK'] = NULL;
    //$this->current_tag['attrs']['ONFOCUS'] = NULL;
    $this->current_tag['attrs']['ONCLICK'] = "window.status = '{$this->current_tag['attrs']['ONCLICK']}';";
    $this->current_tag['attrs']['ONFOCUS'] = "window.status = '{$this->current_tag['attrs']['ONFOCUS']}';";
    $attrs = array_filter($this->current_tag['attrs']);
    $this->current_tag['attrs'] = $attrs;

    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_select_close(& $parser, $tag) {/*{{{*/
    // Treat SELECT tags as containers on OPEN, but as tags on CLOSE 
    // $this->stack_to_containers();
    $select_contents = array_pop($this->container_stack);
    $this->add_to_container_stack($select_contents);
    return TRUE;
  }/*}}}*/

  function ru_option_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_option_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to OPTION tag. This is normally the option label 
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_option_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $target = $this->current_tag['attrs']['VALUE'];
    if ( !is_null($target) ) {
      $link_data = array(
        'value'    => $this->current_tag['attrs']['VALUE'],
        'text'     => join(' ',$this->current_tag['cdata']),
      );
      if ( array_key_exists('SELECTED', $this->current_tag['attrs']) ) $link_data['selected'] = 'selected';
      $this->add_to_container_stack($link_data);
    }
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->current_tag['attrs']['class'] = 'legiscope-remote';
    $this->push_tagstack();
    array_push($this->links, $this->current_tag); 
    return TRUE;
  }  /*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    $this->pop_tagstack();
    $link = array_pop($this->links);
    $link['cdata'][] = $cdata; 
    $this->current_tag['cdata'][] = $cdata; 
    array_push($this->links, $link);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $target = 0 < count($this->links) ? array_pop($this->links) : NULL;
		$link_data = $this->collapse_current_tag_link_data();
    if ( !(empty($link_data['url']) && empty($link_data['text'])) ) {
      array_push($this->links, $target);
      $this->add_to_container_stack($link_data);
    }
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $cell_data = array(
      'celltext' => preg_replace('@[ ]{2,}@',' ',preg_replace('@[^-A-Z0-9_" &/,.]@i','',trim(join('', $this->current_tag['cdata'])))),
      'column' => intval($this->cellcounter),
      'seq' => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($cell_data);
    $this->cellcounter++;
    return TRUE;
  }/*}}}*/

  function ru_b_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_b_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_b_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/


}


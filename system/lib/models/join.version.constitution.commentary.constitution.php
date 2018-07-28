<?php

/*
 * Class ConstitutionCommentaryConstitutionVersionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sat, 28 Jul 2018 07:09:37 +0000
 */

class ConstitutionCommentaryConstitutionVersionJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_commentary_ConstitutionCommentaryModel;
  var $constitution_version_ConstitutionVersion;

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class($this))) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_commentary($v) { $this->constitution_commentary_ConstitutionCommentaryModel = $v; return $this; }
  function get_constitution_commentary($v = NULL) { if (!is_null($v)) $this->set_constitution_commentary($v); return $this->constitution_commentary_ConstitutionCommentaryModel; }

  function & set_constitution_version($v) { $this->constitution_version_ConstitutionVersion = $v; return $this; }
  function get_constitution_version($v = NULL) { if (!is_null($v)) $this->set_constitution_version($v); return $this->constitution_version_ConstitutionVersion; }

}


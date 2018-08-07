<?php

/*
 * system/core.php
 * Legiscope crawler
 */

require_once('configuration.php');
require_once('base.php');

// Instantiate Legiscope controller
LegiscopeBase::instantiate_by_host();

LegiscopeBase::image_request();
LegiscopeBase::javascript_request();
LegiscopeBase::stylesheet_request();
// Must be deferred until authentication state is determined.
// Transferred to LegiscopeBase::wordpress_init
// LegiscopeBase::model_action();

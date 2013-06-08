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
LegiscopeBase::model_action();

<?php

$request_uri = explode('/',trim(array_element($_SERVER,'REQUEST_URI'),'/'));
$request_uri = array_pop($request_uri);

switch ( $request_uri ) {
	case 'legislative-executive-catalog':
		break; 
	case 'legiscope':
		break;
	default:
		$request_uri = NULL;
		phpinfo(INFO_ENVIRONMENT | INFO_VARIABLES);
		break;
}

if ( !is_null($request_uri) ) require_once("{$request_uri}.php");

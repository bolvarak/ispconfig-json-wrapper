<?php // ini_set('display_errors', true); ini_set('error_reporting', E_ALL);
/**
 * @name ISPConfig JSON Wrapper
 * @description This class provides a JSON/P wrapper for the remote interface
 * @copyright 2014, Travis Brown
 * @license GPLv3 All Rights Reserved
 * @author Travis Brown (bolvarak) <tmbrown6@gmail.com>
 * @version 1.0
 * @uses ISPConfig 3
 * This file is part of ISPConfigJsonWrapper.
 *
 * ISPConfigJsonWrapper is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *      
 * ISPConfgJsonWrapper is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ISPConfigJsonWrapper.  If not, see <http://www.gnu.org/licenses/>.
 */

// Include the configuration library
require_once '../../lib/config.inc.php';
// Do not start sessions
$conf['start_session'] = false;
// Include the application library
require_once '../../lib/app.inc.php';
// Include the JSON wrapper
require_once(dirname(__FILE__).'/ISPConfigJsonWrapper.php');
// Check for demo mode
if($conf['demo_mode'] == true) $app->error('This function is disabled in demo mode.');
// Load the app and configuration
$app->load('remoting,getconf');
// Configure the permissions
$security_config = $app->getconf->get_security_config('permissions');
// Check for remote access
if($security_config['remote_api_allowed'] != 'yes') die('Remote API is disabled in security settings.');
// Instantiate the JSON API
$server = new ISPConfigJSonWrapper();
// Set the class into the wrapper
$server->setHandlerClass('remoting');
// Execute the API
$server->handleRequest();


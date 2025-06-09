<?php

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

// settings
date_default_timezone_set('Europe/Berlin');

// Make sure this setting is turned on and reflect the root url for your WebDAV server.
// This can be for example the root / or a complete path to your server script
$baseUri = '/adm_plugins/dav/server.php';

// Autoloader
require_once 'vendor/autoload.php';

// Backends
include 'AdmBasicAuthBackend.php';
$authBackend = new AdmBasicAuthBackend();
include 'AdmPrincipalBackend.php';
$principalBackend = new AdmPrincipalBackend();
include 'AdmCarddavBackend.php';
$carddavBackend = new AdmCarddavBackend();
include 'AdmCalendarBackend.php';
$calendarBackend = new AdmCalendarBackend();

// Setting up the directory tree //
$nodes = [
    new Sabre\CalDAV\Principal\Collection($principalBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);

// Plugins
/* Server Plugins */
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend);
$server->addPlugin($authPlugin);

$aclPlugin = new Sabre\DAVACL\Plugin();
$server->addPlugin($aclPlugin);

/* CalDAV support */
$caldavPlugin = new Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);

/* CardDAV support */
$server->addPlugin(new Sabre\CardDAV\Plugin());

// Support for html frontend
$browser = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

// And off we go!
$server->start();

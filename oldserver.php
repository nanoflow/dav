<?php

if (in_array($_SERVER['REQUEST_URI'], ['/', '/.well-known/carddav', '/.well-known/caldav'])) {
    $_SERVER['REQUEST_URI'] = '/';
}

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

$baseUri = '/adm_plugins/dav/'; 

// Autoloader
require_once 'vendor/autoload.php';

// Backends
include 'BasicAuthBackend.php';
$authBackend = new BasicAuthBackend();

include 'AdmPrincipalBackend.php';
$principalBackend = new AdmPrincipalBackend();

include 'AdmCalendarBackend.php';
$calendarBackend = new AdmCalendarBackend();

include 'AdmCarddavBackend.php';
$carddavBackend = new AdmCarddavBackend();

// Setting up the directory tree //
$nodes = [
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend)
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
// $server->addPlugin(new Sabre\DAVACL\Plugin());
// $server->addPlugin(new Sabre\DAV\Sync\Plugin());

// And off we go!
$server->start();

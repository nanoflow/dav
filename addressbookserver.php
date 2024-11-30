<?php

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

$baseUri = '/adm_plugins/carddav/addressbookserver.php'; // TODO: check this

// Autoloader
require_once 'vendor/autoload.php';

// Backends
include 'BasicAuthBackend.php';
$authBackend = new BasicAuthBackend();

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server();
$server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());

// And off we go!
$server->start();

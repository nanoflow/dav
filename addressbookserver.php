<?php

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

$baseUri = '/adm_plugins/carddav/addressbookserver.php'; // TODO: check this

// Autoloader
require_once 'vendor/autoload.php';

$server = new Sabre\DAV\Server();
$server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new Sabre\DAV\Browser\Plugin());

// And off we go!
$server->start();

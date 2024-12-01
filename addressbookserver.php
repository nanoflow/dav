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

include 'AdmPrincipalBackend.php';
$principalBackend = new AdmPrincipalBackend();

// Setting up the directory tree //
$nodes = [
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());

// And off we go!
$server->start();

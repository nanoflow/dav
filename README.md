# DAV Plugin for Admidio

## Installation:

### Using the zip file:

Unzip the dav plugin into the adm_plugins folder of your admidio installation.

### Using git and composer:

- Clone the repository into the adm_plugins folder
- run `composer install` inside the cloned repository

## Service Discovery:

you probably want to improve the discovery of your dav server by adding the following redirects to your webserver:

/.well-known/caldav -> /adm_plugins/dav/server.php
/.well-known/carddav -> /adm_plugins/dav/server.php

also ssl encryption is mandatory e.g. for access with the macOS calendar and the used basic authentication.

this can be done e.g. by putting a .htaccess file in the root of your website with the following content:

```
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
Redirect 302 /.well-known/carddav /adm_plugins/dav/server.php
Redirect 302 /.well-known/caldav /adm_plugins/dav/server.php
```

### Debugging

you can enable the Sabre DAV browser plugin for debuging in the dav/server.php file.

Further information in the [Sabre documentation](https://sabre.io/dav/)

## Client configuration

### CalDAV (calendars):

#### MacOS Calendar (Sequoia 15.5):

- open the calendar app
- go to Calendar/Add Account
- select Other CalDAV Account
- Enter the following information
  - Account Type: Manual
  - Username: Your Admidio User Name ()
  - Password: Your Admidio Password
  - Server Address: URL of your Admidio Installation (including https://)
- Sign In
- All calendars that are available for your account will be added to the Calendar App

#### Thunderbird (139.0.1):

- open the calendar in thunderbird
- New Calendar
- Select 'On the Network'
- Enter the following information
  - Username: Your Admidio User Name
  - Location: URL of your Admidio Installation
  - Click 'Find Calendars'
- Enter your Admidio password when asked for it
- Select the calendars that you want to subscribe to
- Click 'Subscribe'

### CardDAV (contacts):

#### MacOS Contacts

- Contacts/Add Account
- Other Contacts Account...
- Enter the following information
  - CardDAV
  - Account Type: Manual
  - Username: Your Admidio User Name
  - Password: Your Admidio Password
  - Server Address: URL of your Admidio Installation (including https://)
- Only one addressbook is addedto the Contacts App, this is a known limitation of MacOS Contacts App

#### Thunderbird (139.0.1):

- Open the thunderbird address book
- Add CardDAV Address Book
- Enter the following information:
  - Username: Your Admidio User Name
  - Location: URL of your Admidio Installation
  - Click 'Continue'
- Enter your Admidio username and password when asked for it
- Select the Addressbooks that you want to subscribe to
- Click 'Continue'

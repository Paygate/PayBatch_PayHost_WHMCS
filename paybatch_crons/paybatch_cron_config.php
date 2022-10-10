<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This module facilitates PayGate payments by means of PayHost / PayBatch for WHMCS clients
 *
 * PayHost is used to initialise and vault card detail, successive payments are made using the vault and PayBatch
 *
 */

$license              = 'My_license';
$db_host              = 'www.myserver.com';
$db_username          = 'db_user';
$db_password          = 'db_password';
$db_name              = 'db_name';
$cc_encryption_hash   = 'my_hash';
$templates_compiledir = 'templates_c';
$mysql_charset        = 'utf8';

$api_identifier = 'my_api_identifier';
$api_secret     = 'my_api_secret';
$api_access_key = 'my_api_access_key';

$api_url = 'https://www.myserver.com/includes/api.php';

// Change this if default is not being used
define('_DB_PREFIX_', 'tbl');

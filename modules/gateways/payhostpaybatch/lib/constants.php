<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Global defines used for the two endpoints
 *
 */

define( "PAYHOSTAPI", 'https://secure.paygate.co.za/payhost/process.trans' );
define( "PAYHOSTAPIWSDL", 'https://secure.paygate.co.za/payhost/process.trans/?wsdl' );
define( "PAYBATCHAPI", 'https://secure.paygate.co.za/paybatch/1.2/process.trans' );
define( "PAYBATCHAPIWSDL", 'https://secure.paygate.co.za/paybatch/1.2/PayBatch.wsdl' );
define( "PAYGATETESTID", '10011072130' );
define( "PAYGATETESTKEY", 'secret' );
define( "GATEWAY", 'payhostpaybatch' );

$docroot = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
if ( isset( $_SERVER['SERVER_PORT'] ) ) {
    $docroot .= ':' . $_SERVER['SERVER_PORT'];
}
$docroot .= '/';
define( "DOC_ROOT", $docroot );

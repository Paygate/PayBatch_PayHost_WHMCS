<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 */

try {
    $dbc = new mysqli( $db_host, $db_username, $db_password, $db_name );
} catch ( Exception $e ) {
    die( $e->getMessage() );
}

if ( !defined( '_DB_PREFIX_' ) ) {
    define( '_DB_PREFIX_', 'tbl' );
}

// Create a table to store PayBatch transaction data
$query = "create table if not exists `" . _DB_PREFIX_ . "payhostpaybatch` (";
$query .= " id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ";
$query .= " recordtype VARCHAR(20) NOT NULL, ";
$query .= " recordid VARCHAR(50) NOT NULL, ";
$query .= " recordval VARCHAR(50) NOT NULL, ";
$query .= " dbid VARCHAR(10) NOT NULL DEFAULT '1')";
$stmt = $dbc->prepare( $query );
$stmt->execute();
$stmt = null;

// Get gateway parameters
$query = "select * from `" . _DB_PREFIX_ . "paymentgateways` where gateway = '" . GATEWAY . "'";
$stmt  = $dbc->prepare( $query );
$stmt->execute();
$params = [];
$r      = $stmt->get_result();
while ( $item = $r->fetch_array( MYSQLI_ASSOC ) ) {
    $params[$item['setting']] = $item['value'];
}

//Get system currencies
$query = "select * from `" . _DB_PREFIX_ . "currencies`";
$stmt  = $dbc->prepare( $query );
$stmt->execute();
$r          = $stmt->get_result();
$currencies = [];
while ( $item = $r->fetch_assoc() ) {
    $currencies[$item['id']] = ['code' => $item['code'], 'rate' => $item['rate']];
}

// Check if test mode or not
$testMode = $params['testMode'];
if ( $testMode == 'on' ) {
    $payHostId         = PAYGATETESTID;
    $payBatchId        = PAYGATETESTID;
    $payHostSecretKey  = PAYGATETESTKEY;
    $payBatchSecretKey = PAYGATETESTKEY;
} else {
    $payHostId         = $params['payHostID'];
    $payBatchId        = $params['payBatchID'];
    $payHostSecretKey  = $params['payHostSecretKey'];
    $payBatchSecretKey = $params['payBatchSecretKey'];
}

function callApi( $options )
{
    global $api_identifier;
    global $api_secret;
    global $access_key;
    $whmcsUrl = DOC_ROOT . 'includes/api.php';

    $postfields = [
        'identifier'   => $api_identifier,
        'secret'       => $api_secret,
        'accesskey'    => $access_key,
        'responsetype' => 'json',
    ];

    $postfields = array_merge( $postfields, $options );

    // Call the API
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $whmcsUrl );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $postfields ) );
    $response = curl_exec( $ch );
    $error    = curl_error( $ch );
    curl_close( $ch );

    if ( strlen( $error ) > 0 ) {
        return ['error' => true, 'response' => $error];
    }

    return ['error' => false, 'response' => json_decode( $response, true )];
}

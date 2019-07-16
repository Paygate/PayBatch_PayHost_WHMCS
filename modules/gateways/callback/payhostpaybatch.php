<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This file handles the return POST from a PayHost or PayBatch transactionId
 *
 */

// Require libraries needed for gateway module functions
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

define( "PAYHOSTAPI", 'https://secure.paygate.co.za/payhost/process.trans' );
define( "PAYHOSTAPIWSDL", 'https://secure.paygate.co.za/payhost/process.trans/?wsdl' );
define( "PAYBATCHAPI", 'https://secure.paygate.co.za/paybatch/1.2/process.trans' );
define( "PAYBATCHAPIWSDL", 'https://secure.paygate.co.za/paybatch/1.2/PayBatch.wsdl' );
define( "PAYGATETESTID", '10011072130' );
define( "PAYGATETESTKEY", 'test' );

function getQuery( $pgid, $key, $reqid )
{
    $userId = $_SESSION['uid'];
    $token  = Capsule::table( 'tblclients' )
        ->where( 'id', $userId )
        ->value( 'cardnum' );

    $soap = <<<SOAP
            <ns1:SingleFollowUpRequest>
                <ns1:QueryRequest>
                    <ns1:Account>
                        <ns1:PayGateId>{$pgid}</ns1:PayGateId>
                        <ns1:Password>{$key}</ns1:Password>
                    </ns1:Account>
                    <ns1:PayRequestId>{$reqid}</ns1:PayRequestId>
                </ns1:QueryRequest>
            </ns1:SingleFollowUpRequest>
SOAP;
    $wsdl = PAYHOSTAPIWSDL;
    $sc   = new SoapClient( $wsdl, ['trace' => 1] );
    try {
        $result = $sc->__soapCall( 'SingleFollowUp', [
            new SoapVar( $soap, XSD_ANYXML ),
        ] );
        if ( $result ) {
            $vaultId       = $result->QueryResponse->Status->VaultId;
            $reference     = $result->QueryResponse->Status->Reference;
            $transactionId = $result->QueryResponse->Status->TransactionId;
        } else {
            $vaultId = null;
        }
    } catch ( SoapFault $f ) {
        $vaultId = null;
    }

    if ( $token == null || $token == '' ) {
        $token = $vaultId;
    }
    return ['token' => $token, 'reference' => $reference, 'transactionId' => $transactionId];
}

// Get current user
$userId = intval($_SESSION['uid']);

// Detect module name from filename
$gatewayModuleName = basename( __FILE__, '.php' );

// Fetch gateway configuration parameters
$gatewayParams = getGatewayVariables( $gatewayModuleName );

// Die if module is not active.
if ( !$gatewayParams['type'] ) {
    die( "Module Not Activated" );
}

// Check if we are in test mode
$testMode = $gatewayParams['testMode'];
if ( $testMode == 'on' ) {
    $payHostId         = PAYGATETESTID;
    $payBatchId        = PAYGATETESTID;
    $payHostSecretKey  = PAYGATETESTKEY;
    $payBatchSecretKey = PAYGATETESTKEY;
} else {
    $payHostId         = $gateWayParams['payHostId'];
    $payBatchId        = $gateWayParams['payBatchId'];
    $payHostSecretKey  = $gateWayParams['payHostSecretKey'];
    $payBatchSecretKey = $gateWayParams['payBatchSecretKey'];
}

// Retrieve data returned in payment gateway callback
// We need to distinguish between a return from PayHost and a return from PayBatch

if ( isset( $_POST['PAY_REQUEST_ID'] ) && isset( $_POST['TRANSACTION_STATUS'] ) ) {
    // PayHost postback
    $status   = filter_var( $_POST['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
    $verified = false;
    if ( $status == 1 ) {
        // Success
        // Verify transaction key
        $payRequestId = filter_var( $_POST['PAY_REQUEST_ID'] );
        if ( $payRequestId == $_SESSION['PAY_REQUEST_ID'] ) {
            $checkString = $payHostId . $payRequestId . $status . $_SESSION['REFERENCE'] . $payHostSecretKey;
            $check       = md5( $checkString );
            if ( $check == filter_var( $_POST['CHECKSUM'] ) ) {
                $verified = true;
            }
        } else {
            // Validity not verified
        }
    } else {
        // Transaction failed
    }

    // Make a request to get the Vault Id
    if ( $verified ) {
        $response      = getQuery( $payHostId, $payHostSecretKey, $payRequestId );
        $token         = $response['token'];
        $reference     = $response['reference'];
        $transactionId = $response['transactionId'];

        // Store the token
        Capsule::table( 'tblclients' )
            ->where( 'id', $userId )
            ->update( ['cardnum' => $token] );

        // Check the reference validity
        if ( $reference == $_SESSION['REFERENCE'] ) {
            $command = 'AddInvoicePayment';
            $data    = [
                'invoiceid' => $reference,
                'transid'   => $transactionId,
                'gateway'   => $gatewayModuleName,
            ];
            $result = localAPI( $command, $data );
            logTransaction( $gatewayModuleName, $response, 'success' );
            callback3DSecureRedirect( $reference, true );
        }
    } else {
        // Failed
        logTransaction( $gatewayModuleName, null, 'failed' );
    }
} else {
    // PayBatch response
}
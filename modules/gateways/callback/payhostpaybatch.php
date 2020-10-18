<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
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
require_once '../payhostpaybatch/lib/constants.php';

if ( !defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

use WHMCS\Database\Capsule;

if ( !defined( '_DB_PREFIX_' ) ) {
    define( '_DB_PREFIX_', 'tbl' );
}

/**
 * Check for existence of payhostpaybatch table and create if not
 */
if ( !function_exists( 'createPayhostpaybatchTable' ) ) {
    function createPayhostpaybatchTable()
    {
        $query = "create table if not exists `" . _DB_PREFIX_ . "payhostpaybatch` (";
        $query .= " id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ";
        $query .= " recordtype VARCHAR(20) NOT NULL, ";
        $query .= " recordid VARCHAR(50) NOT NULL, ";
        $query .= " recordval VARCHAR(50) NOT NULL, ";
        $query .= " dbid VARCHAR(10) NOT NULL DEFAULT '1')";

        return full_query( $query );
    }
}

createPayhostpaybatchTable();

/**
 * @param $pgid
 * @param $key
 * @param $reqid
 * @return array ['token' => $token, 'reference' => $reference, 'transactionId' => $transactionId]
 * @throws SoapFault
 *
 * PayHost Query Request to retrieve card token from authorised vault transaction
 */
function getQuery( $pgid, $key, $reqid )
{
    $userId             = $_SESSION['uid'];
    $tblpayhostpaybatch = _DB_PREFIX_ . 'payhostpaybatch';
    $token              = Capsule::table( $tblpayhostpaybatch )
        ->where( 'recordtype', 'clientdetail' )
        ->where( 'recordid', $userId )
        ->value( 'recordval' );

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
$userId = intval( $_SESSION['uid'] );

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
    $payHostId         = $gatewayParams['payHostID'];
    $payBatchId        = $gatewayParams['payBatchID'];
    $payHostSecretKey  = $gatewayParams['payHostSecretKey'];
    $payBatchSecretKey = $gatewayParams['payBatchSecretKey'];
}

// Retrieve data returned in payment gateway callback
// We need to distinguish between a return from PayHost and a return from PayBatch

if ( isset( $_POST['PAY_REQUEST_ID'] ) && isset( $_POST['TRANSACTION_STATUS'] ) ) {
    // PayHost postback
    $payRequestId       = filter_var( $_POST['PAY_REQUEST_ID'] );
    $tblpayhostpaybatch = _DB_PREFIX_ . 'payhostpaybatch';
    $reference          = Capsule::table( $tblpayhostpaybatch )
        ->where( 'recordtype', 'transactionrecord' )
        ->where( 'recordid', $payRequestId )
        ->value( 'recordval' );

    $status   = filter_var( $_POST['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
    $verified = false;

    // Verify transaction key
    $checkString = $payHostId . $payRequestId . $status . $reference . $payHostSecretKey;
    $check       = md5( $checkString );
    $verified    = hash_equals( $check, $_POST['CHECKSUM'] );
    if ( !$verified ) {
        // Validity not verified
        // Failed
        logActivity( 'Validity not verified: ' . $payRequestId . '_' . $reference );
        callback3DSecureRedirect( $reference, false );
    }

    // Make a request to get the Vault Id
    if ( $verified && $status == 1 ) {
        $response      = getQuery( $payHostId, $payHostSecretKey, $payRequestId );
        $transactionId = $response['transactionId'];

        // Check for token and valid format
        $vaultPattern = '/^[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$/';
        $token        = !empty( $response['token'] ) ? $response['token'] : null;
        if ( preg_match( $vaultPattern, $token ) != 1 ) {
            $token = null;
        }

        // Store the token if valid
        if ( $token ) {
            $clientExists = Capsule::table( $tblpayhostpaybatch )
                ->where( 'recordtype', 'clientdetail' )
                ->where( 'recordid', $userId )
                ->value( 'recordval' );

            if ( strlen( $clientExists ) > 0 ) {
                Capsule::table( $tblpayhostpaybatch )
                    ->where( 'recordtype', 'clientdetail' )
                    ->where( 'recordid', $userId )
                    ->update( ['recordval' => $token] );
            } else {
                Capsule::table( $tblpayhostpaybatch )
                    ->insert( [
                        'recordtype' => 'clientdetail',
                        'recordid'   => $userId,
                        'recordval'  => $token,
                    ] );
            }
        }

        // Get the current invoice and check its status
        $command = 'GetInvoice';
        $data    = [
            'invoiceid' => $reference,
        ];
        $invoice = localApi( $command, $data );

        // Get transactions for invoice
        $command = 'GetTransactions';
        $data    = [
            'invoiceid' => $reference,
        ];
        $transactions = localAPI( $command, $data );

        // Check for duplicate transaction
        $duplicate = false;
        foreach ( $transactions['transactions']['transaction'] as $transaction ) {
            if ( $transactionId == $transaction['transid'] ) {
                $duplicate = true;
            }
        }
        if ( !$duplicate ) {
            // Add invoice payment
            $command = 'AddInvoicePayment';
            $data    = [
                'invoiceid' => $reference,
                'transid'   => $transactionId,
                'gateway'   => $gatewayModuleName,
            ];
            $result = localAPI( $command, $data );
            logTransaction( $gatewayModuleName, $response, 'success' );
            logActivity( 'Payment successful: ' . $payRequestId . '_' . $reference );
            callback3DSecureRedirect( $reference, true );
        } else {
            logActivity( 'Duplicate transaction: ' . $payRequestId . '_' . $transactionId . '_' . $reference );
            callback3DSecureRedirect( $reference, false );

        }
    } else {
        // Failed
        logTransaction( $gatewayModuleName, null, 'failed' );
        logActivity( 'Payment failed: ' . $payRequestId . '_' . $reference );
        callback3DSecureRedirect( $reference, false );
    }
}

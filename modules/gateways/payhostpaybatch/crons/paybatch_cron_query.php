<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
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

require_once '../../../../init.php';
include_once '../lib/constants.php';
include_once '../../../../configuration.php';
include_once '../lib/paybatch_cron_common.php';
include_once '../lib/paybatchsoap.class.php';

$payBatchSoap = new paybatchsoap( DOC_ROOT );

try {
    $batches = [];
    $query   = "select recordid from `" . _DB_PREFIX_ . "payhostpaybatch` where recordtype = 'uploadid'";
    $stmt    = $dbc->prepare( $query );
    $stmt->execute();
    $result = $stmt->get_result();
    while ( $batch = $result->fetch_assoc() ) {
        $batches[] = $batch['recordid'];
    }
    $stmt = null;

    if (  ( $nbatches = count( $batches ) ) > 0 ) {
        foreach ( $batches as $key => $batch ) {
            $queryResult = doPayBatchQuery( $batch );

            if ( !empty( $queryResult->TransResult ) ) {
                if ( !is_array( $queryResult->TransResult ) ) {
                    //Only single result
                    handleLineItem( $queryResult->TransResult );
                } else {
                    foreach ( $queryResult->TransResult as $transResult ) {
                        handleLineItem( $transResult );
                    }
                }
            }
            unset( $batches[$key] );
            $query = "delete from `" . _DB_PREFIX_ . "payhostpaybatch` where recordtype = 'uploadid' and recordid = ?";
            $stmt  = $dbc->prepare( $query );
            $stmt->bind_param( 's', $batch );
            $stmt->execute();
            $stmt = null;
        }

        die( $nbatches . ' PayGate PayBatch batches were queried for payment information and processed' );
    } else {
        die( 'No PayGate PayBatch batches were found for processing' );
    }
} catch ( Exception $e ) {
    die( $e->getMessage() );
}

function handleLineItem( $transResult )
{
    $transResult = explode( ',', $transResult );
    $headings    = ['txId', 'txType', 'txRef', 'authcode', 'txStatusCode', 'txStatusDescription', 'txResultCode', 'txResultDescription'];
    $transResult = array_combine( $headings, $transResult );
    if ( $transResult['txStatusCode'] === '1' && $transResult['txStatusDescription'] === 'Approved' ) {
        $options = [
            'action'    => 'AddInvoicePayment',
            'invoiceid' => $transResult['txRef'],
            'transid'   => $transResult['authcode'] . '_' . uniqid(),
            'gateway'   => GATEWAY,
        ];
    }

    $result = callApi( $options );
}

function doPayBatchQuery( $uploadId )
{
    global $payBatchSoap;
    global $payBatchId;
    global $payBatchSecretKey;

    $queryXml = $payBatchSoap->getQueryRequest( $uploadId );
    $wsdl     = PAYBATCHAPIWSDL;
    $options  = ['trace' => 1, 'login' => $payBatchId, 'password' => $payBatchSecretKey];

    $soapClient  = new SoapClient( $wsdl, $options );
    $queryResult = $soapClient->__soapCall( 'Query', [
        new SoapVar( $queryXml, XSD_ANYXML ),
    ] );

    return $queryResult;
}

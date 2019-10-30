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

$invoices = getInvoices1();

if ( $invoices ) {
    $today  = new DateTime();
    $data   = [];
    $tokens = [];

    foreach ( $invoices as $invoice ) {
        $userid    = $invoice['userid'];
        $firstname = $invoice['firstname'];
        $lastname  = $invoice['lastname'];
        $duedate   = $invoice['duedate'];
        $total     = $invoice['total'];
        $currencycode = 'ZAR'; // PayBatch only handles ZAR
        $status       = $invoice['status'];
        $invoiceId    = $invoice['id'];

        if ( new DateTime( $duedate ) <= $today && $status == 'Unpaid' && ( $currencycode == 'ZAR' || !$currencycode ) ) {
            if ( isset( $tokens[$userid] ) ) {
                $vaultId = $tokens[$userid];
            } else {
                // Need to make api call to get the user token
                $token = $invoice['cardnum'];
                if ( $token ) {
                    $vaultPattern = '/^[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$/';
                    if ( preg_match( $vaultPattern, $token ) != 1 ) {
                        continue;
                    }
                    $tokens[$userid] = $token;
                    $vaultId         = $token;
                } else {
                    $tokens[$userid] = null;
                    continue;
                }
            }
            $item   = [];
            $item[] = 'A'; // Authorisation request
            $item[] = $invoiceId; // Transaction reference - use invoice id
            $item[] = $firstname . '_' . $lastname; // User name - not used anywhere
            $item[] = $vaultId; // Vault Id for client card
            $item[] = '00'; // Budget period - no budget
            $item[] = intval( $total * 100 ); // Transaction amount in ZA cents
            $data[] = $item;
        }
    }

    $errors   = false;
    $invalids = true;
    if ( count( $data ) > 0 ) {
        while ( !$errors && $invalids && count( $data ) > 0 ) {
            try {
                // Make PayBatch authorisation request
                $soap    = $payBatchSoap->getAuthRequest( $data );
                $wsdl    = PAYBATCHAPIWSDL;
                $options = ['trace' => 1, 'login' => $payBatchId, 'password' => $payBatchSecretKey];

                $soapClient = new SoapClient( $wsdl, $options );
                $result     = $soapClient->__soapCall( 'Auth', [
                    new SoapVar( $soap, XSD_ANYXML ),
                ] );
                if ( $result->Invalid == 0 ) {
                    $invalids = false;
                    $uploadId = $result->UploadID;
                    // Now make confirmation request to trigger actual payment attempt
                    $confirmXml    = $payBatchSoap->getConfirmRequest( $uploadId );
                    $confirmResult = $soapClient->__soapCall( 'Confirm', [
                        new SoapVar( $confirmXml, XSD_ANYXML ),
                    ] );
                    if ( $confirmResult->Invalid != 0 ) {
                        $errors = true;
                    }
                } else {
                    foreach ( $result->InvalidReason as $invalid ) {
                        unset( $data[$invalid->Line - 1] );
                    }
                }
            } catch ( SoapFault $fault ) {
                $errors = true;
                echo $fault->getMessage();
            }
        }

        if ( $errors ) {
            // Log and die
            die( 'Could not process batch transaction' );
        }

        // Store the upload ids so we can process later
        try {
            $query = "insert into `" . _DB_PREFIX_ . "payhostpaybatch` (recordtype, recordid, recordval)  values ('uploadid', ?, 'true')";
            $stmt  = $dbc->prepare( $query );
            $stmt->bind_param( 's', $uploadId );
            $stmt->execute();
        } catch ( Exception $e ) {
            die( $e->getMessage() );
        }
        die( count( $data ) . ' invoices were successfully uploaded to PayGate PayBatch for processing' );
    } else {
        die( 'No matching invoices found!' );
    }
}

function getInvoices1()
{
    global $dbc;
    $query = "select `" . _DB_PREFIX_ . "invoices`.*, firstname, lastname, cardnum, currency from `" . _DB_PREFIX_ . "invoices` ";
    $query .= " inner join `" . _DB_PREFIX_ . "clients` on `" . _DB_PREFIX_ . "clients`.id = `" . _DB_PREFIX_ . "invoices`.userid ";
    $query .= " where `" . _DB_PREFIX_ . "invoices`.status = 'Unpaid' and paymentmethod = '" . GATEWAY . "'";
    $stmt = $dbc->prepare( $query );
    $stmt->execute();
    $result   = $stmt->get_result();
    $invoices = [];
    while ( $invoice = $result->fetch_assoc() ) {
        $invoices[] = $invoice;
    }

    return $invoices;
}

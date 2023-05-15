<?php
/*
 * Copyright (c) 2023 PayGate (Pty) Ltd
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

require_once './paybatch_cron_config.php';
require_once './paybatch_cron_common.php';
include_once './paybatchsoap.class.php';

$payBatchSoap = new paybatchsoap();

$invoices = getInvoices1();

// Get currency id for ZAR
$zarId = null;
foreach ($currencies as $id => $currency) {
    if ($currency['code'] === 'ZAR') {
        $zarId = $id;
    }
}

if ($invoices) {
    $today = new DateTime();
    $data  = [];

    foreach ($invoices as $invoice) {
        $userid       = $invoice['userid'];
        $firstname    = $invoice['firstname'];
        $lastname     = $invoice['lastname'];
        $duedate      = $invoice['duedate'];
        $total        = $invoice['total'];
        $currencycode = 'ZAR'; // PayBatch only handles ZAR
        $status       = $invoice['status'];
        $invoiceId    = $invoice['id'];

        if (new DateTime($duedate) <= $today && $status == 'Unpaid' && ($currencycode == 'ZAR' || ! $currencycode)) {
            if ( ! empty($invoice['cardnum'])) {
                $vaultId = $invoice['cardnum'];
                $item    = [];
                $item[]  = 'A'; // Authorisation request
                $item[]  = $invoiceId; // Transaction reference - use invoice id
                $item[]  = $firstname . '_' . $lastname; // User name - not used anywhere
                $item[]  = $vaultId; // Vault Id for client card
                $item[]  = '00'; // Budget period - no budget
                $item[]  = intval($total * 100); // Transaction amount in ZA cents
                $data[]  = $item;
            }
        }
    }

    echo 'Invoices for processing: ' . json_encode($data) . PHP_EOL;

    $errors   = false;
    $invalids = true;
    if (count($data) > 0) {
        while ( ! $errors && $invalids && count($data) > 0) {
            try {
                // Make PayBatch authorisation request
                $soap    = $payBatchSoap->getAuthRequest($data);
                $wsdl    = PAYBATCHAPIWSDL;
                $options = ['trace' => 1, 'login' => $payBatchId, 'password' => $payBatchSecretKey];
                echo 'Options: ' . json_encode($options) . PHP_EOL;

                $soapClient = new SoapClient($wsdl, $options);
                $result     = $soapClient->__soapCall('Auth', [
                    new SoapVar($soap, XSD_ANYXML),
                ]);
                echo 'Result: ' . print_r($result) . PHP_EOL;
                if ($result->Invalid == 0) {
                    $invalids = false;
                    $uploadId = $result->UploadID;
                    // Now make confirmation request to trigger actual payment attempt
                    $confirmXml    = $payBatchSoap->getConfirmRequest($uploadId);
                    $confirmResult = $soapClient->__soapCall('Confirm', [
                        new SoapVar($confirmXml, XSD_ANYXML),
                    ]);
                    if ($confirmResult->Invalid != 0) {
                        $errors = true;
                    }
                } else {
                    foreach ($result->InvalidReason as $invalid) {
                        unset($data[$invalid->Line - 1]);
                    }
                }
            } catch (SoapFault $fault) {
                $errors = true;
                echo $fault->getMessage();
            }
        }

        if ($errors) {
            // Log and die
            die('Could not process batch transaction');
        }

        // Store the upload ids so we can process later
        try {
            $query = "insert into `" . _DB_PREFIX_ . "payhostpaybatch` (recordtype, recordid, recordval)  values ('uploadid', ?, 'true')";
            $stmt  = $dbc->prepare($query);
            $stmt->bind_param('s', $uploadId);
            $stmt->execute();
        } catch (Exception $e) {
            die($e->getMessage());
        }
        die(count($data) . ' invoices were successfully uploaded to PayGate PayBatch for processing');
    } else {
        die('No matching invoices found!');
    }
}

function getInvoices1()
{
    global $dbc;
    $prefix = _DB_PREFIX_;
    $pm     = GATEWAY;

    $query = <<<QUERY
select i.*, firstname, lastname, token cardnum
from {$prefix}invoices i
inner join {$prefix}clients t on i.userid = t.id
inner join {$prefix}payhostpaybatchvaults pay on pay.user_id = i.userid
where i.status = 'Unpaid' and paymentmethod = '{$pm}'
limit 1
QUERY;

    $stmt = $dbc->prepare($query);
    $stmt->execute();
    $result   = $stmt->get_result();
    $invoices = [];
    while ($invoice = $result->fetch_assoc()) {
        $invoices[] = $invoice;
    }

    return $invoices;
}

function getInvoicesApi()
{
    global $api_identifier, $api_secret, $api_url, $api_access_key;
    $postFields = [
        'username'     => $api_identifier,
        'password'     => $api_secret,
        'action'       => 'GetInvoices',
        'status'       => 'Unpaid',
        'responsetype' => 'json',
        'accesskey'    => $api_access_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    $response = curl_exec($ch);
    $error    = curl_error($ch);

    $invoices = [];
    if ($response && $response != '') {
        try {
            $rawInvoices = json_decode($response->invoices->invoice);
            foreach ($rawInvoices as $rawInvoice) {
                if ($rawInvoice->paymentmethod === GATEWAY) {
                }
            }
        } catch (Exception $exception) {
            die('Invoices could not be retrieved: ' . $exception->getMessage());
        }
    }

    return $invoices;
}

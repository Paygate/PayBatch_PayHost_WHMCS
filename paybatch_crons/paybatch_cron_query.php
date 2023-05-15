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

try {
    $batches = [];
    $query   = "select recordid from `" . _DB_PREFIX_ . "payhostpaybatch` where recordtype = 'uploadid'";
    $stmt    = $dbc->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($batch = $result->fetch_assoc()) {
        $batches[] = $batch['recordid'];
    }
    $stmt = null;

    if (($nbatches = count($batches)) > 0) {
        echo 'Query batches: ' . json_encode($batches) . '<br>';
        foreach ($batches as $key => $batch) {
            $queryResult = doPayBatchQuery($batch);
            echo 'Query result: ' . json_encode($queryResult) . '<br>';

            if (intval($queryResult->Unprocessed) == 0) {
                echo 'Unprocessed: ' . $queryResult->Unprocessed;
                if ( ! empty($queryResult->TransResult)) {
                    if ( ! is_array($queryResult->TransResult)) {
                        // Only single result
                        handleLineItem($queryResult->TransResult);
                    } else {
                        foreach ($queryResult->TransResult as $transResult) {
                            handleLineItem($transResult);
                        }
                    }
                    unset($batches[$key]);
                    $query = "delete from `" . _DB_PREFIX_ . "payhostpaybatch` where recordtype = 'uploadid' and recordid = ?";
                    $stmt  = $dbc->prepare($query);
                    $stmt->bind_param('s', $batch);
                    $stmt->execute();
                    $stmt = null;
                }
            }
        }

        die($nbatches . ' PayGate PayBatch batches were queried for payment information and processed');
    } else {
        die('No PayGate PayBatch batches were found for processing');
    }
} catch (Exception $e) {
    die($e->getMessage());
}

function handleLineItem($transResult)
{
    $transResult = explode(',', $transResult);
    $headings    = [
        'txId',
        'txType',
        'txRef',
        'authcode',
        'txStatusCode',
        'txStatusDescription',
        'txResultCode',
        'txResultDescription',
    ];
    $transResult = array_combine($headings, $transResult);
    echo json_encode($transResult);

    $dataApi  = [
        'invoiceid'     => $transResult['txRef'],
        'paymentmethod' => 'payhostpaybatch',
        'status'        => 'Paid',
        'txId'          => $transResult['txId'],
    ];
    $response = updateInvoicesApi($dataApi);
    echo $response;
}

function doPayBatchQuery($uploadId)
{
    global $payBatchSoap;
    global $payBatchId;
    global $payBatchSecretKey;

    $queryXml = $payBatchSoap->getQueryRequest($uploadId);
    $wsdl     = PAYBATCHAPIWSDL;
    $options  = ['trace' => 1, 'login' => $payBatchId, 'password' => $payBatchSecretKey];

    $soapClient  = new SoapClient($wsdl, $options);
    $queryResult = $soapClient->__soapCall('Query', [
        new SoapVar($queryXml, XSD_ANYXML),
    ]);

    return $queryResult;
}

function updateInvoicesApi($data)
{
    addInvoicePayment($data);
}

function markInvoicesPaid($data)
{
    global $api_identifier, $api_secret, $api_url, $api_access_key;
    $postFields = [
        'username'     => $api_identifier,
        'password'     => $api_secret,
        'action'       => 'UpdateInvoice',
        'status'       => 'Paid',
        'responsetype' => 'json',
        'accesskey'    => $api_access_key,
        'invoiceid'    => $data['invoiceid'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    $response = curl_exec($ch);
    $error    = curl_error($ch);

    if ($error == '') {
        return $response;
    } else {
        return $error;
    }
}

function addInvoicePayment($data)
{
    global $api_identifier, $api_secret, $api_url, $api_access_key;

    // Check for duplicate transactions in invoice
    $postFields = [
        'username'     => $api_identifier,
        'password'     => $api_secret,
        'action'       => 'GetTransactions',
        'gateway'      => $data['paymentmethod'],
        'responsetype' => 'json',
        'accesskey'    => $api_access_key,
        'invoiceid'    => $data['invoiceid'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    $response = curl_exec($ch);
    curl_close($ch);

    $isDuplicate  = false;
    $transactions = json_decode($response)->transactions->transaction;
    foreach ($transactions as $transaction) {
        if ($data['txId'] == $transaction->transid) {
            $isDuplicate = true;
        }
    }

    if ( ! $isDuplicate) {
        $postFields = [
            'username'     => $api_identifier,
            'password'     => $api_secret,
            'action'       => 'AddInvoicePayment',
            'gateway'      => $data['paymentmethod'],
            'responsetype' => 'json',
            'transid'      => $data['txId'],
            'accesskey'    => $api_access_key,
            'invoiceid'    => $data['invoiceid'],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error == '') {
            return $response;
        } else {
            return $error;
        }
    }
}

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

// Require libraries needed for gateway module functions
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';

require_once 'payhostpaybatch/lib/constants.php';
require_once 'payhostpaybatch/lib/payhostsoap.class.php';

if ( ! defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

if ( ! defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'tbl');
}

/**
 * Check for existence of payhostpaybatch table and create if not
 */
if ( ! function_exists('createPayhostpaybatchTable')) {
    function createPayhostpaybatchTable()
    {
        $query = "create table if not exists `" . _DB_PREFIX_ . "payhostpaybatch` (";
        $query .= " id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ";
        $query .= " recordtype VARCHAR(20) NOT NULL, ";
        $query .= " recordid VARCHAR(50) NOT NULL, ";
        $query .= " recordval VARCHAR(50) NOT NULL, ";
        $query .= " dbid VARCHAR(10) NOT NULL DEFAULT '1')";

        return full_query($query);
    }
}

/**
 * Check for existence of payhostpaybatchvaults table and create if not
 */
if ( ! function_exists('createPayhostpaybatchVaultTable')) {
    function createPayhostpaybatchVaultTable()
    {
        $query = "create table if not exists `" . _DB_PREFIX_ . "payhostpaybatchvaults` (";
        $query .= " id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ";
        $query .= " user_id INT NOT NULL, ";
        $query .= " token VARCHAR(50) NOT NULL, ";
        $query .= " card_number VARCHAR(50) NOT NULL, ";
        $query .= " card_expiry VARCHAR(10) NOT NULL)";

        return full_query($query);
    }
}

createPayhostpaybatchTable();
createPayhostpaybatchVaultTable();

if (isset($_POST['INITIATE']) && $_POST['INITIATE'] == 'initiate') {
    $params = json_decode(base64_decode($_POST['jparams']), true);
    payhostpaybatch_initiate($params);
}

/**
 * Define module related meta data
 *
 * Values returned here are used to determine module related capabilities and
 * settings
 *
 * @return array
 */
function payhostpaybatch_MetaData()
{
    return array(
        'DisplayName'                => 'PayGate PayHost / PayBatch',
        'APIVersion'                 => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage'           => true,
    );
}

/**
 * Define gateway configuration options
 *
 *
 * @return array
 */
function payhostpaybatch_config()
{
    return array(
        // The friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName'                => array(
            'Type'  => 'System',
            'Value' => 'PayGate PayHost / PayBatch Gateway',
        ),
        // A text field type allows for single line text input
        'payHostID'                   => array(
            'FriendlyName' => 'PayHost ID',
            'Type'         => 'text',
            'Size'         => '11',
            'Default'      => '',
            'Description'  => 'Enter your PayHost ID here',
        ),
        // A password field type allows for masked text input
        'payHostSecretKey'            => array(
            'FriendlyName' => 'PayHost Secret Key',
            'Type'         => 'password',
            'Size'         => '32',
            'Default'      => '',
            'Description'  => 'Enter your PayHost password here',
        ),
        // A text field type allows for single line text input
        'payBatchID'                  => array(
            'FriendlyName' => 'PayBatch ID',
            'Type'         => 'text',
            'Size'         => '11',
            'Default'      => '',
            'Description'  => 'Enter your PayBatch ID here',
        ),
        // A password field type allows for masked text input
        'payBatchSecretKey'           => array(
            'FriendlyName' => 'PayBatch Secret Key',
            'Type'         => 'password',
            'Size'         => '32',
            'Default'      => '',
            'Description'  => 'Enter your PayBatch password here',
        ),
        // The yesno field type displays a single checkbox option
        'testMode'                    => array(
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ),
        // Enable or disable 3D Secure Authentication
        '3D'                          => array(
            'FriendlyName' => '3D Secure Authentication',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
        // Enable or disable card vaulting
        'payhostpaybatch_vaulting'    => array(
            'FriendlyName' => 'Allow card vaulting',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
        // Enable or disable recurring payments
        'payhostpaybatch_recurring'   => array(
            'FriendlyName' => 'Allow recurring payments',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
        // Enable or disable paybatch auto currency conversion
        'payhostpaybatch_autoconvert' => array(
            'FriendlyName' => 'Enable auto convert to ZAR for PayBatch',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
    );
}

/**
 * Payment link
 *
 * Defines the HTML output displayed on an invoice
 *
 * @return string
 */
function payhostpaybatch_link($params)
{
    $jparams  = base64_encode(json_encode($params));
    $vaulting = $params['payhostpaybatch_vaulting'] === 'on';

    // Check for values of correct format stored in tblclients->cardnum : we will use this to store the card vault id
    $vaultIds                 = [];
    $tblpayhostpaybatchvaults = _DB_PREFIX_ . 'payhostpaybatchvaults';
    if ($vaulting) {
        $vaultIds     = [];
        $vaults       = Capsule::table($tblpayhostpaybatchvaults)
                               ->where('user_id', $params['clientdetails']['userid'])
                               ->select()
                               ->get();
        $vaultPattern = '/^[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$/';
        foreach ($vaults as $vault) {
            if (preg_match($vaultPattern, $vault->token) == 1) {
                $vaultIds[] = $vault;
            }
        }
    }

    if ($vaulting) {
        $html = '<h4>Choose a card option</h4>';
    }

    $html .= <<<HTML
    <form method="post" action="modules/gateways/payhostpaybatch.php">
    <input type="hidden" name="INITIATE" value="initiate">
    <input type="hidden" name="jparams" value="$jparams">   
HTML;
    if ($vaulting) {
        $html .= '<select name="card-token">';
        foreach ($vaultIds as $vault_id) {
            $html .= "<option value='$vault_id->id'>Use card $vault_id->card_number</option>";
        }
        $html .= <<<HTML
<option value="no-save">Use a new card and don't save it</option>
    <option value="new-save">Use a new card and save it</option>
</select>
HTML;
    }

    $html .= <<<HTML
    <input type="submit" value="Pay using PayHost">
</form>
HTML;

    return $html;
}

/**
 * Payment process
 *
 * Process payment to PayHost
 *
 * @return string
 */
function payhostpaybatch_initiate($params)
{
    // Check if test mode or not
    $testMode = $params['testMode'];
    if ($testMode == 'on') {
        $payHostId        = PAYGATETESTID;
        $payHostSecretKey = PAYGATETESTKEY;
    } else {
        $payHostId        = $params['payHostID'];
        $payHostSecretKey = $params['payHostSecretKey'];
    }

    $user_id = $params['clientdetails']['id'];

    $handle_card = filter_var($_POST['card-token'], FILTER_SANITIZE_STRING);

    $gatewayModuleName = basename(__FILE__, '.php');
    $html              = '';

    // Check if recurring payments and vaulting are allowed - if not, do not enable PayBatch
    $vaulting = $params['payhostpaybatch_vaulting'] === 'on';
    if ($handle_card === 'no-save') {
        $vaulting = false;
    }
    if ((int)$handle_card > 0) {
        $vault_id = $handle_card;
    }

    $usePayBatch = false;

    // System Parameters
    $companyName       = $params['companyname'];
    $systemUrl         = $params['systemurl'];
    $returnUrl         = $params['returnurl'];
    $langPayNow        = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName        = $params['paymentmethod'];
    $whmcsVersion      = $params['whmcsVersion'];

    // Callback urls
    $notifyUrl = $systemUrl . 'modules/gateways/callback/payhostpaybatch.php';
    $returnUrl = $systemUrl . 'modules/gateways/callback/payhostpaybatch.php';

    // Transaction date
    $transactionDate = date('Y-m-d\TH:i:s');

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname  = $params['clientdetails']['lastname'];
    $email     = $params['clientdetails']['email'];
    $address1  = $params['clientdetails']['address1'];
    $address2  = $params['clientdetails']['address2'];
    $city      = $params['clientdetails']['city'];
    $state     = $params['clientdetails']['state'];
    $postcode  = $params['clientdetails']['postcode'];
    $country   = $params['clientdetails']['country'];
    $phone     = $params['clientdetails']['phonenumber'];

    // Invoice Parameters
    $invoiceId    = $params['invoiceid'];
    $description  = $params["description"];
    $amount       = $params['amount'];
    $currencyCode = $params['currency'];

    // Get vault id from database
    $tblpayhostpaybatch = _DB_PREFIX_ . 'payhostpaybatch';
    if ($vaulting) {
        $tblpayhostpaybatchvaults = _DB_PREFIX_ . 'payhostpaybatchvaults';
        $vaultId                  = Capsule::table($tblpayhostpaybatchvaults)
                                           ->where('user_id', $params['clientdetails']['userid'])
                                           ->where('id', $vault_id)
                                           ->value('token');
        $vaultPattern             = '/^[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$/';
        if (preg_match($vaultPattern, $vaultId) != 1) {
            $vaultId = '';
        }
    }

    // A web payment request - use PayHost WebPayment request for redirect

    $payhostSoap = new payhostsoap();

    // Set data
    $data                  = [];
    $data['pgid']          = $payHostId;
    $data['encryptionKey'] = $payHostSecretKey;
    $data['reference']     = $invoiceId;
    $data['amount']        = intval($amount * 100);
    $data['currency']      = $currencyCode;
    $data['transDate']     = $transactionDate;
    $data['locale']        = 'en-us';
    $data['firstName']     = $firstname;
    $data['lastName']      = $lastname;
    $data['email']         = $email;
    $data['customerTitle'] = isset($data['customerTitle']) ? $data['customerTitle'] : 'Mr';
    $data['country']       = 'ZAF';
    $data['retUrl']        = $returnUrl;
    $data['notifyURL']     = $notifyUrl;
    $data['recurring']     = $usePayBatch;
    $data['userKey1']      = 'user_id';
    $data['userField1']    = $user_id;
    if ($vaulting) {
        $data['vaulting'] = true;
    }
    if ($vaultId != '' && $vaulting) {
        $data['vaultId'] = $vaultId;
    }

    $payhostSoap->setData($data);

    $xml = $payhostSoap->getSOAP();

    // Use PHP SoapClient to handle request
    ini_set('soap.wsdl_cache', 0);
    $soapClient = new SoapClient(PAYHOSTAPIWSDL, ['trace' => 1]);

    try {
        $result = $soapClient->__soapCall(
            'SinglePayment',
            [
                new SoapVar($xml, XSD_ANYXML),
            ]
        );

        if (array_key_exists('Redirect', (array)$result->WebPaymentResponse)) {
            // Redirect to Payment Portal
            // Store key values for return response

            Capsule::table($tblpayhostpaybatch)
                   ->insert(
                       [
                           'recordtype' => 'transactionrecord',
                           'recordid'   => $result->WebPaymentResponse->Redirect->UrlParams[1]->value,
                           'recordval'  => $result->WebPaymentResponse->Redirect->UrlParams[2]->value,
                           'dbid'       => time(),
                       ]
                   );

            // Delete records which are older than 24 hours
            $expiryTime = time() - 24 * 3600;
            Capsule::table($tblpayhostpaybatch)
                   ->where('dbid', '>', 1)
                   ->where('dbid', '<', $expiryTime)
                   ->delete();

            // Do redirect
            // First check that the checksum is valid
            $d = $result->WebPaymentResponse->Redirect->UrlParams;

            $checkSource = $d[0]->value;
            $checkSource .= $d[1]->value;
            $checkSource .= $d[2]->value;
            $checkSource .= $payHostSecretKey;
            $check       = md5($checkSource);
            if ($check == $d[3]->value) {
                $inputs = $d;

                $html = <<<HTML
        <form action="{$result->WebPaymentResponse->Redirect->RedirectUrl}" method="post" name="payhost">
        <input type="hidden" name="{$inputs[0]->key}" value="{$inputs[0]->value}" />
        <input type="hidden" name="{$inputs[1]->key}" value="{$inputs[1]->value}" />
        <input type="hidden" name="{$inputs[2]->key}" value="{$inputs[2]->value}" />
        <input type="hidden" name="{$inputs[3]->key}" value="{$inputs[3]->value}" />
        </form>
        <script type="text/javascript"> document.forms['payhost'].submit();</script>
HTML;
                echo $html;
            }
        } else {
            // Process response - doesn't happen
        }
    } catch (SoapFault $f) {
        var_dump($f);
    }
    echo $html;
}

/**
 * Refund transaction
 *
 * Called when a refund is requested for a previously successful transaction
 *
 * @return array Transaction response status
 */
function payhostpaybatch_refund($params)
{
    // Gateway Configuration Parameters
    $accountId     = $params['accountID'];
    $secretKey     = $params['secretKey'];
    $testMode      = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField    = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount          = $params['amount'];
    $currencyCode          = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname  = $params['clientdetails']['lastname'];
    $email     = $params['clientdetails']['email'];
    $address1  = $params['clientdetails']['address1'];
    $address2  = $params['clientdetails']['address2'];
    $city      = $params['clientdetails']['city'];
    $state     = $params['clientdetails']['state'];
    $postcode  = $params['clientdetails']['postcode'];
    $country   = $params['clientdetails']['country'];
    $phone     = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName       = $params['companyname'];
    $systemUrl         = $params['systemurl'];
    $langPayNow        = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName        = $params['paymentmethod'];
    $whmcsVersion      = $params['whmcsVersion'];

    // Perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status'  => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees'    => $feeAmount,
    );
}

/**
 * Cancel subscription
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user
 *
 * @return array Transaction response status
 */
function payhostpaybatch_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $accountId     = $params['accountID'];
    $secretKey     = $params['secretKey'];
    $testMode      = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField    = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName       = $params['companyname'];
    $systemUrl         = $params['systemurl'];
    $langPayNow        = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName        = $params['paymentmethod'];
    $whmcsVersion      = $params['whmcsVersion'];

    // Perform API call to cancel subscription and interpret result

    return array(
        // 'success' if successful, any other value for failure
        'status'  => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
    );
}

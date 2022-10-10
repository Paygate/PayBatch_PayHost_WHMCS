<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace WHMCS\Module\Addon\PayhostPaybatch\Client;

use SoapClient;
use SoapFault;
use SoapVar;
use WHMCS\Database\Capsule;

/**
 * Client Area Controller
 */
class Controller
{

    /**
     * Index action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return array
     */
    public function index($vars)
    {
        return (new ClientDispatcher())->dispatch('secret', []);
    }

    /**
     * Secret action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return array
     */
    public function secret($vars)
    {
        if ( ! defined('_DB_PREFIX_')) {
            define('_DB_PREFIX_', 'tbl');
        }

        // Get the stored tokens for the logged-in user
        $userId                   = $_SESSION['uid'] ?? null;
        $tblpayhostpaybatchvaults = _DB_PREFIX_ . 'payhostpaybatchvaults';
        $tokens                   = Capsule::table($tblpayhostpaybatchvaults)
                                           ->select()
                                           ->where('user_id', $userId)
                                           ->get();

        $cardDetails = [];
        foreach ($tokens as $tokenValue) {
            $cardDetail['token']      = $tokenValue->token;
            $cardDetail['cardNumber'] = $tokenValue->card_number;
            $cardDetail['expDate']    = $tokenValue->card_expiry;
            array_push($cardDetails, $cardDetail);
        }

        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule

        return array(
            'pagetitle'    => 'PayHost PayBatch Addon Module',
            'breadcrumb'   => array(
                'index.php?m=payhostpaybatch'               => 'PayHost PayBatch Addon Module',
                'index.php?m=payhostpaybatch&action=secret' => 'Token Page',
            ),
            'templatefile' => 'secretpage',
            'requirelogin' => true, // Set true to restrict access to authenticated client users
            'forcessl'     => false, // Deprecated as of Version 7.0. Requests will always use SSL if available.
            'vars'         => array(
                'modulelink'      => $modulelink,
                'configTextField' => json_encode($cardDetails),
                'customVariable'  => 'your own content goes here',
                'userId'          => $userId,
                'userTokens'      => $cardDetails,
            ),
        );
    }

    public function deleteToken($vars)
    {
        if ( ! defined('_DB_PREFIX_')) {
            define('_DB_PREFIX_', 'tbl');
        }

        $tokenId = $_GET['tokenId'] ?? null;
        if ($tokenId) {
            $userId                   = $_SESSION['uid'] ?? null;
            $tblpayhostpaybatchvaults = _DB_PREFIX_ . 'payhostpaybatchvaults';
            Capsule::table($tblpayhostpaybatchvaults)
                   ->where('user_id', $userId)
                   ->where('token', $tokenId)
                   ->delete();
        }
        $dispatcher = new ClientDispatcher();

        return $dispatcher->dispatch('secret', []);
    }

    private function getVaultCard($vaultId)
    {
        $gatewaySettings = $this->getPayhostGatewayDetail();
        // Check for test mode
        if ($gatewaySettings['testMode'] == 'on') {
            $payHostId        = '10011072130';
            $payHostSecretKey = 'test';
        } else {
            $payHostId        = $gatewaySettings['payHostID'];
            $payHostSecretKey = $gatewaySettings['payHostSecretKey'];
        }

        $soap = <<<SOAP
<ns1:SingleVaultRequest>
    <ns1:LookUpVaultRequest>
        <ns1:Account>
            <ns1:PayGateId>$payHostId</ns1:PayGateId>
            <ns1:Password>$payHostSecretKey</ns1:Password>
        </ns1:Account>
        <ns1:VaultId>$vaultId</ns1:VaultId>
    </ns1:LookUpVaultRequest>
</ns1:SingleVaultRequest>
SOAP;

        $wsdl = 'https://secure.paygate.co.za/payhost/process.trans?wsdl';
        $sc   = new SoapClient($wsdl, ['trace' => 1]);
        try {
            $result = $sc->__soapCall('SingleVault', [
                new SoapVar($soap, XSD_ANYXML),
            ]);
        } catch (SoapFault $f) {
            return json_encode($f);
        }

        return $result;
    }

    private function getPayhostGatewayDetail()
    {
        if ( ! defined('_DB_PREFIX_')) {
            define('_DB_PREFIX_', 'tbl');
        }
        $tblpaymentgateways = _DB_PREFIX_ . 'paymentgateways';
        $gatewayValues      = Capsule::table($tblpaymentgateways)
                                     ->select('setting', 'value')
                                     ->where('gateway', 'payhostpaybatch')
                                     ->get();
        $gatewaySettings    = [];
        foreach ($gatewayValues as $gatewayValue) {
            $gatewaySettings[$gatewayValue->setting] = $gatewayValue->value;
        }

        return $gatewaySettings;
    }
}

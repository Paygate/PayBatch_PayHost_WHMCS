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

if ( !defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

use WHMCS\Database\Capsule;

/**
 * Global defines used for the two endpoints
 */
define( "PAYHOSTAPI", 'https://secure.paygate.co.za/payhost/process.trans' );
define( "PAYHOSTAPIWSDL", 'https://secure.paygate.co.za/payhost/process.trans/?wsdl' );
define( "PAYBATCHAPI", 'https://secure.paygate.co.za/paybatch/1.2/process.trans' );
define( "PAYBATCHAPIWSDL", 'https://secure.paygate.co.za/paybatch/1.2/PayBatch.wsdl' );
define( "PAYGATETESTID", '10011072130' );
define( "PAYGATETESTKEY", 'test' );

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
        'FriendlyName'              => array(
            'Type'  => 'System',
            'Value' => 'PayGate PayHost / PayBatch Gateway',
        ),
        // A text field type allows for single line text input
        'payHostID'                 => array(
            'FriendlyName' => 'PayHost ID',
            'Type'         => 'text',
            'Size'         => '11',
            'Default'      => '',
            'Description'  => 'Enter your PayHost ID here',
        ),
        // A password field type allows for masked text input
        'payHostSecretKey'          => array(
            'FriendlyName' => 'PayHost Secret Key',
            'Type'         => 'password',
            'Size'         => '32',
            'Default'      => '',
            'Description'  => 'Enter your PayHost password here',
        ),
        // A text field type allows for single line text input
        'payBatchID'                => array(
            'FriendlyName' => 'PayBatch ID',
            'Type'         => 'text',
            'Size'         => '11',
            'Default'      => '',
            'Description'  => 'Enter your PayBatch ID here',
        ),
        // A password field type allows for masked text input
        'payBatchSecretKey'         => array(
            'FriendlyName' => 'PayBatch Secret Key',
            'Type'         => 'password',
            'Size'         => '32',
            'Default'      => '',
            'Description'  => 'Enter your PayBatch password here',
        ),
        // The yesno field type displays a single checkbox option
        'testMode'                  => array(
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ),
        // Enable or disable 3D Secure Authentication
        '3D'                        => array(
            'FriendlyName' => '3D Secure Authentication',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
        // Enable or disable card vaulting
        'payhostpaybatch_vaulting'  => array(
            'FriendlyName' => 'Allow card vaulting',
            'Type'         => 'yesno',
            'Default'      => 'Yes',
        ),
        // Enable or disable recurring payments
        'payhostpaybatch_recurring' => array(
            'FriendlyName' => 'Allow recurring payments',
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
function payhostpaybatch_link( $params )
{
    // Check if test mode or not
    $testMode = $params['testMode'];
    if ( $testMode == 'on' ) {
        $payHostId         = PAYGATETESTID;
        $payBatchId        = PAYGATETESTID;
        $payHostSecretKey  = PAYGATETESTKEY;
        $payBatchSecretKey = PAYGATETESTKEY;
    } else {
        $payHostId         = $params['payHostId'];
        $payBatchId        = $params['payBatchId'];
        $payHostSecretKey  = $params['payHostSecretKey'];
        $payBatchSecretKey = $params['payBatchSecretKey'];
    }

    $html = '';

    // Check if recurring payments and vaulting are allowed - if not, do not enable PayBatch
    $recurring   = $params['payhostpaybatch_recurring'];
    $vaulting    = $params['payhostpaybatch_vaulting'];
    $usePayBatch = false;
    if ( $recurring == 'on' && $vaulting == 'on' ) {
        $usePayBatch = true;
    }

    // System Parameters
    $companyName                             = $params['companyname'];
    $systemUrl                               = $params['systemurl'];
    $_SESSION['_PAYHOSTPAYBATCH_SYSTEM_URL'] = $systemUrl;
    $returnUrl                               = $params['returnurl'];
    $langPayNow                              = $params['langpaynow'];
    $moduleDisplayName                       = $params['name'];
    $moduleName                              = $params['paymentmethod'];
    $whmcsVersion                            = $params['whmcsVersion'];

    // Callback urls
    $notifyUrl = $systemUrl . 'modules/gateways/callback/payhostpaybatch.php';
    $returnUrl = $systemUrl . 'modules/gateways/callback/payhostpaybatch.php';

    // Transaction date
    $transactionDate = date( 'Y-m-d\TH:i:s' );

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

    // Check for a value stored in tblclients->cardnum : we will use this to store the card vault id
    $vaultId = Capsule::table( 'tblclients' )
        ->where( 'id', $params['clientdetails']['userid'] )
        ->value( 'cardnum' );

    if ( isset( $vaultId ) && $vaultId != '' && $usePayBatch === true ) {
        // A vault transaction - use PayBatch with single entry
        $payBatchSoap = new paybatchsoap( $notifyUrl );

        if ( $currencyCode !== 'ZAR' ) {
            die( 'ZAR is required for PayBatch. Cannot proceed.' );
        }

        $data   = [];
        $item   = [];
        $item[] = 'A'; // Authorisation request
        $item[] = $invoiceId; // Transaction reference - use invoice id
        $item[] = $lastname . '_' . $firstname; // User name - not used anywhere
        $item[] = $vaultId; // Vault Id for client card
        $item[] = '00'; // Budget period - no budget
        $item[] = intval( $amount * 100 ); // Transaction amount in ZA cents
        $data[] = $item;

        // Use SoapClient to make request
        $soap       = $payBatchSoap->getAuthRequest( $data );
        $wsdl       = PAYBATCHAPIWSDL;
        $options    = ['trace' => 1, 'login' => $payBatchId, 'password' => $payBatchSecretKey];
        $soapClient = new SoapClient( $wsdl, $options );
        try {
            $result = $soapClient->__soapCall( 'Auth', [
                new SoapVar( $soap, XSD_ANYXML ),
            ] );
            if ( $result->Invalid == 0 ) {
                // Detect module name from filename.
                $gatewayModuleName = basename( __FILE__, '.php' );

                $command = 'AddInvoicePayment';
                $data    = [
                    'invoiceid' => $invoiceId,
                    'transid'   => $result->UploadID,
                    'gateway'   => $gatewayModuleName,
                ];
                $result = localAPI( $command, $data );
                logTransaction( $gatewayModuleName, (array) $result, 'success' );
                callback3DSecureRedirect( $invoiceId, true );
            }
        } catch ( SoapFault $fault ) {

        }

    } else {
        // A web payment request - use PayHost WebPayment request for redirect

        $payhostSoap = new payhostsoap();

        // Set data
        $data                  = [];
        $data['pgid']          = $payHostId;
        $data['encryptionKey'] = $payHostSecretKey;
        $data['reference']     = $invoiceId;
        $data['amount']        = intval( $amount * 100 );
        $data['currency']      = $currencyCode;
        $data['transDate']     = $transactionDate;
        $data['locale']        = 'en-us';
        $data['firstName']     = $firstname;
        $data['lastName']      = $lastname;
        $data['email']         = $email;
        $data['customerTitle'] = isset( $data['customerTitle'] ) ? $data['customerTitle'] : 'Mr';
        $data['country']       = 'ZAF';
        $data['retUrl']        = $returnUrl;
        $data['notifyURL']     = $notifyUrl;
        $data['recurring']     = $usePayBatch;
        $payhostSoap->setData( $data );

        $xml = $payhostSoap->getSOAP();

        // Use PHP SoapClient to handle request
        ini_set( 'soap.wsdl_cache', 0 );
        $soapClient = new SoapClient( PAYHOSTAPIWSDL, ['trace' => 1] );

        try {
            $result = $soapClient->__soapCall( 'SinglePayment', [
                new SoapVar( $xml, XSD_ANYXML ),
            ] );
            if ( array_key_exists( 'Redirect', $result->WebPaymentResponse ) ) {
                // Redirect to Payment Portal
                // Store key values for return response
                $_SESSION['PAY_REQUEST_ID'] = $result->WebPaymentResponse->Redirect->UrlParams[1]->value;
                $_SESSION['REFERENCE']      = $result->WebPaymentResponse->Redirect->UrlParams[2]->value;

                // Do redirect
                // First check that the checksum is valid
                $d = $result->WebPaymentResponse->Redirect->UrlParams;

                $checkSource = $d[0]->value;
                $checkSource .= $d[1]->value;
                $checkSource .= $d[2]->value;
                $checkSource .= $payHostSecretKey;
                $check = md5( $checkSource );
                if ( $check == $d[3]->value ) {
                    $inputs = $d;

                    $html = <<<HTML
        <form action="{$result->WebPaymentResponse->Redirect->RedirectUrl}" method="post" name="payhost">
        <input type="hidden" name="{$inputs[0]->key}" value="{$inputs[0]->value}" />
        <input type="hidden" name="{$inputs[1]->key}" value="{$inputs[1]->value}" />
        <input type="hidden" name="{$inputs[2]->key}" value="{$inputs[2]->value}" />
        <input type="hidden" name="{$inputs[3]->key}" value="{$inputs[3]->value}" />
        </form>
        <script type="text/javascript">
        document.forms['payhost'].submit();
</script>
HTML;

//                    return $html;
                }
            } else {
                // Process response - doesn't happen
            }
        } catch ( SoapFault $f ) {
            var_dump( $f );
        }
    }
    return $html;
}

/**
 * Refund transaction
 *
 * Called when a refund is requested for a previously successful transaction
 *
 * @return array Transaction response status
 */
function payhostpaybatch_refund( $params )
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

    // perform API call to initiate refund and interpret result

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
function payhostpaybatch_cancelSubscription( $params )
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

/**
 * Class payhostsoap
 * Helper class to make SOAP call to PayHost endpoint
 */
class payhostsoap
{
    /**
     * @var string the url of the PayGate PayHost process page
     */
    public static $process_url = 'https://secure.paygate.co.za/PayHost/process.trans';

    /**
     * @var string the url of the PayGate PayHost WSDL
     */
    public static $wsdl = 'https://secure.paygate.co.za/payhost/process.trans?wsdl';

    /**
     * @var string default namespace. We add the namespace manually because of PHP's "quirks"
     */
    private static $ns = 'ns1';

    // Standard Inputs
    protected $pgid;
    protected $reference;
    protected $amount;
    protected $currency;
    protected $transDate;
    protected $locale;
    protected $payMethod;
    protected $payMethodDetail;
    protected $encryptionKey;

    // Customer Details
    protected $customerTitle;
    protected $firstName;
    protected $middleName;
    protected $lastName;
    protected $telephone;
    protected $mobile;
    protected $fax;
    protected $email;
    protected $dateOfBirth;
    protected $socialSecurity;

    // Address Details
    protected $addressLine1;
    protected $addressLine2;
    protected $addressLine3;
    protected $zip;
    protected $city;
    protected $state;
    protected $country;

    // Address checkboxes
    protected $incCustomer = true;
    protected $incBilling  = true;
    protected $incShipping;

    // Shipping Details
    protected $deliveryDate;
    protected $deliveryMethod;
    protected $installRequired;

    // Redirect Details
    protected $retUrl;
    protected $notifyURL;
    protected $target;

    // Risk
    protected $riskAccNum;
    protected $riskIpAddr;

    // Airline
    protected $ticketNumber;
    protected $PNR;
    protected $travellerType;
    protected $departureAirport;
    protected $departureCountry;
    protected $departureCity;
    protected $departureDateTime;
    protected $arrivalAirport;
    protected $arrivalCountry;
    protected $arrivalCity;
    protected $arrivalDateTime;
    protected $marketingCarrierCode;
    protected $marketingCarrierName;
    protected $issuingCarrierCode;
    protected $issuingCarrierName;
    protected $flightNumber;

    // Recurring orders
    protected $recurring;

    public static $DEFAULT_PGID           = '10011072130';
    public static $DEFAULT_AMOUNT         = 3299;
    public static $DEFAULT_CURRENCY       = 'ZAR';
    public static $DEFAULT_LOCALE         = 'en-us';
    public static $DEFAULT_ENCRYPTION_KEY = 'test';
    public static $DEFAULT_TITLE          = 'Mr';
    public static $DEFAULT_FIRST_NAME     = 'PayGate';
    public static $DEFAULT_LAST_NAME      = 'Test';
    public static $DEFAULT_EMAIL          = 'itsupport@paygate.co.za';
    public static $DEFAULT_COUNTRY        = 'ZAF';
    public static $DEFAULT_NOTIFY_URL     = 'http://www.gatewaymanagementservices.com/ws/gotNotify.php';
    public static $DEFAULT_PAY_METHOD     = 'CC';

    public function __construct()
    {

    }

    public function setData( $data )
    {
        foreach ( $data as $key => $value ) {
            $k        = $key;
            $this->$k = $value;
        }
    }

    public function getSOAP()
    {
        $xml = <<<XML
<{$this::$ns}:SinglePaymentRequest>
<{$this::$ns}:WebPaymentRequest>
{$this->getAccount()}
{$this->getCustomer()}
{$this->getVault( $this->recurring )}
{$this->getPaymentType()}
{$this->getRedirect()}
{$this->getOrder()}
{$this->getRisk()}
{$this->getUserFields()}
</{$this::$ns}:WebPaymentRequest>
</{$this::$ns}:SinglePaymentRequest>
XML;

        $xml = preg_replace( "/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $xml ); // Remove empty lines to make the plain text request prettier

        return $xml;
    }

    private function getVault( $recurring )
    {
        if ( $recurring !== true ) {
            return '';
        }

        $token = null;
        if ( $token == null || $token == '' ) {
            // If token is not already stored under member then add element to request a vault transaction
            $vault = <<<VAULT
<!-- Vault Detail -->
    <{$this::$ns}:Vault>true</{$this::$ns}:Vault>
VAULT;
            return $vault;
        } else {
            // Return the Vault element with valid token
            $vault = <<<VAULT
    <{$this::$ns}:VaultId>{$token}</{$this::$ns}:VaultId>
VAULT;

            return $vault;
        }
    }

    private function getAccount()
    {
        $account = <<<XML
<!-- Account Details -->
    <{$this::$ns}:Account>
    <{$this::$ns}:PayGateId>{$this->pgid}</{$this::$ns}:PayGateId>
    <{$this::$ns}:Password>{$this->encryptionKey}</{$this::$ns}:Password>
    </{$this::$ns}:Account>
XML;

        return $account;
    }

    private function getCustomer()
    {

        $middleName     = ( $this->middleName != '' ? "<{$this::$ns}:MiddleName>{$this->middleName}</{$this::$ns}:MiddleName>" : '' );
        $telephone      = ( $this->telephone != '' ? "<{$this::$ns}:Telephone>{$this->telephone}</{$this::$ns}:Telephone>" : '' );
        $mobile         = ( $this->mobile != '' ? "<{$this::$ns}:Mobile>{$this->mobile}</{$this::$ns}:Mobile>" : '' );
        $fax            = ( $this->fax != '' ? "<{$this::$ns}:Fax>{$this->fax}</{$this::$ns}:Fax>" : '' );
        $dateOfBirth    = ( $this->dateOfBirth != '' ? "<{$this::$ns}:DateOfBirth>{$this->dateOfBirth}</{$this::$ns}:DateOfBirth>" : '' );
        $socialSecurity = ( $this->socialSecurity != '' ? "<{$this::$ns}:SocialSecurityNumber>{$this->socialSecurity}</{$this::$ns}:SocialSecurityNumber>" : '' );
        $address        = ( isset( $this->incCustomer ) ? $this->getAddress() : '' );

        $customer = <<<XML
<!-- Customer Details -->
    <{$this::$ns}:Customer>
    <{$this::$ns}:Title>{$this->customerTitle}</{$this::$ns}:Title>
    <{$this::$ns}:FirstName>{$this->firstName}</{$this::$ns}:FirstName>
    {$middleName}
    <{$this::$ns}:LastName>{$this->lastName}</{$this::$ns}:LastName>
    {$telephone}
    {$mobile}
    {$fax}
    <{$this::$ns}:Email>{$this->email}</{$this::$ns}:Email>
    {$dateOfBirth}
    {$socialSecurity}
    {$address}
    </{$this::$ns}:Customer>
XML;

        return $customer;
    }

    private function getAddress()
    {

        $address1 = ( $this->addressLine1 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine1}</{$this::$ns}:AddressLine>" : '' );
        $address2 = ( $this->addressLine2 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine2}</{$this::$ns}:AddressLine>" : '' );
        $address3 = ( $this->addressLine3 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine3}</{$this::$ns}:AddressLine>" : '' );
        $city     = ( $this->city != '' ? "<{$this::$ns}:City>{$this->city}</{$this::$ns}:City>" : '' );
        $country  = ( $this->country != '' ? "<{$this::$ns}:Country>{$this->country}</{$this::$ns}:Country>" : 'ZAF' );
        $state    = ( $this->state != '' ? "<{$this::$ns}:State>{$this->state}</{$this::$ns}:State>" : '' );
        $zip      = ( $this->zip != '' ? "<{$this::$ns}:Zip>{$this->zip}</{$this::$ns}:Zip>" : '' );

        $address = <<<XML
<!-- Address Details -->
    <{$this::$ns}:Address>
    {$address1}
    {$address2}
    {$address3}
    {$city}
    {$country}
    {$state}
    {$zip}
    </{$this::$ns}:Address>
XML;

        return $address;
    }

    private function getPaymentType()
    {
        $paymentType = '';

        if ( $this->payMethod != '' || $this->payMethodDetail != '' ) {
            $payMethod       = ( $this->payMethod != '' ? "<{$this::$ns}:Method>{$this->payMethod}</{$this::$ns}:Method>" : '' );
            $payMethodDetail = ( $this->payMethodDetail != '' ? "<{$this::$ns}:Detail>{$this->payMethodDetail}</{$this::$ns}:Detail>" : '' );

            $paymentType = <<<XML
<!-- Payment Type Details -->
    <{$this::$ns}:PaymentType>
    {$payMethod}
    {$payMethodDetail}
    </{$this::$ns}:PaymentType>
XML;
        }

        return $paymentType;
    }

    private function getRedirect()
    {
        $target = ( isset( $this->target ) && $this->target != '' ? '<' . $this::$ns . ':Target>' . $this->target . '</' . $this::$ns . ':Target>' : '' );

        $redirect = <<<XML
<!-- Redirect Details -->
    <{$this::$ns}:Redirect>
    <{$this::$ns}:NotifyUrl>{$this->notifyURL}</{$this::$ns}:NotifyUrl>
    <{$this::$ns}:ReturnUrl>{$this->retUrl}</{$this::$ns}:ReturnUrl>
    {$target}
    </{$this::$ns}:Redirect>
XML;

        return $redirect;
    }

    private function getBillingDetails()
    {
        $billing = '';

        if ( isset( $this->incBilling ) ) {
            $billing = <<<XML
<{$this::$ns}:BillingDetails>
{$this->getCustomer()}
{$this->getAddress()}
</{$this::$ns}:BillingDetails>
XML;
        }

        return $billing;
    }

    private function getShippingDetails()
    {
        $shipping = '';

        if ( isset( $this->incShipping ) || $this->deliveryDate != '' || $this->deliveryMethod != '' || isset( $this->installRequired ) ) {

            $address         = ( isset( $this->incShipping ) ? $this->getAddress() : '' );
            $deliveryDate    = ( $this->deliveryDate != '' ? "<{$this::$ns}:DeliveryDate>{$this->deliveryDate}</{$this::$ns}:DeliveryDate>" : '' );
            $deliveryMethod  = ( $this->deliveryMethod != '' ? "<{$this::$ns}:DeliveryMethod>{$this->deliveryMethod}</{$this::$ns}:DeliveryMethod>" : '' );
            $installRequired = ( $this->installRequired != '' ? "<{$this::$ns}:InstallationRequested>{$this->installRequired}</{$this::$ns}:InstallationRequested>" : '' );

            $shipping = <<<XML
<{$this::$ns}:ShippingDetails>
{$this->getCustomer()}
{$address}
{$deliveryDate}
{$deliveryMethod}
{$installRequired}
</{$this::$ns}:ShippingDetails>
XML;
        }

        return $shipping;
    }

    private function getOrder()
    {

        $order = <<<XML
<!-- Order Details -->
    <{$this::$ns}:Order>
    <{$this::$ns}:MerchantOrderId>{$this->reference}</{$this::$ns}:MerchantOrderId>
    <{$this::$ns}:Currency>{$this->currency}</{$this::$ns}:Currency>
    <{$this::$ns}:Amount>{$this->amount}</{$this::$ns}:Amount>
    <{$this::$ns}:TransactionDate>{$this->transDate}</{$this::$ns}:TransactionDate>
    {$this->getBillingDetails()}
    {$this->getShippingDetails()}
    {$this->getAirlineFields()}
    <{$this::$ns}:Locale>{$this->locale}</{$this::$ns}:Locale>
    </{$this::$ns}:Order>
XML;

        return $order;
    }

    private function getRisk()
    {
        $risk = '';

        if ( $this->riskAccNum != '' && $this->riskIpAddr != '' ) {
            $risk = <<<XML
<!-- Risk Details -->
<{$this::$ns}:Risk>
<{$this::$ns}:AccountNumber>{$this->riskAccNum}</{$this::$ns}:AccountNumber>
<{$this::$ns}:IpV4Address>{$this->riskIpAddr}</{$this::$ns}:IpV4Address>
</{$this::$ns}:Risk>
XML;
        }

        return $risk;
    }

    private function getUserFields()
    {

        $userDefined = '<!-- User Fields -->' . PHP_EOL;
        $i           = 1;

        while ( $i >= 1 ) {
            if ( isset( $this->{'userKey' . $i} ) && $this->{'userKey' . $i} != '' && isset( $this->{'userField' . $i} ) && $this->{'userField' . $i} != '' ) {

                $key   = $this->{'userKey' . $i};
                $value = $this->{'userField' . $i};

                $userDefined
                .= <<<XML
    <{$this::$ns}:UserDefinedFields>
    <{$this::$ns}:key>{$key}</ns1:key>
    <{$this::$ns}:value>{$value}</ns1:value>
    </{$this::$ns}:UserDefinedFields>

XML;
                $i++;
            } else {
                break;
            }
        }

        return $userDefined;
    }

    private function getPassenger()
    {
        $middleName     = ( $this->middleName != '' ? "<{$this::$ns}:MiddleName>{$this->middleName}</{$this::$ns}:MiddleName>" : '' );
        $telephone      = ( $this->telephone != '' ? "<{$this::$ns}:Telephone>{$this->telephone}</{$this::$ns}:Telephone>" : '' );
        $mobile         = ( $this->mobile != '' ? "<{$this::$ns}:Mobile>{$this->mobile}</{$this::$ns}:Mobile>" : '' );
        $fax            = ( $this->fax != '' ? "<{$this::$ns}:Fax>{$this->fax}</{$this::$ns}:Fax>" : '' );
        $dateOfBirth    = ( $this->dateOfBirth != '' ? "<{$this::$ns}:DateOfBirth>{$this->dateOfBirth}</{$this::$ns}:DateOfBirth>" : '' );
        $socialSecurity = ( $this->socialSecurity != '' ? "<{$this::$ns}:SocialSecurityNumber>{$this->socialSecurity}</{$this::$ns}:SocialSecurityNumber>" : '' );

        $passenger = <<<XML
<{$this::$ns}:Passenger>
<{$this::$ns}:Title>{$this->customerTitle}</{$this::$ns}:Title>
<{$this::$ns}:FirstName>{$this->firstName}</{$this::$ns}:FirstName>
{$middleName}
<{$this::$ns}:LastName>{$this->lastName}</{$this::$ns}:LastName>
{$telephone}
{$mobile}
{$fax}
<{$this::$ns}:Email>{$this->email}</{$this::$ns}:Email>
{$dateOfBirth}
{$socialSecurity}
</{$this::$ns}:Passenger>

XML;

        return $passenger;
    }

    private function getFlightLegs()
    {
        $flightLeg = <<<XML
<{$this::$ns}:FlightLegs>
<{$this::$ns}:DepartureAirport>{$this->departureAirport}</{$this::$ns}:DepartureAirport>
<{$this::$ns}:DepartureCountry>{$this->departureCountry}</{$this::$ns}:DepartureCountry>
<{$this::$ns}:DepartureCity>{$this->departureCity}</{$this::$ns}:DepartureCity>
<{$this::$ns}:DepartureDateTime>{$this->departureDateTime}</{$this::$ns}:DepartureDateTime>
<{$this::$ns}:ArrivalAirport>{$this->arrivalAirport}</{$this::$ns}:ArrivalAirport>
<{$this::$ns}:ArrivalCountry>{$this->arrivalCountry}</{$this::$ns}:ArrivalCountry>
<{$this::$ns}:ArrivalCity>{$this->arrivalCity}</{$this::$ns}:ArrivalCity>
<{$this::$ns}:ArrivalDateTime>{$this->arrivalDateTime}</{$this::$ns}:ArrivalDateTime>
<{$this::$ns}:MarketingCarrierCode>{$this->marketingCarrierCode}</{$this::$ns}:MarketingCarrierCode>
<{$this::$ns}:MarketingCarrierName>{$this->marketingCarrierName}</{$this::$ns}:MarketingCarrierName>
<{$this::$ns}:IssuingCarrierCode>{$this->issuingCarrierCode}</{$this::$ns}:IssuingCarrierCode>
<{$this::$ns}:IssuingCarrierName>{$this->issuingCarrierName}</{$this::$ns}:IssuingCarrierName>
<{$this::$ns}:FlightNumber>{$this->flightNumber}</{$this::$ns}:FlightNumber>
<{$this::$ns}:BaseFareAmount>{$this->amount}</{$this::$ns}:BaseFareAmount>
<{$this::$ns}:BaseFareCurrency>{$this->currency}</{$this::$ns}:BaseFareCurrency>
</{$this::$ns}:FlightLegs>
XML;

        return $flightLeg;
    }

    private function getAirlineFields()
    {
        $airline = '';

        if ( $this->PNR != '' ) {
            $airline = <<<XML
<{$this::$ns}:AirlineBookingDetails>
<{$this::$ns}:TicketNumber>{$this->ticketNumber}</{$this::$ns}:TicketNumber>
<{$this::$ns}:PNR>{$this->PNR}</{$this::$ns}:PNR>
<{$this::$ns}:Passengers>
{$this->getPassenger()}
<{$this::$ns}:TravellerType>{$this->travellerType}</{$this::$ns}:TravellerType>
</{$this::$ns}:Passengers>
{$this->getFlightLegs()}
</{$this::$ns}:AirlineBookingDetails>
XML;
        }

        return $airline;
    }

    public function getSOAPData()
    {
        $data                                 = [];
        $data['WebPaymentRequest']            = [];
        $data['WebPaymentRequest']['Account'] = $this->getAccountData();

        return $data;
    }

    private function getAccountData()
    {
        $PayGateId = $this->pgid;
        $Password  = $this->encryptionKey;
        return ['PayGateId' => $PayGateId, 'Password' => $Password];
    }
}

/**
 * Class paybatchsoap
 * Helper class for making SOAP requests to PayBatch API
 */
class paybatchsoap
{
    /**
     * @var string the url of the PayGate PayBatch process page
     */
    public static $process_url = 'https://secure.paygate.co.za/paybatch/1.2/process.trans';

    /**
     * @var string the url of the PayGate PayBatch WSDL
     */
    public static $wsdl = 'https://secure.paygate.co.za/paybatch/1.2/PayBatch.wsdl';

    /**
     * @var string default namespace. We add the namespace manually because of PHP's "quirks"
     */
    private static $ns = 'ns1';

    /**
     * @var string $notifyUrl
     */
    private static $notifyUrl;

    /**
     * @var string $soapStart , $soapEnd
     * SOAP HEADERS
     */
    private static $soapStart = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>';
    private static $soapend = '</SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

    /**
     * @var array of data for batchline
     */
    protected $batchData = [];

    protected $batchReference = 'PayBatch_';

    public function __construct( $notifyUrl )
    {
        $this->batchReference .= date( 'Y-m-d' ) . '_' . uniqid();
        $this::$notifyUrl = $notifyUrl;
    }

    /**
     * @param $data array of batchline type
     */
    public function setBatchData( $data )
    {
        foreach ( $data as $line ) {
            $this->batchData[] = $line;
        }
    }

    /**
     * @param $data input data array
     */
    public function getAuthRequest( $data )
    {
        $this->setBatchData( $data );
        try {
            // Use SimpleXMLElement to build structure
            $xml = new SimpleXMLElement( '<Auth />' );
            $xml->addChild( 'BatchReference', $this->batchReference );
            $xml->addChild( 'NotificationUrl', $this::$notifyUrl );

            $batchData = $xml->addChild( 'BatchData' );
            foreach ( $this->batchData as $line ) {
                $batchLine = '';
                foreach ( $line as $item ) {
                    $batchLine .= $item . ',';
                }
                $batchLine = rtrim( $batchLine, ',' );
                $batchData->addChild( 'BatchLine', $batchLine );
            }

            // Remove XML headers
            $dom = new DOMDocument();
            $dom->loadXML( $xml->asXML() );

            $soap = $dom->saveXML( $dom->documentElement );

            // Remove Auth tag - added in __soapCall
            $childrenOnly = str_replace( ['<Auth>', '</Auth>'], '', $soap );

            return $childrenOnly;
        } catch ( Exception $e ) {
            return $e->getMessage();
        }
    }
}
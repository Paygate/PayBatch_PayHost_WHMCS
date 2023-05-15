<?php
/*
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Helper class to make SOAP call to PayHost endpoint
 */

class payhostsoap
{
    /**
     * @var string the url of the PayGate PayHost process page
     */
    public static $process_url = PAYHOSTAPI;

    /**
     * @var string the url of the PayGate PayHost WSDL
     */
    public static $wsdl = PAYHOSTAPIWSDL;
    public static $DEFAULT_PGID = '10011072130';

    // Standard Inputs
    public static $DEFAULT_AMOUNT = 3299;
    public static $DEFAULT_CURRENCY = 'ZAR';
    public static $DEFAULT_LOCALE = 'en-us';
    public static $DEFAULT_ENCRYPTION_KEY = 'test';
    public static $DEFAULT_TITLE = 'Mr';
    public static $DEFAULT_FIRST_NAME = 'PayGate';
    public static $DEFAULT_LAST_NAME = 'Test';
    public static $DEFAULT_EMAIL = 'itsupport@paygate.co.za';
    public static $DEFAULT_COUNTRY = 'ZAF';

    // Customer Details
    public static $DEFAULT_NOTIFY_URL = 'http://www.gatewaymanagementservices.com/ws/gotNotify.php';
    public static $DEFAULT_PAY_METHOD = 'CC';
    /**
     * @var string default namespace. We add the namespace manually because of PHP's "quirks"
     */
    private static $ns = 'ns1';
    protected $pgid;
    protected $reference;
    protected $amount;
    protected $currency;
    protected $transDate;
    protected $locale;
    protected $payMethod;

    // Address Details
    protected $payMethodDetail;
    protected $encryptionKey;
    protected $customerTitle;
    protected $firstName;
    protected $middleName;
    protected $lastName;
    protected $telephone;

    // Address checkboxes
    protected $mobile;
    protected $fax;
    protected $email;

    // Shipping Details
    protected $dateOfBirth;
    protected $socialSecurity;
    protected $addressLine1;

    // Redirect Details
    protected $addressLine2;
    protected $addressLine3;
    protected $zip;

    // Risk
    protected $city;
    protected $state;

    // Airline
    protected $country;
    protected $incCustomer = true;
    protected $incBilling = true;
    protected $incShipping;
    protected $deliveryDate;
    protected $deliveryMethod;
    protected $installRequired;
    protected $retUrl;
    protected $notifyURL;
    protected $target;
    protected $riskAccNum;
    protected $riskIpAddr;
    protected $ticketNumber;
    protected $PNR;
    protected $travellerType;
    protected $departureAirport;

    // Recurring orders
    protected $departureCountry;

    // Vaulting allowed
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
    protected $recurring;
    protected $vaulting;
    protected $vaultId;

    public function __construct()
    {
    }

    public function setData($data)
    {
        foreach ($data as $key => $value) {
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
{$this->getVault($this->vaulting, $this->vaultId)}
{$this->getPaymentType()}
{$this->getRedirect()}
{$this->getOrder()}
{$this->getRisk()}
{$this->getUserFields()}
</{$this::$ns}:WebPaymentRequest>
</{$this::$ns}:SinglePaymentRequest>
XML;

        $xml = preg_replace(
            "/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/",
            "\n",
            $xml
        ); // Remove empty lines to make the plain text request prettier

        return $xml;
    }

    public function getSOAPData()
    {
        $data                                 = [];
        $data['WebPaymentRequest']            = [];
        $data['WebPaymentRequest']['Account'] = $this->getAccountData();

        return $data;
    }

    private function getVault($vaulting, $vaultId)
    {
        if ($vaulting !== true) {
            return '';
        }

        $token = $vaultId;
        if ($token == null || $token == '') {
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
        $middleName     = ($this->middleName != '' ? "<{$this::$ns}:MiddleName>{$this->middleName}</{$this::$ns}:MiddleName>" : '');
        $telephone      = ($this->telephone != '' ? "<{$this::$ns}:Telephone>{$this->telephone}</{$this::$ns}:Telephone>" : '');
        $mobile         = ($this->mobile != '' ? "<{$this::$ns}:Mobile>{$this->mobile}</{$this::$ns}:Mobile>" : '');
        $fax            = ($this->fax != '' ? "<{$this::$ns}:Fax>{$this->fax}</{$this::$ns}:Fax>" : '');
        $dateOfBirth    = ($this->dateOfBirth != '' ? "<{$this::$ns}:DateOfBirth>{$this->dateOfBirth}</{$this::$ns}:DateOfBirth>" : '');
        $socialSecurity = ($this->socialSecurity != '' ? "<{$this::$ns}:SocialSecurityNumber>{$this->socialSecurity}</{$this::$ns}:SocialSecurityNumber>" : '');
        $address        = (isset($this->incCustomer) ? $this->getAddress() : '');

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
        $address1 = ($this->addressLine1 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine1}</{$this::$ns}:AddressLine>" : '');
        $address2 = ($this->addressLine2 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine2}</{$this::$ns}:AddressLine>" : '');
        $address3 = ($this->addressLine3 != '' ? "<{$this::$ns}:AddressLine>{$this->addressLine3}</{$this::$ns}:AddressLine>" : '');
        $city     = ($this->city != '' ? "<{$this::$ns}:City>{$this->city}</{$this::$ns}:City>" : '');
        $country  = ($this->country != '' ? "<{$this::$ns}:Country>{$this->country}</{$this::$ns}:Country>" : 'ZAF');
        $state    = ($this->state != '' ? "<{$this::$ns}:State>{$this->state}</{$this::$ns}:State>" : '');
        $zip      = ($this->zip != '' ? "<{$this::$ns}:Zip>{$this->zip}</{$this::$ns}:Zip>" : '');

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

        if ($this->payMethod != '' || $this->payMethodDetail != '') {
            $payMethod       = ($this->payMethod != '' ? "<{$this::$ns}:Method>{$this->payMethod}</{$this::$ns}:Method>" : '');
            $payMethodDetail = ($this->payMethodDetail != '' ? "<{$this::$ns}:Detail>{$this->payMethodDetail}</{$this::$ns}:Detail>" : '');

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
        $target = (isset($this->target) && $this->target != '' ? '<' . $this::$ns . ':Target>' . $this->target . '</' . $this::$ns . ':Target>' : '');

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

        if (isset($this->incBilling)) {
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

        if (isset($this->incShipping) || $this->deliveryDate != '' || $this->deliveryMethod != '' || isset($this->installRequired)) {
            $address         = (isset($this->incShipping) ? $this->getAddress() : '');
            $deliveryDate    = ($this->deliveryDate != '' ? "<{$this::$ns}:DeliveryDate>{$this->deliveryDate}</{$this::$ns}:DeliveryDate>" : '');
            $deliveryMethod  = ($this->deliveryMethod != '' ? "<{$this::$ns}:DeliveryMethod>{$this->deliveryMethod}</{$this::$ns}:DeliveryMethod>" : '');
            $installRequired = ($this->installRequired != '' ? "<{$this::$ns}:InstallationRequested>{$this->installRequired}</{$this::$ns}:InstallationRequested>" : '');

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

        if ($this->riskAccNum != '' && $this->riskIpAddr != '') {
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

        while ($i >= 1) {
            if (isset($this->{'userKey' . $i}) && $this->{'userKey' . $i} != '' && isset($this->{'userField' . $i}) && $this->{'userField' . $i} != '') {
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
        $middleName     = ($this->middleName != '' ? "<{$this::$ns}:MiddleName>{$this->middleName}</{$this::$ns}:MiddleName>" : '');
        $telephone      = ($this->telephone != '' ? "<{$this::$ns}:Telephone>{$this->telephone}</{$this::$ns}:Telephone>" : '');
        $mobile         = ($this->mobile != '' ? "<{$this::$ns}:Mobile>{$this->mobile}</{$this::$ns}:Mobile>" : '');
        $fax            = ($this->fax != '' ? "<{$this::$ns}:Fax>{$this->fax}</{$this::$ns}:Fax>" : '');
        $dateOfBirth    = ($this->dateOfBirth != '' ? "<{$this::$ns}:DateOfBirth>{$this->dateOfBirth}</{$this::$ns}:DateOfBirth>" : '');
        $socialSecurity = ($this->socialSecurity != '' ? "<{$this::$ns}:SocialSecurityNumber>{$this->socialSecurity}</{$this::$ns}:SocialSecurityNumber>" : '');

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

        if ($this->PNR != '') {
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

    private function getAccountData()
    {
        $PayGateId = $this->pgid;
        $Password  = $this->encryptionKey;

        return ['PayGateId' => $PayGateId, 'Password' => $Password];
    }
}

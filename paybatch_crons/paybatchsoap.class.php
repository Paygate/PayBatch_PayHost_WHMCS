<?php
/*
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Helper class for making SOAP requests to PayBatch API
 *
 */

class paybatchsoap
{
    /**
     * @var string the url of the PayGate PayBatch process page
     */
    public static $process_url = PAYBATCHAPI;

    /**
     * @var string the url of the PayGate PayBatch WSDL
     */
    public static $wsdl = PAYBATCHAPIWSDL;

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

    public function __construct()
    {
        $this->batchReference = date('Y-m-d') . '_' . uniqid();
        $this::$notifyUrl     = 'https://www.xtestyz854.com';
    }

    /**
     * @param $data input data array
     */
    public function getAuthRequest($data)
    {
        $this->batchReference = date('Y-m-d') . '_' . uniqid();
        $this->setBatchData($data);
        try {
            // Use SimpleXMLElement to build structure
            $xml = new SimpleXMLElement('<Auth />');
            $xml->addChild('BatchReference', $this->batchReference);
            $xml->addChild('NotificationUrl', $this::$notifyUrl);

            $batchData = $xml->addChild('BatchData');
            foreach ($this->batchData as $line) {
                $batchLine = '';
                foreach ($line as $item) {
                    $batchLine .= $item . ',';
                }
                $batchLine = rtrim($batchLine, ',');
                $batchData->addChild('BatchLine', $batchLine);
            }

            // Remove XML headers
            $dom = new DOMDocument();
            $dom->loadXML($xml->asXML());

            $soap = $dom->saveXML($dom->documentElement);

            // Remove Auth tag - added in __soapCall
            $childrenOnly = str_replace(['<Auth>', '</Auth>'], '', $soap);

            return $childrenOnly;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $data array of batchline type
     */
    public function setBatchData($data)
    {
        $this->batchData = [];
        foreach ($data as $line) {
            $this->batchData[] = $line;
        }
    }

    public function getConfirmRequest($uploadId)
    {
        try {
            // Use SimpleXmlElement for better control of children
            $xml = new SimpleXMLElement('<Query />');

            $xml->addChild('UploadID', $uploadId);

            // Use DomDocument to remove XML headers
            $dom = new DOMDocument();
            $dom->loadXML($xml->asXML());

            $soap = $dom->saveXML($dom->documentElement);

            // Remove Confirm tag because we pass it in the __soapCall
            $childrenOnly = str_replace(['<Query>', '</Query>'], '', $soap);

            return $childrenOnly;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getQueryRequest($uploadId)
    {
        try {
            // Use SimpleXmlElement for better control of children
            $xml = new SimpleXMLElement('<Query />');

            $xml->addChild('UploadID', $uploadId);

            // Use DomDocument to remove XML headers
            $dom = new DOMDocument();
            $dom->loadXML($xml->asXML());

            $soap = $dom->saveXML($dom->documentElement);

            // Remove Confirm tag because we pass it in the __soapCall
            $childrenOnly = str_replace(['<Query>', '</Query>'], '', $soap);

            return $childrenOnly;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}

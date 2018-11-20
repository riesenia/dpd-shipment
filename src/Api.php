<?php

declare(strict_types=1);

namespace Riesenia\DPDShipment;

/**
 * Class Api.
 */
class Api
{
    /** @var \SoapClient */
    protected $soap;

    /** @var array */
    protected $authHeader;

    /** @var string */
    protected $wsdl = 'https://api.dpdportal.sk/shipment/soap?wsdl';

    /**
     * Api constructor.
     *
     * @param $clientKey
     * @param $email
     * @param $password
     * @param bool $testMode
     */
    public function __construct(string $clientKey, string $email, string $password, bool $testMode = false)
    {
        $this->soap = new \SoapClient($this->wsdl, [
            'trace' => $testMode
        ]);

        $this->soap->__setSoapHeaders(new \SoapHeader('http://www.dpdportal.sk/XMLSchema/DPDSecurity/v2', 'DPDSecurity', [
            'SecurityToken' => [
                'ClientKey' => $clientKey,
                'Email' => $email,
                'Password' => $password
            ]
        ]));

        if ($testMode) {
            $this->soap->__setLocation('https://capi.dpdportal.sk/apix/shipment');
        }
    }

    /**
     * Create shipments and return label url on success.
     *
     * @param array $shipments
     *
     * @return array
     */
    public function send(array $shipments): array
    {
        $response = $this->soap->Create([
            'shipment' => $shipments
        ]);

        if ($response->result->success == false) {
            return ['errors' => $response->result->messages];
        }

        return ['label' => $response->result->label];
    }

    /**
     * Get label from specified url.
     *
     * @param string $url
     *
     * @return array
     */
    public function generateLabel(string $url): array
    {
        $ch = \curl_init($url);

        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = \curl_exec($ch);

        if (\curl_errno($ch)) {
            return ['error' => \curl_error($ch)];
        }

        \curl_close($ch);

        $finfo = \finfo_open(FILEINFO_MIME_TYPE);

        if (\finfo_buffer($finfo, $response) !== 'application/pdf') {
            return ['error' => 'Unsupported label mime type.'];
        }

        return ['label' => $response];
    }
}

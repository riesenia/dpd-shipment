<?php

declare(strict_types=1);

namespace Riesenia\DPDShipment;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Class Api.
 */
class Api
{
    /** @var string */
    protected $clientKey;

    /** @var string */
    protected $email;

    /** @var string */
    protected $endpoint = 'https://api.dpdportal.sk/shipment/json';

    /** @var Fpdi */
    protected $fpdi;

    /** @var string */
    protected $password;

    /** @var array */
    protected $options = [];

    /** @var array */
    protected $errors = [];

    /**
     * Api constructor.
     *
     * @param string $clientKey
     * @param string $email
     * @param string $password
     * @param array  $options
     */
    public function __construct(string $clientKey, string $email, string $password, array $options = [])
    {
        $this->clientKey = $clientKey;
        $this->email = $email;
        $this->fpdi = new Fpdi();
        $this->password = $password;
        $this->options = $options + ['testMode' => false, 'timeout' => 10];

        if ($this->options['testMode']) {
            $this->endpoint = 'https://capi.dpdportal.sk/apix/shipment/json';
        }
    }

    /**
     * Create shipment and return label url on success.
     *
     * @param array $shipment
     *
     * @return string
     */
    public function send(array $shipment): string
    {
        $response = $this->sendRequest('create', [
            'shipment' => $shipment
        ]);

        return (string) $response['result']['result']['label'];
    }

    /**
     * Get labels from specified urls.
     *
     * @param array $urls
     *
     * @return string
     */
    public function generateLabels(array $urls): string
    {
        foreach ($urls as $url) {
            $ch = \curl_init($url);

            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);

            $response = \curl_exec($ch);

            if (\curl_errno($ch)) {
                continue;
            }

            \curl_close($ch);

            $finfo = \finfo_open(FILEINFO_MIME_TYPE);

            if (\finfo_buffer($finfo, $response) !== 'application/pdf') {
                continue;
            }

            $this->addPagesToPdf($response);
        }

        return $this->fpdi->Output('S');
    }

    /**
     * Append pages to single pdf.
     *
     * @param string $pdfData
     */
    protected function addPagesToPdf(string $pdfData)
    {
        $pageCount = $this->fpdi->setSourceFile(StreamReader::createByString(\base64_decode($pdfData)));

        for ($i = 1; $i <= $pageCount; ++$i) {
            $template = $this->fpdi->importPage($i);
            $this->fpdi->AddPage();
            $this->fpdi->useTemplate($template);
        }
    }

    /**
     * Send cURL request.
     *
     * @param string $method
     * @param array  $data
     *
     * @return array
     */
    protected function sendRequest(string $method, array $data): array
    {
        $data['DPDSecurity'] = [
            'SecurityToken' => [
                'ClientKey' => $this->clientKey,
                'Email' => $this->email,
                'Password' => $this->password
            ]
        ];

        $postData = [
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $data
        ];

        $ch = \curl_init($this->endpoint);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($postData));
        \curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);

        $response = \json_decode(\curl_exec($ch), true);

        if ($code = \curl_errno($ch)) {
            throw new ShipmentApiException('Request failed: ' . \curl_error($ch), $code);
        }

        \curl_close($ch);

        if (isset($response['result']['result'][0]) && (bool) $response['result']['result'][0]['success'] == false) {
            throw new ShipmentApiException(\array_column($response['result']['result'][0]['messages'], 'value'));
        }

        return $response;
    }
}

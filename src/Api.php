<?php

declare(strict_types=1);

namespace Riesenia\DPDShipment;

/**
 * Class Api.
 */
class Api
{
    /** @var string */
    protected $endpoint = 'https://api.dpdportal.sk/shipment/json';

    /** @var string */
    protected $clientKey;

    /** @var string */
    protected $email;

    /** @var string */
    protected $password;

    /** @var array */
    protected $options = [];

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
        $this->password = $password;
        $this->options = $options + ['testMode' => false, 'timeout' => 10];

        if ($this->options['testMode']) {
            $this->endpoint = 'https://capi.dpdportal.sk/apix/shipment/json';
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
        try {
            $response = $this->sendRequest('create', [
                'shipment' => $shipments
            ]);
        } catch (\Exception $e) {
            return ['errors' => [$e->getMessage()]];
        }

        if (isset($response['errors'])) {
            return ['errors' => $response['errors']];
        }

        return ['label' => (string) $response['result']['result']['label']];
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

    /**
     * Send cURL request.
     *
     * @param string $method
     * @param array  $data
     *
     * @return array|mixed
     */
    protected function sendRequest(string $method, array $data)
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

        $response = \json_decode(\curl_exec($ch));

        if ($code = \curl_errno($ch)) {
            throw new \Exception('Request failed: ' . \curl_error($ch), $code);
        }

        \curl_close($ch);

        if (isset($response->result->result) && (bool) $response->result->result[0]->success == false) {
            return ['errors' => (array) $response->result->result[0]->messages];
        }

        return $response;
    }
}

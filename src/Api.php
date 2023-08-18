<?php

declare(strict_types=1);

namespace Riesenia\DPDShipment;

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
    protected $endpoint = 'https://api.dpd.sk';

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
        $this->password = $password;
        $this->options = $options + ['testMode' => false, 'timeout' => 10];

        if ($this->options['testMode']) {
            $this->endpoint = 'https://capi.dpd.sk';
        }
    }

    /**
     * Create shipment and return response on success.
     *
     * @param array $shipment
     *
     * @return array
     */
    public function send(array $shipment): array
    {
        $response = $this->sendRequest('create', '/shipment/json', [
            'shipment' => $shipment
        ]);

        if (isset($response['error']) && \is_array($response['error'])) {
            throw new ShipmentApiException($response['error']['message']);
        }

        if (isset($response['result']['result'][0]['success']) && !$response['result']['result'][0]['success']) {
            $messages = [];

            foreach ($response['result']['result'][0]['messages'] as $message) {
                $messages[] = $message['value'] ?? $message;
            }

            throw new ShipmentApiException(\implode(' ', $messages));
        }

        return $response['result']['result'][0];
    }

    /**
     * Get available parcelshops.
     *
     * @return array
     */
    public function getParcelshops(): array
    {
        $response = $this->sendRequest('getAll', '/parcelshop/json', []);

        if (!isset($response['result']['result']) && $response['result']['result'] != 'OK') {
            throw new ShipmentApiException('Unable to retrieve parcelshops.');
        }

        return $response['result']['parcelshops'] ?? [];
    }

    /**
     * Send cURL request.
     *
     * @param string $method
     * @param array  $data
     *
     * @return array
     */
    protected function sendRequest(string $method, string $path, array $data): array
    {
        $data['DPDSecurity'] = [
            'SecurityToken' => [
                'ClientKey' => $this->clientKey,
                'Email' => $this->email
            ]
        ];

        $postData = [
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $data
        ];

        $ch = \curl_init($this->endpoint . $path);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($postData));
        \curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);

        $response = \curl_exec($ch);

        if (\curl_errno($ch)) {
            throw new ShipmentApiException('Request failed: ' . \curl_error($ch));
        }

        \curl_close($ch);
        $response = \json_decode($response, true);

        return $response;
    }
}

<?php

namespace KForge\Soisy;

/**
 * @package  Soisy
 */
class Client
{

    const SANDBOX_SHOP_ID = 'partnershop';
    const SANDBOX_API_KEY = 'partnerkey';

    const QUOTE_INSTALMENTS_AMOUNT = 6;
    const MIN_INSTALMENTS = 3;
    const MAX_INSTALMENTS = 60;

    const MIN_AMOUNT = 250;
    const MAX_AMOUNT = 30000;

    const PATH_ORDER_CREATION = 'orders';
    const PATH_LOAN_QUOTE = 'loan-quotes';

    private $apiBaseUrl = [
        'sandbox' => 'https://api.sandbox.soisy.it/api/shops',
        'prod'    => 'https://api.soisy.it/api/shops'
    ];

    private $webappBaseUrl = [
        'sandbox' => 'https://shop.sandbox.soisy.it',
        'prod'    => 'https://shop.soisy.it'
    ];

    /** @var bool */
    private $isSandboxMode;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $shopId;

    private $timeout = 4000;

    public function __construct(?string $shopId, ?string $apiKey, $sandboxMode = true)
    {
        if ($this->isSandboxModeWanted($sandboxMode)) {
            $this->isSandboxMode = true;
            $this->shopId        = self::SANDBOX_SHOP_ID;
            $this->apiKey        = self::SANDBOX_API_KEY;

            return;
        }

        $this->isSandboxMode = false;
        $this->shopId        = $shopId;
        $this->apiKey        = $apiKey;
    }

    public function getLoanSimulation(array $params): \stdClass
    {
        $rawResponse = $this->doRequest($this->getLoanQuoteUrl(), 'GET', $params);

        return $rawResponse;
    }

    public function requestToken(array $params): ?string
    {
        $response = $this->doRequest($this->getOrderCreationUrl(), 'POST', $params);

        if (isset($response->token)) {
            return $response->token;
        }

        return null;
    }

    public function getOrder(string $token): \stdClass
    {
        $rawResponse = $this->doRequest($this->getOrderUrl($token), 'GET');

        return $rawResponse;
    }

    public function getRedirectUrl(string $token): string
    {
        $baseUrl = $this->isSandboxMode ? $this->webappBaseUrl['sandbox'] : $this->webappBaseUrl['prod'];

        return $baseUrl . '/' . $this->shopId . '#/loan-request?token=' . $token;
    }

    public function getApiUrl(): string
    {
        $url = $this->isSandboxMode ? $this->apiBaseUrl['sandbox'] : $this->apiBaseUrl['prod'];

        return $url . '/' . $this->shopId;
    }

    private function getOrderCreationUrl(): string
    {
        return $this->getApiUrl() . '/' . self::PATH_ORDER_CREATION;
    }

    private function getLoanQuoteUrl(): string
    {
        return $this->getApiUrl() . '/' . self::PATH_LOAN_QUOTE;
    }

    private function getOrderUrl(string $token): string
    {
        return $this->getOrderCreationUrl() . '/' . $token;
    }

    private function doRequest(string $url, string $httpMethod = 'GET', array $params = [], int $timeout = null): \stdClass
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Auth-Token: ' . $this->apiKey,
        ]);

        if ($httpMethod == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } elseif ($httpMethod == 'GET' && isset($params)) {
            $url = $url . '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, !is_null($timeout) ? $timeout : $this->timeout);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $output          = json_decode(curl_exec($ch));
        $httpStatusCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError       = curl_error($ch);
        $curlErrorNumber = curl_errno($ch);

        curl_close($ch);

        if ($curlErrorNumber !== 0 || $curlError !== '') {
            throw new \Error($curlError);
        }

        if ($httpStatusCode == 403) {
            throw new \DomainException('Autenticazione fallita, verificare Shop ID e API Key');
        }

        if ($httpStatusCode !== 200) {
            throw new \DomainException(
                'Error:'.$httpStatusCode.' '.$curlError.' '.$this->convertErrorsToString((array)$output->errors ?? [])
            );
        }

        return $output;
    }

    private function isSandboxModeWanted($sandbox): bool
    {
        return $sandbox === "1" || $sandbox === 1 || $sandbox === true || is_null($sandbox);
    }

    private function convertErrorsToString(array $errors): string
    {
        $errorMessage = '';

        foreach ($errors as $error) {
            $errorMessage .= sprintf("%s\n", $error[0]);
        }

        return $errorMessage;
    }
}

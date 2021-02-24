<?php

use KForge\Soisy\Client as BaseClient;

class Client
{
    private $client;
    private $module;

    public $statusList = [
        'LoanWasApproved'  => ['request_approved'],
        'LoanWasVerified'  => ['waiting_for_disbursement'],
        'LoanWasDisbursed' => ['disbursed'],
        'UserWasRejected'  => ['cancelled'],
    ];

    public function __construct(string $shopId, string $apiKey, $sandboxMode = true, $module)
    {
        $this->client = new BaseClient($shopId, $apiKey, $sandboxMode);

        $this->module = $module;
    }

    public function __call($method, $arguments)
    {
        //log call
        $message = $method.' - '.print_r($arguments, true);
        $logResult = $this->module->logCall($message);

        try {
            $result = call_user_func_array([$this->client, $method], $arguments);
        } catch (\Error | \DomainException | \Exception $e) {
            //log exception
            $error = $e->getMessage();
            $message = $method.' - '.$error;

            $this->module->logCall($message, 3);

            // $exceptionType = get_class($e);
            throw new \Exception($error);
        }

        //log result
        $message = $method.' - '.print_r($result, true);
        $this->module->logCall($message);

        return $result;
    }
}

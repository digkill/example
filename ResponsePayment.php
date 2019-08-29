<?php

namespace common\payments\bambora;

class ResponsePayment
{

    private $authCode;
    private $dateTime;
    private $type;
    private $amount;
    private $id;

    private $net;
    private $fees;
    private $total;

    public static function create($response)
    {
        return new static($response);
    }

    public function __construct($response)
    {
        $this->authCode = $response['auth_code'] ?? 0;
        $this->dateTime = $response['created'] ?? null;
        $this->type = $response['type'] ?? null;
        $this->amount = $response['amount'] ?? null;
        $this->id = $response['id'] ?? null;

        $this->net = $response['net'] ?? null;
        $this->fees = $response['fees'] ?? null;
        $this->total = $response['total'] ?? null;
    }


    public function getResponseCode()
    {
        return $this->authCode;
    }


    public function getISO()
    {
        return null;
    }


    public function getDateTime()
    {
        return $this->dateTime;
    }


    public function getTransType()
    {
        return $this->type;
    }


    public function getTransAmount()
    {
        return $this->amount;
    }

    public function getTxnNumber()
    {
        return $this->id;
    }

    public function getNet()
    {
        return $this->net;
    }

    public function getFees()
    {
        return $this->fees;
    }

    public function getTotal()
    {
        return $this->total;
    }



}
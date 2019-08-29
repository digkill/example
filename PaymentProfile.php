<?php

namespace common\payments\bambora;


use common\models\BankCard;

class PaymentProfile
{
    private $beanstream;
    private $config;

    /**
     * PaymentProfile constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->beanstream = new \Beanstream\Gateway($config->merchantId, $config->apiKeyProfile, $config->platform, $config->apiVersion);
        $this->config = $config;
    }


    public function create($profileData)
    {
        return $this->beanstream->profiles()->createProfile($profileData);
    }

    public function get($profileId)
    {
        return $this->beanstream->profiles()->getProfile($profileId);
    }

    public function update($profileId, $profileData)
    {
        return $this->beanstream->profiles()->updateProfile($profileId, $profileData);
    }

    public function delete($profileId)
    {
        return $this->beanstream->profiles()->deleteProfile($profileId);
    }

    public function addCard($profileId, $tokenData)
    {
        return $this->beanstream->profiles()->addCard($profileId, $tokenData);
    }

    public function payment($profileId, $cardId, $amount, $complete = true)
    {
        $beanstream = new \Beanstream\Gateway($this->config->merchantId, $this->config->apiKeyGateway, $this->config->platform, $this->config->apiVersion);
        $result = $beanstream->payments()->makeProfilePayment($profileId, $cardId, ['amount' => $amount], $complete);
        return $result['id'];
    }

    public function getAllCards($profileId)
    {
        return $this->beanstream->profiles()->getCards($profileId);
    }

    public function token(BankCard $cardData)
    {
        $cardData = $this->prepareCardData($cardData);
        return $this->beanstream->payments()->getTokenTest($cardData);
    }

    private function prepareCardData(BankCard $cardData)
    {
        list($month, $year) = explode('/', $cardData->expireDate);
        return [
            'name' => $cardData->name,
            'number' => $cardData->number,
            'expiry_month' => $month,
            'expiry_year' => $year,
            'cvd' => $cardData->cvd,
        ];
    }

}

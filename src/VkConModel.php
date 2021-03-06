<?php
/**
 * Created by PhpStorm.
 * User: Назым
 * Date: 15.04.2019
 * Time: 20:05
 */

namespace nazbav\VkCoinAPI;


/**
 * Class VkConModel
 * @package nazbav\VkCoinAPI
 */
class VkConModel extends VkCoinController
{
    /**
     * VkCoinController constructor.
     * @param $merchantId
     * @param $key
     * @param bool $checkResponse
     * @throws VkCoinException
     */
    public function __construct($merchantId, $key, $checkResponse = true)
    {
        $this->setDir(dirname(dirname(__FILE__)));
        if (!file_exists($this->getDir() . '/config/Language.php'))
            throw new VkCoinException('Language.php is missing.');
        else
            $this->setMessages((new VkCoinMessages())->messages());
        $this->setCheckResponse($checkResponse);
        $this->setMerchantId($merchantId);
        $this->setKey($key);
        $this->params = [
            'merchantId' => $merchantId,
            'key' => $key
        ];
    }

    /**
     * @return CoinFunc
     */
    public function getFunc()
    {
        return new CoinFunc($this->getMerchantId());
    }

    /**
     * @param $method
     * @param array $parameters
     * @return mixed
     * @throws VkCoinException
     */
    protected function api($method, $parameters = [])
    {
        $this->setParams($parameters);
        $this->setResponse($this->callAPI($method));
        $this->checkResponse();
        return $this->getResponse();
    }

    /**
     * @param $method
     * @return mixed
     */
    protected function callAPI($method)
    {
        $host = sprintf('https://%s/%s/', $this->getHost(), $method);
        $response = $this->postCurl($host, $this->getParams());

        if ($response) {
            $return = ['status' => true];
            if (isset($response['error'])) {
                $return['status'] = false;
                $return['error'] = $response['error'];
            } else {
                $return['response'] = $response['response'];
                //Ловим ошибки и получаем ответ
            }
            return $return;
        }
        return [];
    }

    /**
     * @param $host
     * @param $parameters
     * @return mixed
     */
    protected function postCurl($host, $parameters)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $host,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $return = curl_exec($ch);
        if (curl_error($ch))
            return ['status' => false, 'response' =>
                ['code' => 100,
                    'message' => curl_error($ch)
                ]
            ];
        curl_close($ch);
        return json_decode($return, true);
    }

    /**
     * @return void
     * @throws VkCoinException
     */
    protected function checkResponse()
    {
        if ($this->getCheckResponse()) {
            $response = $this->getResponse();
            if (is_array($response)) {
                if ($response['status'] == false && isset($response['error']))
                    switch ($response['error']['code']) {
                        case 422:
                            $error = $this->getMessages()['COIN_FERROR_METHOD_PARAMETERS'];
                            switch ($response['error']['message']) {
                                case 'BAD_ARGS':
                                    $error = $this->getMessages()['COIN_FERROR_METHOD_BAD_ARGS'];
                                    break;
                                case 'merchantId or key is not valid':
                                    $error = $this->getMessages()['COIN_FERROR_INVALID_IDRKEY'];
                                    break;
                                case 'tx is empty':
                                    $error = $this->getMessages()['COIN_FERROR_METHOD_INVALID_TX'];
                                    break;

                            }
                            throw new VkCoinException($error);
                            break;
                        case 100:
                            throw new VkCoinException($response['error']['message']);
                            break;
                        default:
                            throw new VkCoinException($this->getMessages()['COIN_FERROR_METHOD_PARAMETERS']);
                            break;
                    }
                elseif ($response['status'] == true && isset($response['response'])) {
                    if (isset($response['response'][0]['from_id']) && $response['response'][0]['from_id'] == 'hs')
                        throw new VkCoinException($this->getMessages()['COIN_TRANSFER_PARAM_ERROR']);;
                } elseif (isset($response['response']) && empty($response['response'])) {
                    throw new VkCoinException($this->getMessages()['COIN_FERROR_METHOD_PARAMETERS']);
                }
            } else  throw new VkCoinException($this->getMessages()['COIN_FATAL']);
        }
    }
}
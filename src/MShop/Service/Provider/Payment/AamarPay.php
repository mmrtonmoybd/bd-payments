<?php

namespace Aimeos\MShop\Service\Provider\Payment;

class AamarPay extends \Aimeos\MShop\Service\Provider\Payment\Base implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
    private array $beConfig = [
        'aamarpay.StoreID' => [
            'code' => 'aamarpay.StoreID',
            'internalcode' => 'aamarpay.StoreID',
            'label' => 'Aamarpay Store ID',
            'type' => 'string',
            'default' => '',
            'required' => true,
        ],

        'aamarpay.SignatureKey' => [
            'code' => 'aamarpay.SignatureKey',
            'internalcode' => 'aamarpay.SignatureKey',
            'label' => 'Aamarpay Signature Key',
            'type' => 'string',
            'default' => '',
            'required' => true,
        ],

        'aamarpay.testmode' => [
            'code' => 'aamarpay.testmode',
            'internalcode' => 'aamarpay.testmode',
            'label' => 'Test mode without payments',
            'type' => 'bool',
            'internaltype' => 'boolean',
            'default' => '0',
            'required' => true,
        ],
    ];

    private array $feConfig = [
        'aamarpay.mobile' => [
            'code' => 'aamarpay.mobile',
            'internalcode' => 'mobile',
            'label' => 'Customer phone',
            'type' => 'string',
            'internaltype' => 'string',
            'required' => true,
        ],
    ];

    /**
     * Returns the configuration attribute definitions of the provider to generate a list of available fields and
     * rules for the value of each field in the administration interface.
     *
     * @return array List of attribute definitions implementing \Aimeos\Base\Critera\Attribute\Iface
     */
    public function getConfigBE(): array
    {
        return $this->getConfigItems($this->beConfig);
    }

    /**
     * Checks the backend configuration attributes for validity.
     *
     * @param  array  $attributes  Attributes added by the shop owner in the administraton interface
     * @return array An array with the attribute keys as key and an error message as values for all attributes that are
     *               known by the provider but aren't valid
     */
    public function checkConfigBE(array $attributes): array
    {
        $errors = parent::checkConfigBE($attributes);

        return array_merge($errors, $this->checkConfig($this->beConfig, $attributes));
    }

    public function getConfigFE(\Aimeos\MShop\Order\Item\Iface $basket): array
    {
        $list = [];

        foreach ($this->feConfig as $key => $config) {
            $list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard($config);
        }

        return $list;
    }

    public function checkConfigFE(array $attributes): array
    {
        return $this->checkConfig($this->feConfig, $attributes);
    }

    public function setConfigFE(\Aimeos\MShop\Order\Item\Service\Iface $orderServiceItem,
        array $attributes): \Aimeos\MShop\Order\Item\Service\Iface
    {
        return $orderServiceItem->addAttributeItems($this->attributes($attributes, 'payment'));
    }

    public function getFullUrl()
    {
        return ($this->getConfigValue(['aamarpay.testmode']) == 1) ? 'https://sandbox.aamarpay.com/jsonpost.php' : 'https://secure.aamarpay.com/jsonpost.php';
    }

    public function payRequest(array $param)
    {
        $data = json_encode($param);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->getFullUrl(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    public function process(\Aimeos\MShop\Order\Item\Iface $order,
        array $params = []): ?\Aimeos\MShop\Common\Helper\Form\Iface
    {
        //$total = $order->getPrice()->getValue() + $order->getPrice()->getCosts();

        $storeid = $this->getConfigValue(['aamarpay.StoreID']);
        $SignatureKey = $this->getConfigValue(['aamarpay.SignatureKey']);

        if (($addresses = $order->getAddress(\Aimeos\MShop\Order\Item\Address\Base::TYPE_DELIVERY)) === []) {
            $addresses = $order->getAddress(\Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT);
        }

        $addresses = $order->getAddress(type: \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT);

        $addr = current($order->getAddress('payment'));

        if ($service = current($order->getService('payment'))) {
            $attrItems = $service->getAttributeItems();
        }

        $jsondata = json_decode($attrItems->toJson(), true);

        // Default mobile number
        $mobileNumber = '01700000000';

        // Loop through the array to find where the code is 'aamarpay.mobile'
        foreach ($jsondata as $key => $attribute) {
            if ($attribute['order.service.attribute.code'] === 'aamarpay.mobile') {
                // Check if 'order.service.attribute.value' is not empty
                if (! empty($attribute['order.service.attribute.value'])) {
                    $mobileNumber = $attribute['order.service.attribute.value'];
                }
            }
        }

        $data = [
            'store_id' => $storeid,
            'signature_key' => $SignatureKey,
            'tran_id' => md5('txn:'.$order->getId()),
            'currency' => $order->getPrice()->getCurrencyId(),
            'amount' => $this->getAmount($order->getPrice()),
            'desc' => 'Name: '.$addr->getFirstName().' '.$addr->getLastName().' Order id: '.$order->getId(),
            'cus_name' => $addr->getFirstName().' '.$addr->getLastName(),
            'cus_email' => $addr->getEmail(),
            'cus_phone' => $mobileNumber,
            'success_url' => $this->getConfigValue('payment.url-success'),
            'fail_url' => $this->getConfigValue('payment.url-success'),
            'cancel_url' => $this->getConfigValue('payment.url-success'),
            'type' => 'json',
        ];

        $send = $this->payRequest($data);
        $responseObj = json_decode($send);
        //dd($send);
        if (isset($responseObj->payment_url) && ! empty($responseObj->payment_url)) {

            $paymentUrl = $responseObj->payment_url;

            return new \Aimeos\MShop\Common\Helper\Form\Standard($paymentUrl, 'GET', []);
            //return redirect()->away($paymentUrl);

        } else {
            $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
            $order->setStatusPayment($status);
            $this->save($order);

            throw new \Aimeos\MShop\Service\Exception((string) 'Something went worng, payment gateway return error.');
        }

        //return new \Aimeos\MShop\Common\Helper\Form\Standard( );
    }

    public function checkPay(\Aimeos\MShop\Order\Item\Iface $order, $given_txn)
    {
        $txn = md5('txn:'.$order->getId());
        $amount = $this->getAmount($order->getPrice());
        $currency = $order->getPrice()->getCurrencyId();
        $storeid = $this->getConfigValue(['aamarpay.StoreID']);
        $SignatureKey = $this->getConfigValue(['aamarpay.SignatureKey']);

        $url = ($this->getConfigValue(['aamarpay.testmode']) == 1) ? 'https://sandbox.aamarpay.com/' : 'https://secure.aamarpay.com/';
        $fullurl = $url.'api/v1/trxcheck/request.php?request_id='.$txn.'&store_id='.$storeid.'&signature_key='.$SignatureKey.'&type=json';
        //return $fullurl;
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $fullurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    public function updateSync(\Psr\Http\Message\ServerRequestInterface $request,
        \Aimeos\MShop\Order\Item\Iface $order): \Aimeos\MShop\Order\Item\Iface
    {

        $params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

        //dd($params);
        if (! empty($params)) {
            if ($params['status_code'] == 2 && ! empty($params['mer_txnid'])) {
                $send = $this->checkPay($order, $params['mer_txnid']);

                $currency = $order->getPrice()->getCurrencyId();

                $senddata = json_decode($send, false);

                if (! empty($senddata)) {

                    if (isset($senddata->status_code) && $senddata->status_code == 2) {

                        if ($currency != 'BDT' && isset($senddata->currency_merchant)) {

                            if (($currency == $senddata->currency_merchant) && ($this->getAmount($order->getPrice()) == $senddata->amount_currency) && ($params['mer_txnid'] == $senddata->mer_txnid)) {
                                $status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
                                $order->setStatusPayment($status);
                                //$value = 1;
                                if ($order->getStatusPayment() != 6) {
                                    $this->save($order);
                                }
                            } else {
                                $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
                                $order->setStatusPayment($status);
                                //$value = 2;
                                $this->save($order);
                            }
                        } else {
                            if (($this->getAmount($order->getPrice()) == $senddata->amount) && ($params['mer_txnid'] == $senddata->mer_txnid)) {
                                $status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
                                $order->setStatusPayment($status);
                                //$value = 1;
                                if ($order->getStatusPayment() != 6) {
                                    $this->save($order);
                                }
                            } else {
                                $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
                                $order->setStatusPayment($status);
                                //$value = 2;
                                $this->save($order);
                            }
                        }

                    } else {
                        $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
                        $order->setStatusPayment($status);
                        //$value = 2;
                        $this->save($order);
                    }
                }

                //if (!empty($send) && )
            } elseif ($params['status_code'] == 7) {
                $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
                $order->setStatusPayment($status);
                //$value = 2;
                $this->save($order);
            }

        } else {
            $status = \Aimeos\MShop\Order\Item\Base::PAY_DELETED;
            $order->setStatusPayment($status);
            //$value = 2;
            $this->save($order);

            //dd($params);
        }
        //dd($params, $params['mer_txnid'], $send, $value);

        return $order;
    }

    public function updatePush(\Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();
        // extract the order ID and latest status from the request
        $orderid = $params['orderid'];
        $order = \Aimeos\MShop::create($this->context(), 'order')->get($orderid);

        if (! empty($params)) {
            if ($params['status_code'] == 2 && ! empty($params['mer_txnid'])) {
                $send = $this->checkPay($order, $params['mer_txnid']);

                $currency = $order->getPrice()->getCurrencyId();

                $senddata = json_decode($send, false);
                if (! empty($senddata)) {
                    if (isset($senddata->status_code) && $senddata->status_code == 2) {

                        if ($currency != 'BDT' && isset($senddata->currency_merchant)) {

                            if (($currency == $senddata->currency_merchant) && ($this->getAmount($order->getPrice()) == $senddata->amount_currency) && ($params['mer_txnid'] == $senddata->mer_txnid) && ($order->getStatusPayment() != 6)) {
                                $order->setStatusPayment(\Aimeos\MShop\Order\Item\Base::PAY_RECEIVED);
                                $this->saveOrder($order);
                            }

                        } else {
                            // if currency is BDT and verify the payment
                            if (($params['mer_txnid'] == $senddata->mer_txnid) && ($this->getAmount($order->getPrice()) == $senddata->amount) && ($order->getStatusPayment() != 6)) {
                                $status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
                                $order->setStatusPayment($status);
                                //$value = 1;
                                $this->save($order);
                            }
                        }
                    }

                }

            }
        }

        return $response;
    }
}

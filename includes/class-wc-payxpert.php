<?php
/**
 * Create PayXpert Main Class
 */

use PayXpert\Connect2Pay\Connect2PayClient;
use PayXpert\Connect2Pay\containers\request\PaymentPrepareRequest;
use PayXpert\Connect2Pay\containers\Order;
use PayXpert\Connect2Pay\containers\Shipping;
use PayXpert\Connect2Pay\containers\Shopper;
use PayXpert\Connect2Pay\containers\Account;
use PayXpert\Connect2Pay\containers\constant\OrderType;
use PayXpert\Connect2Pay\containers\constant\OrderShippingType;
use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMode;
use PayXpert\Connect2Pay\containers\constant\AccountAge;
use PayXpert\Connect2Pay\containers\constant\AccountLastChange;
use PayXpert\Connect2Pay\containers\constant\AccountPaymentMeanAge;


class PayXpertMain
{

  /** @var boolean Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log = false;

  function __construct()
  {
    $this->debug = $this->getDebug();
    self::$log_enabled = $this->debug;

  }

  public function getOriginatorId()
  {
    return get_option('payxpert_originator_id');
  }

  public function getpassword()
  {
    return get_option('payxpert_password');
  }

  public function getConnectUrl()
  {
    if (empty(get_option('payxpert_connect2_url'))) {
      $connect2_url = (new PayXpertOption)->payxpert_connect2_url;
    } else {
      $connect2_url = get_option('payxpert_connect2_url');
    }
    $connect2_url .= (substr($connect2_url, -1) == '/' ? '' : '/');

    return $connect2_url;
  }

  public function getApiUrl()
  {
    if (empty(get_option('payxpert_api_url'))) {
      $api_url = (new PayXpertOption)->payxpert_api_url;
    } else {
      $api_url = get_option('payxpert_api_url');
    }
    $api_url .= (substr($api_url, -1) == '/' ? '' : '/');

    return $api_url;
  }

  public function getOrderButtonText()
  {
    if (empty(get_option('payxpert_pay_button'))) {
      $ordertextbutton = 'Place Order';
    } else {
      $ordertextbutton = get_option('payxpert_pay_button');
    }

    return $ordertextbutton;
  }

  public function getSeamlessCheckoutVersion()
  {
    return get_option('payxpert_seamless_version');
  }

  public function getSeamlessCheckoutHash()
  {
    return get_option('payxpert_seamless_hash');
  }

  public function getDebug()
  {
    if (get_option('payxpert_debug') == 'no') {
      $debugreturn = false;
    } else {
      $debugreturn = true;
    }

    return $debugreturn;
  }

  public function merchant_notifications()
  {
    return get_option('payxpert_merchant_notifications');
  }

  public function merchant_notifications_to()
  {
    return get_option('payxpert_merchant_notifications_to');
  }

  public function merchant_notifications_lang()
  {
    return get_option('payxpert_merchant_notifications_lang');
  }

  /**
   * Logging method
   *
   * @param string $message
   */
  public static function log($message)
  {
    if (self::$log_enabled) {
      if (empty(self::$log)) {
        self::$log = new WC_Logger();
      }
      self::$log->add('PayXpert', $message);
    }
  }

  /**
   * Check if this gateway is enabled and available in the user's country
   *
   * @return bool
   */
  public function is_valid_for_use()
  {
    // We allow to use the gateway from any where
    return true;
  }

  /**
   * Check if iframe mode is on
   *
   * @return bool
   */
  public function is_iframe_on()
  {
    // We allow to use the gateway from any where
    if (get_option('payxpert_iframe_mode') == 'yes') {
      return true;
    }
    return false;
  }


  /**
   * Can the order be refunded via PayPal?
   *
   * @param WC_Order $order
   * @return bool
   */
  public function can_refund_order($order)
  {
    return $order && $order->get_transaction_id();
  }

  /**
   * Complete order, add transaction ID and note
   *
   * @param WC_Order $order
   * @param string $txn_id
   * @param string $note
   */
  protected function payment_complete($order, $txn_id = '', $note = '')
  {
    $order->add_order_note($note);
    $order->payment_complete($txn_id);
  }

  public function redirect_to($redirect_url)
  {
    // Clean
    @ob_clean();

    // Header
    header('HTTP/1.1 200 OK');

    echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
    exit;
  }


  public function payxpert_process_payment($order_id, $type, $returnurl, $returnname)
  {
    $orderdetails = new WC_Order($order_id);

    // init api
    $c2pClient = new Connect2PayClient($this->getConnectUrl(), $this->getOriginatorId(), $this->getpassword());

    $prepareRequest = new PaymentPrepareRequest();
    $shopper = new Shopper();
    $account = new Account();
    $order = new Order();
    $shipping = new Shipping();

    if ($type == 'ALIPAY') {
      $prepareRequest->setPaymentMethod(PaymentMethod::ALIPAY);
    }
    if ($type == 'WECHAT') {
      $prepareRequest->setPaymentMethod(PaymentMethod::WECHAT);
    }

    $prepareRequest->setPaymentMode(PaymentMode::SINGLE);

    $prepareRequest->setCurrency($orderdetails->get_currency());

    $total = number_format($orderdetails->get_total() * 100, 0, '.', '');
    $prepareRequest->setAmount($total);


    // customer informations
    $shopper->setId($orderdetails->get_customer_id());
    $shopper->setFirstName(substr($orderdetails->get_billing_first_name(), 0, 35))->setLastName(substr($orderdetails->get_billing_last_name(), 0, 35));
    $shopper->setAddress1(substr(trim($orderdetails->get_billing_address_1() . ' ' . $orderdetails->get_billing_address_2()), 0, 255));
    $shopper->setZipcode(substr($orderdetails->get_billing_postcode(), 0, 10))->setCity(substr($orderdetails->get_billing_city(), 0, 50))->setState(substr($orderdetails->get_billing_state(), 0, 30))->setCountryCode(substr(trim($orderdetails->get_billing_country()), 0, 20));
    $shopper->setHomePhonePrefix("212")->setHomePhone(substr(trim($orderdetails->get_billing_country()), 0, 20));
    $shopper->setEmail($orderdetails->get_billing_email());


    // Shipping information
    if ('yes' == get_option('send_shipping')) {
      $shipping->setName(substr($orderdetails->get_shipping_first_name(), 0, 35));
      $shipping->setAddress1(substr(trim($orderdetails->get_shipping_address_1() . " " . $orderdetails->get_shipping_address_2()), 0, 255));
      $shipping->setZipcode(substr($orderdetails->get_shipping_postcode(), 0, 10))->setState(substr($orderdetails->get_shipping_state(), 0, 30))->setCity(substr($orderdetails->get_shipping_city(), 0, 50))->setCountryCode($orderdetails->get_shipping_country());
      $shipping->setPhone(substr(trim(), 0, 20));

    }

    // Order informations
    $order->setId(substr($orderdetails->get_id(), 0, 100));
    $order->setType(OrderType::GOODS_SERVICE);
    $order->setShippingType(OrderShippingType::DIGITAL_GOODS);
    $order->setDescription(substr('Invoice:' . $orderdetails->get_id(), 0, 255));


    $prepareRequest->setCtrlCallbackURL(WC()->api_request_url($returnname));
    $prepareRequest->setCtrlRedirectURL($returnurl . '&order_id=' . $order_id);


    if ($this->is_iframe_on()) {
      $prepareRequest->setThemeID("373");
    }

    // Merchant notifications
    if (!empty($this->merchant_notifications()) && $this->merchant_notifications() != null) {
      if ($this->merchant_notifications() == 'enabled') {
        $prepareRequest->setMerchantNotification(true);
        $prepareRequest->setMerchantNotificationTo($this->merchant_notifications_to());
        $prepareRequest->setMerchantNotificationLang($this->merchant_notifications_lang());
      } else if ($this->merchant_notifications() == 'disabled') {
        $prepareRequest->setMerchantNotification(false);
      }
    }


    $shopper->setAccount($account);
    $prepareRequest->setShopper($shopper);
    $prepareRequest->setOrder($order);
    $prepareRequest->setShipping($shipping);

    // prepare API
    $result = $c2pClient->preparePayment($prepareRequest);
    if ($result !== false) {

      // Save the merchant token for callback verification
      $order = wc_get_order($order_id);
      $order->update_meta_data('_payxpert_merchant_token', $result->getMerchantToken());
      $order->update_meta_data('_payxpert_customer_url', $c2pClient->getCustomerRedirectURL($result));
      $order->save();

      $url = $c2pClient->getCustomerRedirectURL($result);

      if ($this->is_iframe_on())
        $url = $orderdetails->get_checkout_payment_url(true);

      return array('result' => 'success', 'redirect' => $url);

    } else {

      // Log the error
      $message = "Payment preparation error occurred: " . $c2pClient->getClientErrorMessage();
      $this->log($message);

      // Add a notice to the cart
      wc_add_notice($message, 'error');

      // Return an error
      return array('result' => 'fail', 'redirect' => '');

    }

  }


  public function payxpert_callback_handle()
  {
    $c2pClient = new Connect2PayClient($this->getConnectUrl(), $this->getOriginatorId(), $this->getpassword());

    if (isset($_POST["data"]) && $_POST["data"] != null) {

      $data = $_POST["data"];
      $order_id = $_GET['order_id'];
      $order = wc_get_order($order_id);

      $merchantToken = $order->get_meta('_payxpert_merchant_token');

      // Setup the client and decrypt the redirect Status
      if ($c2pClient->handleRedirectStatus($data, $merchantToken)) {
        // Get the PaymentStatus object
        $status = $c2pClient->getStatus();

        $errorCode = $status->getErrorCode();
        $merchantData = $status->getCtrlCustomData();

        // errorCode = 000 => payment is successful
        if ($errorCode == '000') {
          $transaction = $status->getLastTransactionAttempt();
          $transactionId = $transaction->getTransactionID();

          $message = "Successful transaction by customer redirection. Transaction Id: " . $transactionId;
          $this->payment_complete($order, $transactionId, $message, 'payxpert');

          $order->update_status('completed', $message);

          $this->log($message);
          $this->redirect_to($order->get_checkout_order_received_url());
        } else if ($errorCode == '-1') {
          $message = "Unsuccessful transaction, customer left payment flow. Retrieved data: " . print_r($data, true);
          $this->log($message);
          $this->redirect_to(wc_get_checkout_url());
          wc_add_notice(__('Payment not complete, please try again', 'payxpert'), 'notice');
        } else {
          wc_add_notice(__('Payment not complete: ' . $status->getErrorMessage(), 'payxpert'), 'error');
          $this->redirect_to(wc_get_checkout_url());
        }
      }
    } else {

      if ($c2pClient->handleCallbackStatus()) {

        $status = $c2pClient->getStatus();

        // get the Error code
        $errorCode = $status->getErrorCode();
        $errorMessage = $status->getErrorMessage();
        $transaction = $status->getLastTransactionAttempt();
        $transactionId = $transaction->getTransactionID();

        $order_id = $status->getOrderID();

        $order = wc_get_order($order_id);
        $merchantToken = $status->getMerchantToken();

        $amount = number_format($status->getAmount() / 100, 2, '.', '');

        $data = compact("errorCode", "errorMessage", "transactionId", "order_id", "amount");

        $payxpert_merchant_token = $order->get_meta('_payxpert_merchant_token');

        // Be sure we have the same merchant token
        if ($payxpert_merchant_token == $merchantToken) {
          // errorCode = 000 transaction is successfull
          if ($errorCode == '000') {

            $message = "Successful transaction Callback received with transaction Id: " . $transactionId;
            $this->payment_complete($order, $transactionId, $message, 'payxpert');
            $order->update_status('completed', $message);
            $this->log($message);
          } else {

            $message = "Unsuccessful transaction Callback received with the following information: " . print_r($data, true);
            $order->add_order_note($message);
            $this->log($message);
          }
        } else {
          // We do not update the status of the transaction, we just log the
          // message
          $message = "Error. Invalid token " . $merchantToken . " for order " . $order->get_id() . " in callback from " . $_SERVER["REMOTE_ADDR"];
          $this->log($message);
        }

        // Send a response to mark this transaction as notified
        $response = array("status" => "OK", "message" => "Status recorded");
        header("Content-type: application/json");
        echo json_encode($response);
        exit();
      } else {

        $this->log("Error: Callback received an incorrect status from " . $_SERVER["REMOTE_ADDR"]);
        wp_die("PayXpert Callback Failed", "PayXpert", array('response' => 500));
      }
    }
  }

  public function payxpert_refund($order_id, $amount = null, $reason = '')
  {
    $order = wc_get_order($order_id);

    if (!$this->can_refund_order($order)) {
      $this->log('Refund Failed: No transaction ID');
      return false;
    }

    $transactionId = $order->get_transaction_id();

    $c2pClient = new Connect2PayClient($this->getConnectUrl(), $this->getOriginatorId(), $this->getpassword());

    if ($amount <= 0) {
      $amount = $order->get_total();
    }

    $total = number_format($amount * 100, 0, '.', '');

    $status = $c2pClient->refundTransaction($transactionId, $total);

    if ($status != null && $status->getCode() != null) {
      if ($status->getCode() === '000') {
        $this->log("Refund Successful: Transaction ID {$status->getTransactionID()}");
        $order->add_order_note(sprintf(__('Refunded %s - Refund ID: %s', 'payxpert'), $amount, $status->getTransactionID()));
        return true;
      }
    } else {
      $this->log(
        "Refund Failed: Transaction ID {$status->getTransactionID()}, Error {$status->getErrorCode()} with message {$status->getMessage()}"
      );
      return false;
    }
  }

  public function payxpert_receipt_page($order_id)
  {
    //define the url
    $order = wc_get_order($order_id);
    $payxpert_customer_url = $order->get_meta('_payxpert_customer_url');

    //display the form
    include(PX_ABS . 'views/payxpert-iframe.php');

  }

  public function payxpert_seamless_checkout_field($order_button_text, $carttotal, $allpostdata, $relay_response_url)
  {
    echo '<div id="payment-container"><script type="application/json">
        {
            "externalPaymentButton":"place_order",
            "payButtonText": "' . $order_button_text . '",
            "onPaymentResult": "callbackreturn",
            "hideCardHolderName":"true"
        }</script></div>';

    echo '<div id="error-message-seamless">Please Fill up all required Field to Make Payment with Credit Card.</div>';

    $ccy = get_option('woocommerce_currency');
    $order_price_cents = $carttotal * 100;
    if (isset($allpostdata)) {
      parse_str($allpostdata, $postdata);

      $shopperfirstname = $postdata["billing_first_name"] ? $postdata["billing_first_name"] : "";
      $shopperlastname = $postdata["billing_last_name"] ? $postdata["billing_last_name"] : "";
      $shoppercountry = $postdata["billing_country"] ? $postdata["billing_country"] : "";
      $shopperstate = $postdata["billing_state"] ? $postdata["billing_state"] : " ";
      $shoppercity = $postdata["billing_city"] ? $postdata["billing_city"] : "";
      $shopperaddress = $postdata["billing_address_1"] ? $postdata["billing_address_1"] : "";
      $shopperpostcode = $postdata["billing_postcode"] ? $postdata["billing_postcode"] : "";
      $shopperphone = $postdata["billing_phone"] ? $postdata["billing_phone"] : "";
      $shopperemail = $postdata["billing_email"] ? $postdata["billing_email"] : "";
    }

    $c2pClient = new Connect2PayClient($this->getConnectUrl(), $this->getOriginatorId(), $this->getpassword());

    $prepareRequest = new PaymentPrepareRequest();
    $shopper = new Shopper();
    $account = new Account();
    $order = new Order();
    $shipping = new Shipping();

    // Set all information for the payment
    $prepareRequest->setPaymentMethod(PaymentMethod::CREDIT_CARD);
    $prepareRequest->setPaymentMode(PaymentMode::SINGLE);
    $prepareRequest->setCurrency($ccy);
    $prepareRequest->setAmount($order_price_cents);
    $prepareRequest->setCtrlCallbackURL($relay_response_url);

    $getuniqid = uniqid();
    $order->setId($getuniqid);
    $order->setType(OrderType::GOODS_SERVICE);
    $order->setShippingType(OrderShippingType::DIGITAL_GOODS);
    $order->setDescription("Payment");

    // Client details
    $shopper->setFirstName($shopperfirstname)->setLastName($shopperlastname);
    $shopper->setAddress1($shopperaddress);
    $shopper->setZipcode($shopperpostcode)->setCity($shoppercity)->setState($shopperstate)->setCountryCode($shoppercountry);
    $shopper->setHomePhonePrefix("212")->setHomePhone($shopperphone);
    $shopper->setEmail($shopperemail);


    // Merchant notifications
    if (!empty($this->merchant_notifications()) && $this->merchant_notifications() != null) {
      if ($this->merchant_notifications() == 'enabled') {
        $prepareRequest->setMerchantNotification(true);
        $prepareRequest->setMerchantNotificationTo($this->merchant_notifications_to());
        $prepareRequest->setMerchantNotificationLang($this->merchant_notifications_lang());
      } else if ($this->merchant_notifications() == 'disabled') {
        $prepareRequest->setMerchantNotification(false);
      }
    }

    $shopper->setAccount($account);
    $prepareRequest->setShopper($shopper);
    $prepareRequest->setOrder($order);
    $prepareRequest->setShipping($shipping);


    $result = $c2pClient->preparePayment($prepareRequest);
    if ($result !== false) {
      $_SESSION['customerToken'] = $result->getCustomerToken();
      $_SESSION['merchantToken'] = $result->getMerchantToken();
    } else {
      echo "Payment preparation error occurred: " . $c2pClient->getClientErrorMessage() . "\n";
    }

    echo '<input type="hidden" value="' . $_SESSION['customerToken'] . '" id="tokenpass"/>';
    echo '<input type="hidden" value="' . $_SESSION['merchantToken'] . '" name="merchantToken"/>';
    echo '<input type="hidden" value="' . $this->getSeamlessCheckoutVersion() . '" id="seamless_version" name="seamless_version"/>';
    echo '<input type="hidden" value="' . $this->getSeamlessCheckoutHash() . '" id="seamless_hash" name="seamless_hash"/>';
    echo '<input type="hidden" value="" id="transactionId" name="transactionId"/>';
    echo '<input type="hidden" value="" id="paymentId" name="paymentId"/>';
    echo '<input type="hidden" value="" id="paymentstatus" name="paymentstatus"/>';
  }


  public function seamless_credit_card_process_payment($order_id)
  {
    $order = new WC_Order($order_id);

    if (empty($_POST['transactionId']) || empty($_POST['paymentId']) || $_POST['paymentstatus'] !== "000") {
      wc_add_notice('Progressing Payment....', 'notice');
      return;
    }

    if (get_option('payxpart_store_' . $_POST['merchantToken'] . '_' . $_POST['transactionId'] . '_' . $_POST['paymentstatus'] . '')) {
      wc_add_notice('Same MerchantToken, Transaction ID Found in Database, please don\'t try it', 'error');
      return;
    }

    if (!empty($_POST['transactionId'])) {
      global $woocommerce;
      $amount = $woocommerce->cart->total;
      $transactionId = $_POST['transactionId'];
      $c2pClient = new Connect2PayClient($this->getConnectUrl(), $this->getOriginatorId(), $this->getpassword());
      $transaction = $c2pClient->getTransactionInfo($transactionId);

      if ($transaction != null && $transaction->getResultCode() != null) {
        if ($transaction->getPaymentID() != $_POST['paymentId']) {
          wc_add_notice('Payment ID Not matching with PayXpert, Please Contact with Website Owner.', 'error');
          return;
        }

        if ($transaction->getPaymentMerchantToken() != $_POST['merchantToken']) {
          wc_add_notice('Merchant Token Not matching with PayXpert, Please Contact with Website Owner.', 'error');
          return;
        }

        if ($transaction->getTransactionID() != $_POST['transactionId']) {
          wc_add_notice('Transaction ID Not matching with PayXpert, Please Contact with Website Owner.', 'error');
          return;
        }
      } else {
        wc_add_notice('Error:' . $c2pClient->getClientErrorMessage(), 'error');
        return;
      }
    }

    // we received the payment
    $message = "Successful transaction by customer redirection. Transaction Id: " . $_POST['transactionId'];
    $order->payment_complete($_POST['transactionId']);
    $order->reduce_order_stock();
    $order->update_status('completed', $message);

    // Save the merchant token for callback verification For later
    $order->update_meta_data('_payxpert_merchant_token', $_POST['merchantToken']);
    $order->update_meta_data('_payxpert_transaction_id', $_POST['transactionId']);
    $order->update_meta_data('_payxpert_payment_id', $_POST['paymentstatus']);
    $order->update_meta_data('payxpart_store_' . $_POST['merchantToken'] . '_' . $_POST['transactionId'] . '_' . $_POST['paymentstatus'], 'Yes Used Once!');
    $order->save();

    // Redirect to the thank you page
    return array(
      'result' => 'success',
      'redirect' => $order->get_checkout_order_received_url()
    );
  }

}
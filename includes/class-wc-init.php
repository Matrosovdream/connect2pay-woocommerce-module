<?php
include_once(PX_ABS."/vendor/autoload.php");


/**
 * Check if WooCommerce is active
 **/
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/*
 * Include Gateway Setting
 */
require_once PX_ABS."/includes/class-wc-setting.php";

/*
 * Include PayXpert main Class
 */
require_once PX_ABS."/includes/class-wc-payxpert.php";

/**
 * The Main Class Of Plugin
 */
final class PayxpertMainClass
{
    // Class construction
    private function __construct()
    {
        $this->define_function();

        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_filter('woocommerce_payment_gateways', [$this, 'woocommerce_payxpert_gateway']);
        add_action('admin_head', [$this, 'redirct_to_another_setting']);
        add_action('wp_footer', [$this, 'payxpert_payment_script_footer']);
        add_action('plugins_loaded', [$this, 'woocommerce_payxpert_init'], 0);
    }

    /*
        Single instence 
    */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }


    public function define_function()
    {
        define("PX_FILE", __FILE__);
        define("PX_PATH", __DIR__);
        define("PX_URL", plugins_url('', PX_FILE));
        define("PX_ASSETS", PX_URL . '/assets');
    }

    public function init_plugin()
    {
        new PayXpertOption();

    }

    public function woocommerce_payxpert_gateway($methods)
    {
        $methods[] = 'WC_Gateway_PayXpert_WeChat';
        $methods[] = 'WC_Gateway_PayXpert_Alipay';
        $methods[] = 'WC_PayXpert_Seamless_Gateway';
        $methods[] = 'WC_PayXpert_Simple';
        return $methods;
    }

    public function redirct_to_another_setting()
    {
        if (isset($_GET['page']) && isset($_GET['tab']) && isset($_GET['section'])) {
            $getoptionurl = get_admin_url(null, "/admin.php?page=wc-settings&tab=checkout&section=payxpert");
            // PayXpert Seamless Option
            if ($_GET['page'] == "wc-settings" && $_GET['tab'] == "checkout" && $_GET['section'] == "payxpert_seamless") {
                wp_safe_redirect($getoptionurl);
            }

            // PayXpert WeChat Option
            if ($_GET['page'] == "wc-settings" && $_GET['tab'] == "checkout" && $_GET['section'] == "payxpert_wechat") {
                wp_safe_redirect($getoptionurl);
            }

            // PayXpert Alipay Option
            if ($_GET['page'] == "wc-settings" && $_GET['tab'] == "checkout" && $_GET['section'] == "payxpert_alipay") {
                wp_safe_redirect($getoptionurl);
            }

            // PayXpert Alipay Option
            if ($_GET['page'] == "wc-settings" && $_GET['tab'] == "checkout" && $_GET['section'] == "payxpert") {
                $optionupdate = array('enabled' => 'yes');
            }

        }
    }

    public function payxpert_payment_script_footer()
    {
        if (is_checkout()) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if ( $gateways['payxpert_seamless']->enabled == 'yes' ) {
                include_once( PX_ABS."/includes/seamless-footer-js.php");
            }
        }
    }

    public function woocommerce_payxpert_init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        if (get_option('payxpert_wechat_pay') == "yes") {
            include_once( PX_ABS."/includes/gateways/class-wc-gateway-payxpert-wechat.php");
        }
        if (get_option('payxpert_seamless_card') == "yes") {
            include_once( PX_ABS."/includes/gateways/class-wc-gateway-payxpert-card.php");
        }
        if (get_option('payxpert_alipay') == "yes") {
            include_once( PX_ABS."/includes/gateways/class-wc-gateway-payxpert-alipay.php");
        }
    }
}

/*
Initialize the main plugin
*/
function Payxpert_init()
{
    return PayxpertMainClass::init();
}

/*
Active Plugin
*/
Payxpert_init();
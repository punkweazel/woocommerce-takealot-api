<?php
/**
 * @package MarketplaceGenie_API
 * @version 1.0
 */
/*
Plugin Name: MarketplaceGenie API
Description: The Marketplace Genie API adaptor synchronizes data between WordPress/WooCommerce and the Marketplace Genie platform.
Author: MarketplaceGenie (Pty) Ltd
Version: 1.0
Author URI: https://www.marketplacegenie.co.za/app/woocommerce/
 */

if ( ! defined( 'ABSPATH' ) ) 
{
    exit; // Exit if accessed directly
}

// include dirname( __FILE__ ) . '/includes/...';

if ( !function_exists( 'getallheaders' ) ) 
{
    function getallheaders() {
        $headers = '';
        foreach ( $_SERVER as $name => $value ) {
            if ( substr( $name, 0, 5 ) == 'HTTP_' ) {
                $headers[str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) )) ) )] = $value;
            }
        }
        return $headers;
    }
}

class MarketplaceGenie 
{
    public  $version        =   '1.0.0';
    const   OPTION_GROUP    =   'marketplacegenie-option-group';
    const   URL	            =	'http://api.marketplacegenie.co.za:10000/v1';
    private $apiKey         =   null;

    public function init() 
    {
    }

    public static function apiEnabled()
    {
        return ('true' == esc_attr(get_option('marketplacegenie_api')));
    }

    public static function apiStatus()
    {
        $result     =   false;
        $fileHandle =   null;
        $curlHandle =   null;

        $fileHandle = tmpfile();

        if (is_resource($fileHandle)) {
            $curlHandle = curl_init();

            if (is_resource($curlHandle)) {
                $options = Array(
                        CURLOPT_URL => self::URL . "/status",
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_FAILONERROR => 1,
                        CURLOPT_FOLLOWLOCATION => 1,
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:11.0) Gecko/20100101 Firefox/11.0",
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_COOKIEFILE => $fileHandle,
                        CURLOPT_COOKIEJAR => $fileHandle
                    );

                curl_setopt_array($curlHandle, $options);
                    
                $result     = curl_exec($curlHandle);
                $jsonObject = json_decode(curl_exec($curlHandle), true);

                if (array_key_exists('result_string', $jsonObject) == true && $jsonObject['result_string'] == 'System OK') {
                    $result = true;
                }

                curl_close($curlHandle);
            }

            fclose($fileHandle);
        }

        return $result;
    }

    public static function updateOffer($id)
    {
        $result = false;
        $offerAttributes    =   Array(
            'price'             =>  0,
            'rrp'               =>  0,
            'leadtime_days'     =>  0,
            'leadtime_stock'    =>  0,
            'status'            =>  null,
            'sku'               =>  null    
        );

        $product = wc_get_product($id);

        if (($product != null) && ($product != false)) {
            $offerAttributes['price']          = $product->get_price();
            $offerAttributes['rrp']            = $product->get_regular_price();
            $offerAttributes['leadtime_days']  = 6;
            $offerAttributes['leadtime_stock'] = $product->get_stock_quantity();
            $offerAttributes['status']         = $product->get_status() == 'publish' ? 'Active' : 'Inactive';
            $offerAttributes['sku']            = $product->get_sku();

            $result = self::apiUpdateOffer($offerAttributes, $product->get_sku());
        }

        return $result;
    }

    private static function apiUpdateOffer($offerAttributes, $key, $key_type = 'SKU')
    {
        $result = false;
        $fileHandle = null;
        $curlHandle = null;

        if (($key != null) || ($key != false)) {
            $fileHandle = tmpfile();

            if (is_resource($fileHandle)) 
            {
                $curlHandle = curl_init();

                if (is_resource($curlHandle)) 
                {
                    $options = Array(
                        CURLOPT_HTTPHEADER => array('Authorization: ' . 'Bearer ' . get_option('marketplacegenie_api_key')),
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_URL => self::URL . "/offers/offer/{$key_type}{$key}",
                        CURLOPT_FAILONERROR => 1,
                        CURLOPT_FOLLOWLOCATION => 1,
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:11.0) Gecko/20100101 Firefox/11.0",
                        CURLOPT_CUSTOMREQUEST => 'PATCH',
                        CURLOPT_POSTFIELDS => json_encode($offerAttributes),
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_COOKIEFILE => $fileHandle,
                        CURLOPT_COOKIEJAR => $fileHandle
                    );

                    curl_setopt_array($curlHandle, $options);
                    
                    $result = curl_exec($curlHandle);
                    $result = true;
                    curl_close($curlHandle);
                }

                fclose($fileHandle);
            }

            $fileHandle = null;
        }

        return $result;
    }
}

/**
 * @desc        Check if WooCommerce is active.
 * 
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function marketplacegenie_Initialize() 
    {
        $marketplaceGenie = new MarketplaceGenie();
        $marketplaceGenie->init();
    }

    add_action( 'init', 'marketplacegenie_Initialize' );

    function marketplacegenie_QueryVariables( $vars ) 
    {
        $vars[] = 'marketplacegenie';
        return $vars;
    }

    add_filter( 'query_vars', 'marketplacegenie_QueryVariables' );
/**
 * @desc        Administration.
 * 
 */
    function register_marketplacegenie_Settings() 
    {
        register_setting( MarketplaceGenie::OPTION_GROUP, 'marketplacegenie_api', 'marketplacegenie_ValidateApi' );
        register_setting( MarketplaceGenie::OPTION_GROUP, 'marketplacegenie_api_key', 'marketplacegenie_ValidateApiKey' );
    }

    function marketplacegenie_ValidateApi($val)
    {
        return $val;
    }

    function marketplacegenie_ValidateApiKey($val)
    {
        return $val;
    }

    add_action( 'admin_init', 'register_marketplacegenie_Settings' );
    add_action( 'admin_menu', 'marketplacegenie_WoocommerceMenu' );

    function marketplacegenie_WoocommerceMenu() 
    {
        add_submenu_page( 'woocommerce', 'MarketplaceGenie Options', 'MarketplaceGenie', 'manage_options', 'marketplacegenie', 'marketplacegenie_WoocommerceOptions' );
    }

    function marketplacegenie_WoocommerceOptions() 
    {
        $marketplaceGenieApi    = esc_attr(get_option('marketplacegenie_api'));
        $marketplaceGenieApiKey = esc_attr(get_option('marketplacegenie_api_key'));
        $markup                 = null;

        if ( !current_user_can( 'manage_options' ) )  
        {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ));
        }

        ob_start();

        settings_fields(MarketplaceGenie::OPTION_GROUP);
        do_settings_sections(MarketplaceGenie::OPTION_GROUP);
        submit_button();

        $markup = ob_get_contents();
        ob_end_clean();

        include_once 'options-head.php';
        $markup = <<<EOD
<div class="wrap">
    <h1 class="wp-heading-inline">MarketplaceGenie</h1>
    <form method="post" action="options.php">
        <div id="message" class="updated woocommerce-message wc-connect">
	        <p><strong>Welcome to MarketplaceGenie</strong> – You‘re almost ready to integrate</p>
            <p class="submit">
                <a href="https://www.marketplacegenie.co.za/login" target="_blank" class="button-primary">Sign in to your Marketplace Genie account to get your API key!</a>
            </p>
        </div>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">MarketplaceGenie API Key</th>
                <td><input type="text" name="marketplacegenie_api_key" value="$marketplaceGenieApiKey" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Enable API</th>
                <td>
                    <input type="checkbox" name="marketplacegenie_check" id="checkControl"/>
                    <input type="hidden" name="marketplacegenie_api" id="checkValue" value="$marketplaceGenieApi"/>
                </td>
            </tr>
        </table>
        $markup
    </form>
</div>
<script>
var o = document.getElementById('checkControl');

if ('$marketplaceGenieApi' == 'true') 
{
    o.checked = true;
} 
else
{
    o.checked = false;
}

o.addEventListener('click', function() 
{ 
    if (this.checked == true)
    {
        document.getElementById('checkValue').value = 'true';
    }
    else 
    {
        document.getElementById('checkValue').value = '';
    }
});
</script>
EOD;
        print($markup);
	}

	add_action( 'save_post', 'MarketplaceGenie::updateOffer' );
	add_action( 'woocommerce_product_quick_edit_save', 'MarketplaceGenie::updateOffer' );

    function marketplacegenie_takealot_add_settings_link( $links ) 
    {
        $settings_link = '<a href="'.admin_url( 'admin.php?page=marketplacegenie' ).'">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    $plugin = plugin_basename( __FILE__ );

    add_filter( "plugin_action_links_$plugin", 'marketplacegenie_takealot_add_settings_link' );
}

add_action('admin_bar_menu', 'add_toolbar_items', 100);
function add_toolbar_items($admin_bar)
{
    if (MarketplaceGenie::apiEnabled())
    {
        $admin_bar->add_menu( array(
            'id'    => 'marketplacegenie-takealot-api-status',
            'title' => __('<span>API Status' . (MarketplaceGenie::apiStatus() ? '&nbsp;<span style="color: #00ff00;">+</span>':'<span style="color: #ff0000;">-</span></span>')),
            'href'  => '#',
            'meta'  => array(
                'title' => __('Marketplace Genie Takealot API Status'),            
            ),
        ));
    }
}
?>

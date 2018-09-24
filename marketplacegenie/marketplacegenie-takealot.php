<?php
/**
 * @package MarketplaceGenie_API
 * @version 1.1
 */
/*
	Plugin Name: MarketplaceGenie API
	Description: The Marketplace Genie API adaptor synchronizes data between WordPress/WooCommerce and the Marketplace Genie platform.
	Author: MarketplaceGenie (Pty) Ltd
	Version: 1.1
	Author URI: https://www.marketplacegenie.co.za/app/woocommerce/
 */

if (!defined( 'WPINC' )) 
{
    exit;
}

class MarketplaceGenie 
{
    public  $version        =   '1.0.1';
    const   OPTION_GROUP    =   'marketplacegenie-option-group';
    const   URL	            =	'https://api.marketplacegenie.co.za/takealot-api/v1';
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
        $args       =   array(
            'headers'   =>  array( 'Content-type' => 'application/json' )
        );

        $result = wp_remote_get(self::URL . '/status', $args);
        
        if (is_array($result) && array_key_exists('response', $result) && (intval($result['response']['code']) == 200))
        {
            $jsonObject = json_decode($result['body'], true);

            if ((array_key_exists('result_string', $jsonObject) == true) && ($jsonObject['result_string'] == 'System OK')) 
            {
                $result = true;
            }
            else 
            {
                $result = false;
            }
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
        $result     =   false;
        $args       =   array(
            'method'    =>  'PATCH',
            'headers'   =>  array(
                "Authorization" =>  'Bearer ' . get_option('marketplacegenie_api_key'),
                "Content-type"  =>  'application/json' ),
            'body'      =>  json_encode($offerAttributes)
        );

        $result = wp_remote_request( self::URL . "/offers/offer/{$key_type}{$key}", $args );

        if (is_array($result) && array_key_exists('response', $result) && (intval($result['response']['code']) == 200))
        {
            $result = true;
        }
        else 
        {
            $result = false;
        }
        
        return $result;
    }
}
/**
 * @desc        Check if WooCommerce is active.
 * 
 */
if (in_array( 'woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) 
{
    function marketplacegenie_init() 
    {
        $marketplaceGenie = new MarketplaceGenie();
        $marketplaceGenie->init();
    }
    add_action( 'init', 'marketplacegenie_init' );

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
    function marketplacegenie_adminInit() 
    {
        register_setting( MarketplaceGenie::OPTION_GROUP, 'marketplacegenie_api', 'marketplacegenie_validateApi' );
        register_setting( MarketplaceGenie::OPTION_GROUP, 'marketplacegenie_api_key', 'marketplacegenie_validateApiKey' );
    }
    add_action( 'admin_init', 'marketplacegenie_adminInit' );

    function marketplacegenie_validateApi($val)
    {
        return $val;
    }

    function marketplacegenie_validateApiKey($val)
    {
        return $val;
    }

    function marketplacegenie_wooCommerceMenu() 
    {
        add_submenu_page( 'woocommerce', 'MarketplaceGenie Options', 'MarketplaceGenie', 'manage_options', 'marketplacegenie', 'marketplacegenie_wooCommerceOptions' );
    }
    add_action( 'admin_menu', 'marketplacegenie_wooCommerceMenu' );

    function marketplacegenie_wooCommerceOptions() 
    {
        $marketplaceGenieApi    = esc_attr(get_option('marketplacegenie_api'));
        $marketplaceGenieApiKey = esc_attr(get_option('marketplacegenie_api_key'));
        $markup                 = null;

        if (!current_user_can( 'manage_options' ))  
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

    function marketplacegenie_addSettingsLink( $links ) 
    {
        $settings_link = '<a href="'.admin_url( 'admin.php?page=marketplacegenie' ).'">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    $marketplaceGeniePlugin = plugin_basename( __FILE__ );

    add_filter( "plugin_action_links_$marketplaceGeniePlugin", 'marketplacegenie_addSettingsLink' );

    add_action('admin_bar_menu', 'marketplacegenie_addToolbarItems', 100);

    function marketplacegenie_addToolbarItems($admin_bar)
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
}
 ?>

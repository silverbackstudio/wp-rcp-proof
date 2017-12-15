<?php
/**
 * Main plugin file
 *
 * @package svbk-rcp-proof
 */

/*
Plugin Name: Restrict Content Pro - Push Subscriptions to useproof.com
Description: Send User Data to useproof.com to show notification
Author: Silverback Studio
Version: 1.1
Author URI: http://www.silverbackstudio.it/
Text Domain: svbk-rcp-proof
*/

namespace Svbk\WP\Plugins\RCP\Proof;


/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function init() {
	load_plugin_textdomain( 'svbk-rcp-proof', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	add_action( 'rcp_add_subscription_form',  __NAMESPACE__ . '\level_form' );
	add_action( 'rcp_edit_subscription_form',  __NAMESPACE__ . '\level_form'  );

	add_action( 'rcp_add_subscription', __NAMESPACE__ . '\level_save' , 10, 2 );
	add_action( 'rcp_pre_edit_subscription_level', __NAMESPACE__ . '\level_save' , 10, 2 );
	
	add_action( 'rcp_member_post_set_subscription_id', __NAMESPACE__ . '\push_notification' , 10, 3 );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );


/**
 * Prints the HTML fields in subscrioption's admin panel
 *
 * @param object $level Optional. The subscription level object.
 *
 * @return void
 */
function level_form( $level = null ) {
	global $rcp_levels_db;

	$defaults = array(
		'proof_webhook_url' => '',
	);

	if ( ! empty( $level ) ) {
		$defaults['proof_webhook_url'] = $rcp_levels_db->get_meta( $level->id, 'proof_webhook_url', true );
	}
	?>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="proof_webhook_url"><?php esc_html_e( 'Proof Webhook URL', 'svbk-rcp-proof' ); ?></label>
		</th>
		<td>
			<input type="text" id="proof_webhook_url"  name="proof_webhook_url" value="<?php echo esc_attr( $defaults['proof_webhook_url'] ); ?>"/>
			<p class="description"><?php esc_html_e( 'The useproof.com webhook URL for the Notification', 'svbk-rcp-proof' ); ?></p>
		</td>
	</tr>
<?php }


/**
 * Saves countdown values from the subscription admin pane.
 *
 * @param int   $level_id The subscription level ID.
 * @param array $args The submitted form filed values.
 *
 * @return void
 */
function level_save( $level_id, $args ) {

	global $rcp_levels_db;

	$defaults = array(
    	'proof_webhook_url'  => '',
	);

	$args = wp_parse_args( $args, $defaults );

	$webhook_url = esc_url_raw( $args['proof_webhook_url'] );

	if ( current_filter() === 'rcp_add_subscription' ) {
		$rcp_levels_db->add_meta( $level_id, 'proof_webhook_url', $webhook_url );
	} elseif ( current_filter() === 'rcp_pre_edit_subscription_level' ) {
		$rcp_levels_db->update_meta( $level_id, 'proof_webhook_url', $webhook_url );
	}
}

function extract_member_meta( $data, $field ){
    
    if( !empty($data[$field]) && !empty( $data[$field][0] ) ) {
        return $data[$field][0];
    }
    
    return '';
}

function push_notification( $subscription_id, $member_id, $member ){
 
    global $rcp_levels_db; 
    
    $webhook_url = $rcp_levels_db->get_meta( $subscription_id, 'proof_webhook_url', true );
    
    if($webhook_url){
    
        $user_meta = get_user_meta($member_id);
        
        $response = wp_remote_post( 
            $webhook_url, 
            array(
            	'timeout' => 5,
            	'redirection' => 5,
            	'blocking' => true,
                'headers' => array( 'Content-Type' => 'application/json' ),
            	'body' => json_encode(array(
            	    "type" => "custom",
            	    "first_name" => extract_member_meta($user_meta, 'first_name'),
                	"email"=>$member->data->user_email,
                	"city" => extract_member_meta($user_meta, 'biling_city'),
                	"state" => extract_member_meta($user_meta, 'biling_state'),
                	"country" => extract_member_meta($user_meta, 'billing_country'),
                	"full_name" => extract_member_meta($user_meta, 'first_name') . ' ' . extract_member_meta($user_meta, 'last_name') , 
                	'ip' => filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP),
                	"timestamp" => time()
            	)) ,
            )
        );
        
        if ( is_wp_error( $response ) ) {
           wp_mail( get_option( 'admin_email' ), 'Error in submitting proof webhook', 'Error occurred in submitting useproof.com webhook (' . $webhook_url. '): '. $response->get_error_message());
        }
        
    }
 
    
}
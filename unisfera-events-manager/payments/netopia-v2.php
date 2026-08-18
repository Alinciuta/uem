<?php
if (!defined('ABSPATH')) exit;
/** The official SDK is deliberately required for IPN cryptographic verification. */
function uem_netopia_v2_checkout($order){$settings=uem_payment_settings();if(!$settings['pos_signature']||!$settings['api_key'])return '<p>NETOPIA is not configured. Please contact the organizer.</p>';return '<div style="max-width:650px;margin:40px auto;padding:30px;text-align:center;background:#fff;border-radius:16px"><h2>Secure card payment</h2><p>Your order <strong>'.esc_html($order->order_ref).'</strong> was created for '.esc_html(number_format_i18n($order->amount,2)).' RON.</p><p>The NETOPIA v2 hosted-card SDK must be installed before a live card session can be opened. No card details are collected by Unisfera.</p></div>';}
add_action('rest_api_init',function(){register_rest_route('uem/v1','/netopia/ipn',['methods'=>'POST','callback'=>function($request){return new WP_REST_Response(['received'=>true],202);},'permission_callback'=>'__return_true']);});

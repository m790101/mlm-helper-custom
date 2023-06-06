
<?php
/*
Plugin Name: Affiliate Activation
Plugin URI: [Plugin URL]
Description: Activates user as an affiliate if they spend more than $3000 in a single order.
Version: 1.0
Author: haowen
Author URI: [Your Website]
*/

// Plugin code goes here
// Hook into WooCommerce payment complete action
add_action('woocommerce_payment_complete', 'activate_affiliate_based_on_order_total');

function activate_affiliate_based_on_order_total($order_id)
{
    // Get the order object
    $order = wc_get_order($order_id);
    
    // Get the user ID associated with the order
    $user_id = $order->get_user_id();

    // Get the order total
    $order_total = $order->get_total();
    
    // Check if the order total is greater than $3000
    if ($order_total > 3000) {
        // Activate user as an affiliate (using AffiliateWP functions)
        if (class_exists('Affiliate_WP')) {
            global $Affiliate_WP;

            // Activate the user as an affiliate
            $Affiliate_WP->register->activate_affiliate($user_id);
        }
    }
}



add_filter('affwp_calc_referral_amount', 'adjust_affiliate_rate_based_on_category', 10, 4);

function adjust_affiliate_rate_based_on_category($amount, $affiliate_id, $reference, $reference_type)
{
    // Check if the reference type is an order
    if ($reference_type === 'order') {
        // Get the order object
        $order = wc_get_order($reference);

        // Check if the order contains a product from the desired category
        $category_name = 'example-category'; // Replace with your desired category slug
        $category_id = get_term_by('slug', $category_name, 'product_cat')->term_id;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product_categories = get_the_terms($product_id, 'product_cat');

            foreach ($product_categories as $product_category) {
                if ($product_category->term_id === $category_id) {
                    // Adjust the affiliate rate to half
                    $amount *= 0.5;
                    break 2;
                }
            }
        }
    }

    return $amount;
}


add_filter('affwp_mlm_calc_commission_amount', 'accumulate_commission_from_level_to_level', 10, 3);

function accumulate_commission_from_level_to_level($commission, $affiliate_id, $referral)
{
    // Check if the referral is a direct referral from the specified source level
    $source_level = 4; // Specify the source level here
    if ($referral->level === $source_level && $referral->type === 'direct') {
        // Get the MLM settings
        $settings = affwp_get_option('affwp_mlm_settings', array());

        // Get the level relationships
        $level_relationships = array(
            1 => 3, // Level 1 buys from Level 3
            2 => 4, // Level 2 buys from Level 4
            // Add more level relationships as needed
        );

        // Get the commission rates for each level based on the relationships
        $commission_rates = array();
        for ($i = 1; $i <= $referral->level; $i++) {
            $level = isset($level_relationships[$i]) ? $level_relationships[$i] : 0;
            $commission_rates[$i] = isset($settings['mlm_rate_' . $level]) ? $settings['mlm_rate_' . $level] : 0;
        }

        // Accumulate the commission from the source level to the referral level
        $commission = 0;
        for ($i = $source_level; $i <= $referral->level; $i++) {
            $commission += $referral->amount * $commission_rates[$i];
        }
    }

    return $commission;
}
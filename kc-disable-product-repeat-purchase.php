<?php

/*
Plugin Name: Disable Repeat Purchase - WooCommerce
Description: Disable repeat purchase of tickets.
Author: Peter Fealey
Author URI: https://www.ketchupgroup.com
Version: 1.0
Text Domain: disable-ticket-repeat-purchase
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

add_action( 'woocommerce_check_cart_items', 'check_total' );
function check_total() {
    // Only run in the Cart or Checkout pages
    if( is_cart() || is_checkout() ) {

        global $woocommerce, $product;

        $total_quantity = 0;
        $display_notice = 1;
        $i = 0;
        //loop through all cart products
        foreach ( $woocommerce->cart->cart_contents as $product ) {

            // See if any product is from the selected category or not
            if ( has_term( 'limited-tickets', 'product_cat', $product['product_id'] )) {
                $total_quantity += $product['quantity'];
            }

        }
        // Set up the acceptable totals and loop through them so we don't have an ugly if statement below.
        $acceptable_totals = array(1, 2, 3, 4);

        foreach($acceptable_totals as $total_check) {
            if ( $total_check == $total_quantity ) { $display_notice = 0; } 
        }

        foreach ( $woocommerce->cart->cart_contents as $product ) {
            if ( has_term( 'limited-tickets', 'product_cat', $product['product_id'] ) ) {
                if( $display_notice == 1 && $i == 0 ) {
                    // Display our error message
                    wc_add_notice( sprintf( 'This product can only be sold in following quantities: 1 | 2 | 3 | 4.</p>', $total_quantity),
                    'error' );
                }
                $i++;
            }
        }
    }
}

function kc_disable_repeat_purchase( $purchasable, $product ) {
	
	// Get the ID for the current product (passed in)
	$product_id = $product->is_type( 'variation' ) ? $product->variation_id : $product->id;

	// set the ID of the product that shouldn't be purchased again
	
	if ( has_term( 'limited-tickets', 'product_cat', $product_id )) {
                $non_purchasable = $product_id;
	} else {
		return $purchasable;
	}
	
	// Bail unless the ID is equal to our desired non-purchasable product
	if ( $non_purchasable != $product_id ) {
		return $purchasable;
	}
    
    // return false if the customer has bought the product
    if ( wc_customer_bought_product( get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
        $purchasable = false;
    }
    
    // Double-check for variations: if parent is not purchasable, then variation is not
    if ( $purchasable && $product->is_type( 'variation' ) ) {
        $purchasable = $product->parent->is_purchasable();
    }
    
    return $purchasable;
}
add_filter( 'woocommerce_variation_is_purchasable', 'kc_disable_repeat_purchase', 10, 2 );
add_filter( 'woocommerce_is_purchasable', 'kc_disable_repeat_purchase', 10, 2 );

/**
 * Shows a "purchase disabled" message to the customer
 */
function kc_purchase_disabled_message() {
	
	// Get the current product to check if purchasing should be disabled
	global $product;
	
	
	// Get the ID for the current product (passed in)
	$product_id = $product->is_type( 'variation' ) ? $product->variation_id : $product->id;

	// set the ID of the product that shouldn't be purchased again	
	if ( has_term( 'limited-tickets', 'product_cat', $product_id )) {
                $no_repeats_id = $product_id;
	} else {
		return;
	}
    	$no_repeats_product = wc_get_product( $no_repeats_id );

	
	if ( $no_repeats_product->is_type( 'variation' ) ) {
		// Bail if we're not looking at the product page for the non-purchasable product
		if ( ! $no_repeats_product->parent->id === $product->id ) {
			return;
		}
		
		// Render the purchase restricted message if we are
		if ( wc_customer_bought_product( get_current_user()->user_email, get_current_user_id(), $no_repeats_id ) ) {
			kc_render_variation_non_purchasable_message( $product, $no_repeats_id );
		}
		
	} elseif ( $no_repeats_id === $product->id ) {
		if ( wc_customer_bought_product( get_current_user()->user_email, get_current_user_id(), $no_repeats_id ) ) {
			// Create your message for the customer here
			echo '<div class="woocommerce"><div class="woocommerce-info wc-nonpurchasable-message">You\'ve already purchased this product! It can only be purchased once.</div></div>';
		}
	}
}
add_action( 'woocommerce_single_product_summary', 'kc_purchase_disabled_message', 31 );


/**
 * Generates a "purchase disabled" message to the customer for specific variations
 * 
 * @param \WC_Product $product the WooCommerce product
 * @param int $no_repeats_id the id of the non-purchasable product
 */
function kc_render_variation_non_purchasable_message( $product, $no_repeats_id ) {
	
	// Double-check we're looking at a variable product
	if ( $product->is_type( 'variable' ) && $product->has_child() ) {
		$variation_purchasable = true;
		
		foreach ( $product->get_available_variations() as $variation ) {
			// only show this message for non-purchasable variations matching our ID
			if ( $no_repeats_id === $variation['variation_id'] ) {
				$variation_purchasable = false;	
				// edit your customer message here
				echo '<div class="woocommerce"><div class="woocommerce-info wc-nonpurchasable-message js-variation-' . sanitize_html_class( $variation['variation_id'] ) . '">You\'ve already purchased this product! It can only be purchased once.</div></div>';
			}
		}
	}
	
	// show / hide this message for the right variation with some jQuery magic
	if ( ! $variation_purchasable ) {
		wc_enqueue_js("
			jQuery('.variations_form')
				.on( 'woocommerce_variation_select_change', function( event ) {
					jQuery('.wc-nonpurchasable-message').hide();
				})
				.on( 'found_variation', function( event, variation ) {
					jQuery('.wc-nonpurchasable-message').hide();
					if ( ! variation.is_purchasable ) {
						jQuery( '.wc-nonpurchasable-message.js-variation-' + variation.variation_id ).show();
					}
				})
			.find( '.variations select' ).change();
		");
	}
}
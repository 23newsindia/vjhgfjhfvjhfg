<?php




add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'ts_filter_valid_order_statuses_for_cancel', 20, 2 );

function ts_filter_valid_order_statuses_for_cancel( $statuses, $order = '' ){

// Set HERE the order statuses where you want the cancel button to appear
$custom_statuses = array( 'pending', 'on-hold', 'failed' );

// Return the custom statuses
return $custom_statuses;
}









// Add custom product fields
function add_custom_product_fields() {
    add_meta_box(
        'custom_product_fields',
        'Additional Product Details',
        'custom_product_fields_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_custom_product_fields');

// Callback function to display the custom fields
function custom_product_fields_callback($post) {
    $product_fit = get_post_meta($post->ID, '_product_fit', true);
    $fabric_type = get_post_meta($post->ID, '_fabric_type', true);
    $product_tag = get_post_meta($post->ID, '_product_tag', true);

    wp_nonce_field('custom_product_fields', 'custom_product_fields_nonce');
    ?>
    <div class="custom-fields-container">
        <p>
            <label for="product_fit">Product Fit:</label>
            <input type="text" id="product_fit" name="product_fit" value="<?php echo esc_attr($product_fit); ?>" />
            <span class="description">E.g., OVERSIZED FIT, REGULAR FIT, etc.</span>
        </p>
        <p>
            <label for="fabric_type">Fabric Type:</label>
            <input type="text" id="fabric_type" name="fabric_type" value="<?php echo esc_attr($fabric_type); ?>" />
            <span class="description">E.g., 100% COTTON, PREMIUM DENSE FABRIC, etc.</span>
        </p>
        <p>
            <label for="product_tag">Product Tag:</label>
            <input type="text" id="product_tag" name="product_tag" value="<?php echo esc_attr($product_tag); ?>" />
            <span class="description">E.g., NEW ARRIVAL, LIMITED EDITION, etc.</span>
        </p>
    </div>
    <?php
}

// Save custom fields
function save_custom_product_fields($post_id) {
    if (!isset($_POST['custom_product_fields_nonce']) ||
        !wp_verify_nonce($_POST['custom_product_fields_nonce'], 'custom_product_fields')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields = array('product_fit', 'fabric_type', 'product_tag');

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('save_post_product', 'save_custom_product_fields');























add_action('wp_ajax_load_gender_content', 'handle_gender_content_request');
add_action('wp_ajax_nopriv_load_gender_content', 'handle_gender_content_request');

function handle_gender_content_request() {
    check_ajax_referer('gender_tabs_nonce', 'nonce');

    $gender = sanitize_text_field($_POST['gender'] ?? '');
    $valid_genders = ['women', 'men', 'kids'];
    
    if (!in_array($gender, $valid_genders)) {
        wp_send_json_error('Invalid gender parameter');
    }

    // Get just the content part
    ob_start();
    get_template_part('template-parts/content', $gender);
    $content = ob_get_clean();

    wp_send_json_success([
        'content' => $content,
        'new_nonce' => wp_create_nonce('gender_tabs_nonce')
    ]);
}





// Limit transient lifespan
define('WC_MAX_EXPIRING_LAYERED_NAV_COUNTS', 3600); // 1 hour instead of default 1 week



// In your functions.php - Add this at the BOTTOM of the file (after all other code)

// Allow cancellation for pending, processing, and on-hold orders
add_filter('woocommerce_valid_order_statuses_for_cancel', 'custom_valid_order_statuses_for_cancel', 10, 2);
function custom_valid_order_statuses_for_cancel($statuses, $order) {
    return array('pending', 'processing', 'on-hold');
}

// Extend cancellation time limit to 5 days
add_filter('woocommerce_cancel_unpaid_order', 'extend_cancel_unpaid_order_time', 10, 2);
function extend_cancel_unpaid_order_time($bool, $order) {
    $order_date = $order->get_date_created();
    $current_date = new DateTime();
    $days_difference = $current_date->diff($order_date)->days;
    
    return $days_difference <= 5;
}

// Handle AJAX order cancellation
add_action('wp_ajax_cancel_order', 'handle_order_cancellation'); // Only for logged-in users
function handle_order_cancellation() {
    // Verify nonce first
    if (!check_ajax_referer('cancel-order', 'security', false)) {
        wp_send_json_error('Invalid security token');
    }

    if (!isset($_POST['order_id'])) {
        wp_send_json_error('Order ID missing');
    }

    $order = wc_get_order(absint($_POST['order_id']));
    
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    // Verify current user owns this order
    if ($order->get_customer_id() !== get_current_user_id()) {
        wp_send_json_error('Order does not belong to current user');
    }

    // Check if order can be cancelled
    if (!$order->has_status(['pending', 'processing', 'on-hold'])) {
        wp_send_json_error('Order cannot be cancelled');
    }

    // Cancel the order
    $order->update_status('cancelled', __('Order cancelled by customer.', 'woocommerce'));
    
    wp_send_json_success(array(
        'message' => __('Order cancelled successfully.', 'woocommerce'),
        'order_id' => $order->get_id()
    ));
}









function remove_all_css_except_custom_mobile() {
    global $wp_styles;

    if (!is_admin() && wp_is_mobile()) {
        $allowed_styles_mobile = [
            'header-mobile', 'footer-mobile', 'custom-category-slider-mobile', 'bottom-bar',
            'desktophide', 'products', 'loadmore', 'menu-mobile', 'search-mobile', 'cat-archive',
            'catproduct', 'filter-mobile', 'filter-inside-mobile', 'cat-hide-mobile', 'paggination-mobile',
            'coupon-mobile', 'single-product-mobile', 'header-product', 'fixed-addtocart-mobile',
            'sticky-header.css', 'checkout-extra', 'offer-crusal', 'pro-crusal', 'cat-crusal', 'slider', 'crusal', 'countdown', 'front', 'msg91', 'login2', 'myaccount', 'product-view', 'product-description-tab', 'product-review', 'product-share', 'product-safety-icon', 'wishlist-notice', 'product-est-delivery', 'product-size-view', 'product-wishlist', 'pop-up-addtocart', 'product-description', 'product-title', 'order-wrap', 'checkout-shipping-wrap', 'order-view', 'checkout-billing',  'size-mobile', 'order-bar', 'comment', 'contact-info', 'sidebar-mobile', 'cart-mobile', 'notice',
            'checkout-mobile'
        ];

        foreach ($wp_styles->queue as $handle) {
            if (!in_array($handle, $allowed_styles_mobile)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'remove_all_css_except_custom_mobile', PHP_INT_MAX);

function enqueue_custom_styles_mobile() {
    if (wp_is_mobile()) {
        $is_product_search = isset($_GET['s']) && isset($_GET['post_type']) && $_GET['post_type'] === 'product';
      
    
  
        wp_enqueue_style('desktophide', 'https://mellmon.in/css2/desktop-hide.css', array(), '1.0010.0');
        
  
      
     if (is_account_page()) {
            wp_enqueue_style('header-mobile', 'https://mellmon.in/css2/mobile/header-mobile.css', array(), '97488777977.2.0');
            wp_enqueue_style('menu-mobile', 'https://mellmon.in/css2/mobile/menu-mobile.css', array(), '979.097.0');
            wp_enqueue_style('myaccount', 'https://mellmon.in/css2/myaccount.css', array(), '232699999555909000055500090443888374.979900989797.9990');
            
         
     
     }  
      

       
      
      // Add login.css only if user is logged in
        if (is_user_logged_in()) {
            wp_enqueue_style('login-mobile', 'https://mellmon.in/css2/login.css', array(), '199511.0.0');
        }
      
      
      
        
        

        if (is_front_page() || is_home() || is_page(['men', 'women', 'kids'])) {
          
     
          
           wp_enqueue_style('offer-crusal', 'https://mellmon.in/wp-content/plugins/offers-carousel/assets/css/frontend.css', array(), '187999980.0');
  
  
          
          
          
          wp_enqueue_style('pro-crusal', 'https://mellmon.in/wp-content/plugins/product-carousel/assets/css/frontend.css', array(), '879980.0');
          
          
          
          
          wp_enqueue_style('cat-crusal', 'https://mellmon.in/wp-content/plugins/category-grid/assets/css/carousel.css', array(), '879980.0');
          
          wp_enqueue_style('crusal', 'https://mellmon.in/wp-content/plugins/banner-carousel/assets/css/carousel.css', array(), '879980.0');
          
          wp_enqueue_style('countdown', 'https://mellmon.in/wp-content/plugins/countdown/css/wc-countdown-timer.css', array(), '8766669980.0');
        
          wp_enqueue_style('slider', 'https://mellmon.in/wp-content/plugins/custom-category-slider/includes/assets/css/slider.css', array(), '12777388770099830.0');
          
           wp_enqueue_style('front', 'https://mellmon.in/css2/front.css', array(), '495599.6606877777660880.0');
           wp_enqueue_style('login2', 'https://mellmon.in/css2/mobile/login.css', array(), '8432663498666856799888992537.3.0');
          
            wp_enqueue_style('header-mobile', 'https://mellmon.in/css2/mobile/header-mobile.css', array(), '9788977.2.0');
       
           
            wp_enqueue_style('coupon-mobile', 'https://mellmon.in/css2/coupon.css', array(), '2.00.0');
            wp_enqueue_style('search-mobile', 'https://mellmon.in/css2/mobile/search-mobile.css', array(), '62.677779006669999099668807789000.21');
            wp_enqueue_style('menu-mobile', 'https://mellmon.in/css2/mobile/menu-mobile.css', array(), '979.097.0');
          
            wp_enqueue_style('sidebar-mobile', 'https://mellmon.in/css2/mobile/sidebar-mobile.css', array(), '498555555888579966699997753123337.54.99986');

            wp_enqueue_style('loadmore', 'https://mellmon.in/css2/loadmore.css', array(), '96917.0899909.0');
          
            wp_enqueue_style('bottom-bar', 'https://mellmon.in/css2/mobile/bottom-bar.css', array(), '3.77771110709.0');
            wp_enqueue_style('wishlist-notice', 'https://mellmon.in/css2/wishlist-notice.css', array(), '3.0079.0');
            wp_enqueue_style('footer-mobile', 'https://mellmon.in/css2/mobile/footer-mobile.css', array(), '4.588.0');
            
        }

        if (is_product_category() || $is_product_search) {
          
           wp_enqueue_style('catproduct', 'https://mellmon.in/css2/catproduct.css', array(), '12.97885656569999988779');
          
          
            wp_enqueue_style('header-mobile', 'https://mellmon.in/css2/mobile/header-mobile.css', array(), '7887770064.2.0');
            wp_enqueue_style('menu-mobile', 'https://mellmon.in/css2/mobile/menu-mobile.css', array(), '979.097.0');
            wp_enqueue_style('filter-inside-mobile', 'https://mellmon.in/css2/mobile/f.css', array(), '790500990000009901999234.98799.0');
            wp_enqueue_style('paggination-mobile', 'https://mellmon.in/css2/mobile/paggination-mobile.css', array(), '18999.100.0');
            wp_enqueue_style('sidebar-mobile', 'https://mellmon.in/css2/mobile/sidebar-mobile.css', array(), '7995599999.656646.86');
            wp_enqueue_style('breadcum', 'https://mellmon.in/css2/breadcum.css', array(), '1.000.0');
            wp_enqueue_style('search-mobile', 'https://mellmon.in/css2/mobile/search-mobile.css', array(), '2.089000.21');
            wp_enqueue_style('coupon-mobile', 'https://mellmon.in/css2/coupon.css', array(), '2.00.0');
            wp_enqueue_style('wishlist-notice', 'https://mellmon.in/css2/wishlist-notice.css', array(), '3.009.0');
            wp_enqueue_style('cat-archive', 'https://mellmon.in/css2/catproduct.css', array(), '36764.959970.0');
            wp_enqueue_style('cat-hide-mobile', 'https://mellmon.in/css2/mobile/cat-hide-mobile.css', array(), '3.999779.0');
            wp_enqueue_style('bottom-bar', 'https://mellmon.in/css2/mobile/bottom-bar.css', array(), '3.7117771009.0');
            wp_enqueue_style('footer-mobile', 'https://mellmon.in/css2/mobile/footer-mobile.css', array(), '4.588.0');
        }

        if (is_product()) {
          
          
            wp_enqueue_style('single-product-mobile', 'https://mellmon.in/css2/mobile/single-product-mobile.css', array(), '12.701.9099');
            wp_enqueue_style('header-product', 'https://mellmon.in/css2/mobile/header-product.css', array(), '1111859999973811183.117199`171119.0');
            
            wp_enqueue_style('coupon-mobile', 'https://mellmon.in/css2/coupon.css', array(), '2.00.0');

            wp_enqueue_style('comment', 'https://mellmon.in/css2/mobile/comment.css', array(), '2.8623300.0');
          
          
            wp_enqueue_style('single-product-mobile', 'https://mellmon.in/css2/SingleProduct/gallery.css', array(), '565777790.100.0');
          wp_enqueue_style('wishlist-notice', 'https://mellmon.in/css2/wishlist-notice.css', array(), '3.009.0');
                wp_enqueue_style('search-mobile', 'https://mellmon.in/css2/mobile/search-mobile.css', array(), '2.089000.21');
            wp_enqueue_style('sidebar-mobile', 'https://mellmon.in/css2/mobile/sidebar-mobile.css', array(), '7668446664000794488996699.66866656646.86');
         
          
          
       
          
          
            wp_enqueue_style('product-title', 'https://mellmon.in/css2/mobile/singleproduct/p.css', array(), '99889666912319995566667779996677655599999095519000879374.100.0');
           
           wp_enqueue_style('size-mobile', 'https://mellmon.in/css2/mobile/size-mobile.css', array(), '129448444669.99910990.0');
          
          
          wp_enqueue_style('fixed-addtocart-mobile', 'https://mellmon.in/css2/mobile/fixed-addtocart-mobile.css', array(), '79898.1006.0');
          
                    
          
            wp_enqueue_style('footer-mobile', 'https://mellmon.in/css2/mobile/footer-mobile.css', array(), '89984.588.0');
          
        }

        if (is_cart()) {
            wp_enqueue_style('header-mobile', 'https://mellmon.in/css2/mobile/header-mobile.css', array(), '2.899985552.0');
            wp_enqueue_style('notice', 'https://mellmon.in/css2/notice.css', array(), '1.0899000.0');
            wp_enqueue_style('bottom-bar', 'https://mellmon.in/css2/mobile/bottom-bar.css', array(), '3.7115551009.0');
            wp_enqueue_style('search-mobile', 'https://mellmon.in/css2/mobile/search-mobile.css', array(), '2.0895555000.21');
            wp_enqueue_style('cart-mobile', 'https://mellmon.in/css2/mobile/cart-mobile.css', array(), '87845456885555599881566.2.0');
            wp_enqueue_style('menu-mobile', 'https://mellmon.in/css2/mobile/menu-mobile.css', array(), '88979.097.0');
            wp_enqueue_style('footer-mobile', 'https://mellmon.in/css2/mobile/footer-mobile.css', array(), '4.588.0');
        }

           if (is_checkout() && !is_wc_endpoint_url('order-received')) {
            // Only load these styles on checkout page EXCEPT order-received page
            wp_enqueue_style('checkout-mobile', 'https://mellmon.in/css2/mobile/checkout/check-one.css', array(), '149800088987999999999994975599899999889967.68.0');
            wp_enqueue_style('order-wrap', 'https://mellmon.in/css2/mobile/checkout/order-wrap.css', array(), '1039955777666666999.121367999.28');
            wp_enqueue_style('checkout-extra', 'https://mellmon.in/css2/mobile/checkout/addextra.css', array(), '900998999.9999.28');
        }

        if (is_wc_endpoint_url('order-received')) {
            // Custom styles for order-received page
            wp_enqueue_style('header-mobile', 'https://mellmon.in/css2/mobile/header-mobile.css', array(), '9788977.2.0');
            wp_enqueue_style('footer-mobile', 'https://mellmon.in/css2/mobile/footer-mobile.css', array(), '4.588.0');
            // Add any other specific styles you need for the thank you page
        }
    }
}


add_action('wp_enqueue_scripts', 'enqueue_custom_styles_mobile');










function remove_all_css_except_custom() {
    global $wp_styles;

    if (!is_admin() && !wp_is_mobile()) {
        $allowed_styles = [
            'header', 'footer', 'custom-category-slider', 'products', 'loadmore', 'breadcum',
            'filter', 'catproduct', 'search', 'singleproduct', 'sizeopen', 'comment', 'notice',
            'cart-total', 'coupon', 'product-details',  'product-share', 'product-trust', 'product-meta', 'checkout-bac', 'checkout-1', 'sidebar', 'desktophide', 'checkout-hide',
            'pagination', 'cat-archive', 'pro-crusal', 'size-mobile', 'offer-crusal', 'crusal', 'cat-crusal', 'countdown', 'front', 'checkout-2', 'slider', 'msg91', 'login2', 'myaccount', 'login-mobile', 'test', 'product-sold', 'product-title', 'product-price',  'product-view', 'product-delivery', 'product-size-button', 'product-size-select', 'product-short-details',    'wishlist-notice'
        ];

        foreach ($wp_styles->queue as $handle) {
            if (!in_array($handle, $allowed_styles)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'remove_all_css_except_custom', PHP_INT_MAX);

function enqueue_custom_styles() {
    if (!wp_is_mobile()) {
      
         $is_product_search = isset($_GET['s']) && isset($_GET['post_type']) && $_GET['post_type'] === 'product';
      
        
        wp_enqueue_style('wishlist-notice', 'https://mellmon.in/css2/wishlist-notice.css', array(), '1235822388.999.0');
        wp_enqueue_style('login2', 'https://mellmon.in/css2/login2.css', array(), '525665474554443.999323.0');
        wp_enqueue_style('header', 'https://mellmon.in/css2/header.css', array(), '78944886666855666666755577998999985555558.12333323.0');
        wp_enqueue_style('footer', 'https://mellmon.in/css2/footer.css', array(), '1.7889912.0');
      
        wp_enqueue_style('search', 'https://mellmon.in/css2/search.css', array(), '2999974.99997.9990');
        wp_enqueue_style('coupon', 'https://mellmon.in/css2/coupon.css', array(), '28.9.0');
        wp_enqueue_style('notice', 'https://mellmon.in/css2/notice.css', array(), '1.089000.0');
      
        wp_enqueue_style('desktophide', 'https://mellmon.in/css2/desktop-hide.css', array(), '466447751.089900.0');
        wp_enqueue_style('sidebar', 'https://mellmon.in/css2/sidebar.css', array(), '47645188238999.186549');
      
      
      
     
      
       if (is_account_page()) {
       wp_enqueue_style('myaccount', 'https://mellmon.in/css2/myaccount.css', array(), '290000990000666749994479664448888888898884777.99997.9990');
        wp_enqueue_style('footer', 'https://mellmon.in/css2/footer.css', array(), '1.7889912.0');
           wp_enqueue_style('search', 'https://mellmon.in/css2/search.css', array(), '2999974.99997.9990');
           wp_enqueue_style('msg91', 'https://mellmon.in/wp-content/plugins/msg91-woocommerce-otp/assets/msg91-otp.css', array(), '523499999788977.2.0');
         
            
           
        }  
      
      
      
      
    
      
      
        

         // Load CSS only on Homepage, /men, /women, /kids
if (is_front_page() || is_home() || is_page(['men', 'women', 'kids'])) {
  

  wp_enqueue_style('offer-crusal', 'https://mellmon.in/wp-content/plugins/offers-carousel/assets/css/frontend.css', array(), '879980.0');
  
  
  wp_enqueue_style('pro-crusal', 'https://mellmon.in/wp-content/plugins/product-carousel/assets/css/frontend.css', array(), '879980.0');
  
  wp_enqueue_style('cat-crusal', 'https://mellmon.in/wp-content/plugins/category-grid/assets/css/carousel.css', array(), '879980.0');
  
  wp_enqueue_style('crusal', 'https://mellmon.in/wp-content/plugins/banner-carousel/assets/css/carousel.css', array(), '879980.0');
  
wp_enqueue_style('countdown', 'https://mellmon.in/wp-content/plugins/countdown/css/wc-countdown-timer.css', array(), '879980.0');
  wp_enqueue_style('slider', 'https://mellmon.in/wp-content/plugins/custom-category-slider/includes/assets/css/slider.css', array(), '97999999980.0');
  wp_enqueue_style('front', 'https://mellmon.in/css2/front.css', array(), '8678899830.0');
  
    
}
      
      

       if (is_product_category() || $is_product_search) {
            wp_enqueue_style('filter', 'https://mellmon.in/css2/filter.css', array(), '757654948907499.666656650.769990');
          
          
         
            wp_enqueue_style('catproduct', 'https://mellmon.in/css2/catproduct.css', array(), '12.9788565649999988779');
            wp_enqueue_style('breadcum', 'https://mellmon.in/css2/breadcum.css', array(), '1.8686664444482207988077770.0');
            wp_enqueue_style('pagination', 'https://mellmon.in/css2/pagination.css', array(), '1.077777700.0');
          
        }

         if (is_product()) {
           
           
            wp_enqueue_style('size-mobile', 'https://mellmon.in/css2/SingleProduct/size.css', array(), '165499444669.99910990.0');
           
           
           
            wp_enqueue_style('breadcum', 'https://mellmon.in/css2/breadcum.css', array(), '6666991666.0.0');
          
          
          
            wp_enqueue_style('singleproduct', 'https://mellmon.in/css2/SingleProduct/gallery.css', array(), '765776753000155407.1.0');
          
          
          
          
          
         
          
         wp_enqueue_style('product-view', 'https://mellmon.in/css2/SingleProduct/t3.css', array(), '23833855555543554.893000.0');
          
         
           
 
           
           
        
          wp_enqueue_style('product-price', 'https://mellmon.in/css2/SingleProduct/product-price.css', array(), '17.998990.0');
           
           
           
           
          wp_enqueue_style('test', 'https://mellmon.in/css2/SingleProduct/test.css', array(), '79.35.08');
          
          
          
          
          
          
            
      
            wp_enqueue_style('comment', 'https://mellmon.in/css2/comment.css', array(), '1.4543436.0');
           
           
           
           
           
           
        }

      
      
      
      
      
        if (is_cart()) {
            wp_enqueue_style('cart-total', 'https://mellmon.in/css2/cart-total.css', array(), '145555434437.44678594');
        }

         if (is_checkout() && !is_wc_endpoint_url('order-received')) {
            wp_enqueue_style('checkout-bac', 'https://mellmon.in/css2/checkout/ckeckout-desktop.css', array(), '19911.493.81');
            wp_enqueue_style('checkout-1', 'https://mellmon.in/css2/checkout/checkout-forum.css', array(), '7663276876877896428.0');
            wp_enqueue_style('checkout-2', 'https://mellmon.in/css2/checkout/checkout-product.css', array(), '1.86678346683317976.0');
            
    }
           
           if (is_wc_endpoint_url('order-received')) {
            // Custom styles for order-received page
            wp_enqueue_style('order-received-mobile', 'https://mellmon.in/css2/header.css', array(), '1.0.0');
             wp_enqueue_style('footer', 'https://mellmon.in/css2/footer.css', array(), '1.7889912.0');
            // Add any other specific styles you need for the thank you page
        }
    
}
  }
add_action('wp_enqueue_scripts', 'enqueue_custom_styles');















function custom_add_wc_add_to_cart_params() {
    if (class_exists('WooCommerce')) {
        $params = array(
            'ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'i18n_view_cart' => __('View cart', 'woocommerce'),
            'cart_url' => wc_get_cart_url(),
            'is_cart' => is_cart(),
            'cart_redirect_after_add' => get_option('woocommerce_cart_redirect_after_add'),
            'nonce' => wp_create_nonce('add-to-cart'),
        );

        // Add the parameters as an inline script
        echo '<script type="text/javascript">var wc_add_to_cart_params = ' . json_encode($params) . ';</script>';
    }
}
add_action('wp_footer', 'custom_add_wc_add_to_cart_params', 5); // Priority 5 to load before your custom scripts







function remove_all_js_except_woocommerce() {
    global $wp_scripts;

    if (!is_admin()) {
        $allowed_scripts = [
              'wc-cart-fragments',
            
            'msg91-otp-vars',       // ADD THIS
            'msg91-otp-script',
          
          // WooCommerce core script
            // Add other WooCommerce script handles here if needed
        ];

        // List of scripts to remove
        $scripts_to_remove = [
            'jquery-ui-core',
            'jquery-ui-mouse',
            'jquery-ui-slider',
            'js-cookie',
            'jquery-migrate',
            'jquery',
            'wc-jquery-ui-touchpunch',
            'accounting',
            'wc-price-slider',
            'wpb_composer_front_js',
            'gender-tabs-js',
           
        ];

        // Remove unwanted scripts
        foreach ($scripts_to_remove as $script) {
            wp_dequeue_script($script);
            wp_deregister_script($script);
        }

        // Remove all other scripts except allowed ones
        foreach ($wp_scripts->queue as $handle) {
            if (!in_array($handle, $allowed_scripts)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'remove_all_js_except_woocommerce', PHP_INT_MAX);




// Load scripts ONLY on Home, /men, /women, /kids (if they are WP Pages)
function add_custom_scripts_for_gender_pages() {
    if (is_front_page() || is_home() || is_page(array('men', 'women', 'kids'))) {
        // Output inline scripts
        ?>
        <script type="text/javascript" id="wc-add-to-cart-variation-js-extra">
        /* <![CDATA[ */
        var wc_add_to_cart_variation_params = <?php echo json_encode(array(
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "i18n_no_matching_variations_text" => __('Sorry, no products matched your selection. Please choose a different combination.'),
            "i18n_make_a_selection_text" => __('Please select some product options before adding this product to your cart.'),
            "i18n_unavailable_text" => __('Sorry, this product is unavailable. Please choose a different combination.'),
            "i18n_reset_alert_text" => __('Your selection has been reset. Please select some product options before adding this product to your cart.')
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>

        <?php
      
       wp_enqueue_script('offer-crusal', 'https://mellmon.in/wp-content/plugins/offers-carousel/assets/js/frontend.js', array(), '38899998.000', true);
      
        wp_enqueue_script('pro-crusal', 'https://mellmon.in/wp-content/plugins/product-carousel/assets/js/frontend.js', array(), '399998.000', true);
       wp_enqueue_script('cat-banner', 'https://mellmon.in/wp-content/plugins/category-grid/assets/js/frontend.js', array(), '2347999998.000', true);
      wp_enqueue_script('banner', 'https://mellmon.in/wp-content/plugins/banner-carousel/assets/js/carousel.js', array(), '7999998.000', true);
      wp_enqueue_script('countdown', 'https://mellmon.in/wp-content/plugins/countdown/js/wc-countdown-timer.js', array(), '79998.000', true);
        wp_enqueue_script('slider', 'https://mellmon.in/wp-content/plugins/custom-category-slider/includes/assets/js/slider.js', array(), '798.000', true);
      
      
      
        wp_enqueue_script('gender-tabs-js', 'https://mellmon.in/wp-content/themes/elessi-theme-child/js/gender-tabs.js', array(), '788891.000', true);
        wp_enqueue_script('msg1', 'https://mellmon.in/wp-content/plugins/msg91-woocommerce-otp/assets/msg91-otp.js', array(), '9989998851114473833744477744.1.6', true);
        wp_enqueue_script('main', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/main.js', array(), '1.1.6', true);
        wp_enqueue_script('search', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/search.js', array(), '1.8.5', true);
        wp_enqueue_script('variation', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/variations.js', array(), '1.8.7', true);
        wp_enqueue_script('wish-list', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/wish-list.js', array(), '3.00.5', true);
        wp_enqueue_script('mini-cart', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mini-cart.js', array(), '1.6.5', true);
        wp_enqueue_script('pop-up', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/popup.js', array(), '1.8.5', true);
        wp_enqueue_script('load-more', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/loadmore.js', array(), '3.8.5', true);
        wp_enqueue_script('infinity-scroll', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/infinityscroll.js', array(), '1.8.5', true);
        wp_enqueue_script('mobile-menu', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobile-menu.js', array(), '1.8.5', true);
        wp_enqueue_script('mobile-bottom', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobilebottombar.js', array(), '1.8.5', true);
        wp_enqueue_script('footer-click', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/footerclick.js', array(), '2.8.5', true);
        wp_enqueue_script('login', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/login.js', array(), '5555532.68.5', true);
    }
}
add_action('wp_footer', 'add_custom_scripts_for_gender_pages', 10);







/**
 * Add custom scripts manually for the category page
 */
function add_custom_scripts_for_category_page() {
  
   // Check if it's a product search
    $is_product_search = isset($_GET['s']) && isset($_GET['post_type']) && $_GET['post_type'] === 'product';
    // Add scripts only on the category page
    if (is_product_category() || $is_product_search) {
        // Output inline scripts
        ?>
  

        <script type="text/javascript" id="custom-login-js-extra">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajaxurl" => admin_url('admin-ajax.php'),
            "login_nonce" => wp_create_nonce('login_nonce'),
            "register_nonce" => wp_create_nonce('register_nonce')
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>
        <?php

        // Enqueue external scripts for the category page
        wp_enqueue_script('main', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/main.js', array(), '1.7771.6', true);
        wp_enqueue_script('search', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/search.js', array(), '1.8.5', true);
        wp_enqueue_script('variation', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/variations.js', array(), '1.8.7', true);
        wp_enqueue_script('wish-list', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/wish-list.js', array(), '2.8.5', true);
        wp_enqueue_script('mini-cart', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mini-cart.js', array(), '1.6.5', true);
        wp_enqueue_script('pop-up', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/popup.js', array(), '1.8.5', true);
        wp_enqueue_script('filter', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/filter.js', array(), '813008900026.8.5', true);
        wp_enqueue_script('infinity-scroll', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/infinityscroll.js', array(), '1.8.5', true);
        wp_enqueue_script('mobile-menu', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobile-menu.js', array(), '1.8.5', true);
        wp_enqueue_script('mobile-bottom', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobilebottombar.js', array(), '1.8.5', true);
        wp_enqueue_script('footer-click', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/footerclick.js', array(), '1.8.5', true);
      wp_enqueue_script('login', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/login.js', array(), '5555532.68.5', true);
    }
}
add_action('wp_footer', 'add_custom_scripts_for_category_page', 10);


/**
 * Add custom scripts manually for the product page
 */
function add_custom_scripts_for_product_page() {
    // Add scripts only on the product page
    if (is_product()) {
        // Output inline scripts
        ?>
        <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>

 <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>

<script type="text/javascript" id="wc-add-to-cart-variation-js-extra">
        /* <![CDATA[ */
        var wc_add_to_cart_variation_params = <?php echo json_encode(array(
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "i18n_no_matching_variations_text" => __('Sorry, no products matched your selection. Please choose a different combination.'),
            "i18n_make_a_selection_text" => __('Please select some product options before adding this product to your cart.'),
            "i18n_unavailable_text" => __('Sorry, this product is unavailable. Please choose a different combination.'),
            "i18n_reset_alert_text" => __('Your selection has been reset. Please select some product options before adding this product to your cart.')
        )); ?>;
        /* ]]> */
        </script>

        <?php

        // Enqueue external scripts
     
        wp_enqueue_script('main', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/main.js', array(), '16662977786781.777771.67777', true);
        wp_enqueue_script('gallery', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/gallery.js', array(), '277511565.781.6', true);
        wp_enqueue_script('search', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/search.js', array(), '1.8.5', true);
        wp_enqueue_script('variation', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/variations.js', array(), '2.0.7', true);
        wp_enqueue_script('wish-list', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/wish-list.js', array(), '1.8.5', true);
        wp_enqueue_script('mini-cart', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mini-cart.js', array(), '1.6.5', true);
        wp_enqueue_script('pop-up', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/popup.js', array(), '1.8.5', true);
        wp_enqueue_script('single-product', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/single-product-mobile-app.js', array(), '1.1.6', true);
        wp_enqueue_script('size', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/size.js', array(), '1.5', true);
        wp_enqueue_script('tabs', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/tab.js', array(), '1.5', true);
        wp_enqueue_script('read-more', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/readmore.js', array(), '1.6', true);
        wp_enqueue_script('review', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/review.js', array(), '1.7.5', true);
      wp_enqueue_script('login', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/login.js', array(), '5555532.66668.5', true);
      
       wp_enqueue_script('msg1', 'https://mellmon.in/wp-content/plugins/msg91-woocommerce-otp/assets/msg91-otp.js', array(), '17336666677675633333999777744.1.6', true);
      
      
      
       
    }
}
add_action('wp_footer', 'add_custom_scripts_for_product_page', 10);



// Function for cart page scripts
function add_custom_scripts_for_cart_page() {
    if (is_cart()) {
      
        // Output inline scripts
        ?>
        <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>

 <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>


<script type="text/javascript" id="wc-add-to-cart-variation-js-extra">
        /* <![CDATA[ */
        var wc_add_to_cart_variation_params = <?php echo json_encode(array(
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "i18n_no_matching_variations_text" => __('Sorry, no products matched your selection. Please choose a different combination.'),
            "i18n_make_a_selection_text" => __('Please select some product options before adding this product to your cart.'),
            "i18n_unavailable_text" => __('Sorry, this product is unavailable. Please choose a different combination.'),
            "i18n_reset_alert_text" => __('Your selection has been reset. Please select some product options before adding this product to your cart.')
        )); ?>;
        /* ]]> */
        </script>


        <?php
        // Enqueue external scripts for the cart page
        wp_enqueue_script('main', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/main.js', array(), '1.1.6', true);
        wp_enqueue_script('search', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/search.js', array(), '1.8.5', true);
        wp_enqueue_script('wish-list', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/wish-list.js', array(), '1.8.5', true);
        wp_enqueue_script('pop-up', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/popup.js', array(), '1.8.5', true);
        wp_enqueue_script('cart', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/cart.js', array(), '1.96598.5', true);
        wp_enqueue_script('mobile-menu', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobile-menu.js', array(), '1.8.5', true);
        wp_enqueue_script('mobile-bottom', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/mobilebottombar.js', array(), '1.8.5', true);
        wp_enqueue_script('footer-click', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/footerclick.js', array(), '1.8.5', true);
      wp_enqueue_script('login', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/login.js', array(), '5555532.68.5', true);
    }
}
add_action('wp_footer', 'add_custom_scripts_for_cart_page', 10);




/**
 * Add custom scripts manually for the checkout page (excluding order-received)
 */
function add_custom_scripts_for_checkout_page() {
    // Only run on checkout page BUT NOT order-received page
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
        // Output inline scripts for checkout page only
        ?>
        <script type="text/javascript" id="wc-checkout-js-extra">
        /* <![CDATA[ */
        var wc_checkout_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "update_order_review_nonce" => wp_create_nonce('update-order-review'),
            "apply_coupon_nonce" => wp_create_nonce('apply-coupon'),
            "remove_coupon_nonce" => wp_create_nonce('remove-coupon'),
            "option_guest_checkout" => "yes",
            "checkout_url" => home_url('/?wc-ajax=checkout'),
            "is_checkout" => "1",
            "debug_mode" => "1",
            "i18n_checkout_error" => __('There was an error processing your order. Please check for any charges in your payment method and review your <a href="https://mellmon.in/orders/">order history</a> before placing the order again.')
        )); ?>;
        /* ]]> */
        </script>
        <?php
        
        // Enqueue checkout-specific scripts
        wp_enqueue_script('main', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/main.js', array(), '1.1.6', true);
        wp_enqueue_script('checkout-page', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/checkoutforum.js', array(), '226899988888864349261.0.70', true);
        wp_enqueue_script('checkout-coupon', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/ext-mini-cart.js', array(), '1.0.0', true);
       
    }
}
add_action('wp_footer', 'add_custom_scripts_for_checkout_page', 10);

/**
 * Add custom scripts for order received page only
 */
function add_custom_scripts_for_order_received_page() {
    if (is_wc_endpoint_url('order-received')) {
        // Output inline scripts for order-received page
        ?>
        <script type="text/javascript" id="wc-order-received-js-extra">
        /* <![CDATA[ */
        var wc_order_received_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "order_key" => isset($_GET['key']) ? $_GET['key'] : '',
            "order_id" => isset($_GET['order']) ? $_GET['order'] : '',
            "home_url" => home_url('/')
        )); ?>;
        /* ]]> */
        </script>
        <?php
        
        // Enqueue only order-received specific scripts (no main.js)
        wp_enqueue_script('order-received', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.4.0/dist/confetti.browser.min.js', array(), '1.0.0', true);
    }
}
add_action('wp_footer', 'add_custom_scripts_for_order_received_page', 10);









function add_custom_scripts_for_my_account_page() {
    // Check if it's the My Account page (including endpoints)
    if (is_account_page()) {
        // Output inline scripts
        ?>
        <script type="text/javascript" id="wc-my-account-js-extra">
        /* <![CDATA[ */
        var wc_my_account_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "nonce" => wp_create_nonce('my-account-nonce'),
            "home_url" => home_url('/'),
            "logout_url" => wc_logout_url(),
            "current_endpoint" => WC()->query->get_current_endpoint()
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="firebase-init-js-extra">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "login_nonce" => wp_create_nonce('login_nonce'),
            "register_nonce" => wp_create_nonce('register_nonce'),
            "home_url" => home_url('/'),
            "firebase_config" => array(
               
                "authDomain" => "mellmon.firebaseapp.com",
                "projectId" => "mellmon",
                "storageBucket" => "mellmon.appspot.com",
                "messagingSenderId" => "155058095476",
                "appId" => "1:155058095476:web:f77cfcc98435609d10d821",
                "measurementId" => "G-KZR9P1R22K"
            )
        )); ?>;
        /* ]]> */
        </script>

        <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>

 <script type="text/javascript" id="wc-cart-fragments-js-extra">
        /* <![CDATA[ */
        var wc_cart_fragments_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "cart_hash_key" => "wc_cart_hash_3e4baffd850daa22a5da1abe789a359d",
            "fragment_name" => "wc_fragments_3e4baffd850daa22a5da1abe789a359d",
            "request_timeout" => "5000"
        )); ?>;
        /* ]]> */
        </script>

 <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>


<script type="text/javascript" id="wc-add-to-cart-variation-js-extra">
        /* <![CDATA[ */
        var wc_add_to_cart_variation_params = <?php echo json_encode(array(
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "i18n_no_matching_variations_text" => __('Sorry, no products matched your selection. Please choose a different combination.'),
            "i18n_make_a_selection_text" => __('Please select some product options before adding this product to your cart.'),
            "i18n_unavailable_text" => __('Sorry, this product is unavailable. Please choose a different combination.'),
            "i18n_reset_alert_text" => __('Your selection has been reset. Please select some product options before adding this product to your cart.')
        )); ?>;
        /* ]]> */
        </script>


 <script type="text/javascript" id="elessi-functions-js-js-before">
        /* <![CDATA[ */
        var nasa_ajax_params = <?php echo json_encode(array(
            "ajax_url" => admin_url('admin-ajax.php'),
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%')
        )); ?>;
        /* ]]> */
        </script>

<script type="text/javascript" id="wc-add-to-cart-variation-js-extra">
        /* <![CDATA[ */
        var wc_add_to_cart_variation_params = <?php echo json_encode(array(
            "wc_ajax_url" => home_url('/?wc-ajax=%%endpoint%%'),
            "i18n_no_matching_variations_text" => __('Sorry, no products matched your selection. Please choose a different combination.'),
            "i18n_make_a_selection_text" => __('Please select some product options before adding this product to your cart.'),
            "i18n_unavailable_text" => __('Sorry, this product is unavailable. Please choose a different combination.'),
            "i18n_reset_alert_text" => __('Your selection has been reset. Please select some product options before adding this product to your cart.')
        )); ?>;
        /* ]]> */
        </script>


        <?php

        // Enqueue external scripts for My Account page

      
        wp_enqueue_script('msg1', 'https://mellmon.in/wp-content/plugins/msg91-woocommerce-otp/assets/msg91-otp.js', array(), '17336666677675633333999777744.1.6', true);
        wp_enqueue_script('my-account', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/my-account.js', array(), '18655553666999962455555555446688.0.0', true);
  }    wp_enqueue_script('login', 'https://mellmon.in/wp-content/themes/elessi-theme/template-parts/login.js', array(), '5554445532.68.5', true);
      
}
add_action('wp_footer', 'add_custom_scripts_for_my_account_page', 10);








add_filter( 'woocommerce_get_price_html', 'custom_price_message_based_on_url' );

function custom_price_message_based_on_url( $price ) {
    // Check if the current page is a WooCommerce product page
    if ( is_product() ) {
        // Get the product URL
        $product_url = get_permalink();
        
        // Check if the product URL contains '/product/' (indicating a product page)
        if ( strpos( $product_url, '/product/' ) !== false ) {
            // Apply the custom price message
            $custom_text = '<p><strong>(Inclusive of All Taxes + Free Shipping)</strong></p>';
            return $price . $custom_text;
        }
    }
    
    // If not a product page or URL does not contain '/product/', return the original price
    return $price;
}


function add_mrp_before_price($price, $product) {
    if (is_product() || is_singular('product')) {
        // Adding inline CSS to make the font size smaller for the M.R.P: label
        $price = '<span class="mrp-label" style="font-size: 20px;">MRP: </span>' . $price;
    }
    return $price;
}
add_filter('woocommerce_get_price_html', 'add_mrp_before_price', 10, 2);


add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 998);
function theme_enqueue_styles() {
    $prefix = function_exists('elessi_prefix_theme') ? elessi_prefix_theme() : 'elessi';
    wp_enqueue_style($prefix . '-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style($prefix . '-child-style', get_stylesheet_uri());
}

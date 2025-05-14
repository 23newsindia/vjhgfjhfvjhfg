<?php
class PC_Shortcode {
    public function __construct() {
        add_shortcode('product_carousel', [$this, 'render_carousel']);
        add_action('wp_ajax_pc_load_carousel', [$this, 'ajax_load_carousel']);
        add_action('wp_ajax_nopriv_pc_load_carousel', [$this, 'ajax_load_carousel']);
    }

    public function render_carousel($atts) {
        $atts = shortcode_atts(['slug' => ''], $atts);
        
        if (wp_doing_ajax()) {
            return '<div class="pc-carousel-wrapper" data-slug="' . esc_attr($atts['slug']) . '"></div>';
        }
        
        return $this->get_carousel_html($atts['slug']);
    }

    public function ajax_load_carousel() {
        check_ajax_referer('pc_ajax_nonce', 'nonce');
        
        if (empty($_POST['slug'])) {
            wp_send_json_error('No slug provided');
        }
        
        wp_send_json_success([
            'html' => $this->get_carousel_html(sanitize_text_field($_POST['slug']))
        ]);
    }

    protected function get_filtered_products($settings) {
        // Base query arguments
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $settings['products_per_page'],
            'tax_query' => [],
            'meta_query' => [
                [
                    'key' => '_price',
                    'value' => '',
                    'compare' => '!=',
                    'type' => 'NUMERIC'
                ]
            ]
        ];

        // Debug settings
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Product Carousel Settings: ' . print_r($settings, true));
        }

        // Handle category filtering
        if (!empty($settings['category'])) {
            $category_id = absint($settings['category']);
            if ($category_id > 0) {
                $args['tax_query'][] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id,
                    'include_children' => true
                ];
            }
        }

        // Handle discount rule filtering
        if (!empty($settings['discount_rule']) && class_exists('CTD_DB')) {
            $rule = CTD_DB::get_rule($settings['discount_rule']);
            
            if ($rule) {
                $rule_categories = json_decode($rule->categories, true);
                
                if (!empty($rule_categories)) {
                    if (!empty($settings['category'])) {
                        // If a category is selected, find intersection with rule categories
                        if (!in_array($settings['category'], $rule_categories)) {
                            // No intersection, return empty array
                            return [];
                        }
                    } else {
                        // No category selected, use rule categories
                        $args['tax_query'][] = [
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $rule_categories,
                            'operator' => 'IN',
                            'include_children' => true
                        ];
                    }
                }

                // Exclude specific products if defined in the rule
                $excluded_products = json_decode($rule->excluded_products, true);
                if (!empty($excluded_products)) {
                    $args['post__not_in'] = $excluded_products;
                }
            }
        }

        // Set relation if multiple tax queries
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Set ordering
        switch ($settings['order_by']) {
            case 'popular':
                $args['meta_query'][] = [
                    'key' => 'total_sales',
                    'type' => 'NUMERIC'
                ];
                $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
                break;
                
            case 'latest':
                $args['orderby'] = ['date' => 'DESC', 'ID' => 'DESC'];
                break;
                
            case 'rating':
                $args['meta_query'][] = [
                    'key' => '_wc_average_rating',
                    'type' => 'NUMERIC'
                ];
                $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
                break;
                
            case 'price_low':
                $args['meta_query'][] = [
                    'key' => '_price',
                    'type' => 'NUMERIC'
                ];
                $args['orderby'] = ['meta_value_num' => 'ASC', 'date' => 'DESC'];
                break;
                
            case 'price_high':
                $args['meta_query'][] = [
                    'key' => '_price',
                    'type' => 'NUMERIC'
                ];
                $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
                break;
        }

        // Debug query
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Product Carousel Query Args: ' . print_r($args, true));
        }

        $query = new WP_Query($args);

        // Debug results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Product Carousel Found Posts: ' . $query->found_posts);
            if ($query->found_posts > 0) {
                $first_product = wc_get_product($query->posts[0]);
                if ($first_product) {
                    $cats = wc_get_product_category_list($first_product->get_id());
                    error_log('First Product Categories: ' . $cats);
                }
            }
        }

        return $query->posts;
    }

    protected function get_carousel_html($slug) {
        if (empty($slug)) {
            error_log('Carousel Error: No slug provided');
            return '<!-- Carousel Error: No slug provided -->';
        }
        
        $carousel = $this->get_cached_carousel($slug);
        if (!$carousel) {
            error_log('Carousel Error: Carousel not found for slug: ' . $slug);
            return '<!-- Carousel Error: Carousel not found -->';
        }
        
        // Ensure settings is an array
        $settings = [];
        if (is_string($carousel->settings)) {
            $decoded = json_decode($carousel->settings, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings = $decoded;
            } else {
                error_log('Carousel Error: Failed to decode settings JSON: ' . json_last_error_msg());
            }
        } elseif (is_array($carousel->settings)) {
            $settings = $carousel->settings;
        }

        // Debug settings
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Carousel Settings Before Defaults: ' . print_r($settings, true));
        }

        // Default settings
        $default_settings = [
            'desktop_columns' => 5,
            'mobile_columns' => 2,
            'visible_mobile' => 2,
            'products_per_page' => 10,
            'order_by' => 'popular',
            'category' => '',
            'discount_rule' => ''
        ];
        
        // Merge with defaults and ensure proper types
        $settings = wp_parse_args($settings, $default_settings);
        $settings['category'] = !empty($settings['category']) ? absint($settings['category']) : '';
        $settings['discount_rule'] = !empty($settings['discount_rule']) ? absint($settings['discount_rule']) : '';

        // Debug final settings
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Carousel Final Settings: ' . print_r($settings, true));
        }

        $products = $this->get_filtered_products($settings);
        if (empty($products)) {
            return '<!-- No products found -->';
        }

        ob_start();
        ?>
        <div class="pc-carousel-wrapper"
             data-slug="<?php echo esc_attr($slug); ?>"
             data-columns="<?php echo esc_attr($settings['desktop_columns']); ?>"
             data-mobile-columns="<?php echo esc_attr($settings['mobile_columns']); ?>"
             data-visible-mobile="<?php echo esc_attr($settings['visible_mobile']); ?>">
            
            <div class="pc-carousel-container">
                <?php foreach ($products as $product) : ?>
                    <?php 
                    $product = wc_get_product($product);
                    if ($product) {
                        echo $this->render_product($product);
                    }
                    ?>
                <?php endforeach; ?>
            </div>
            
            <button class="pc-carousel-prev" aria-label="<?php esc_attr_e('Previous', 'product-carousel'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button class="pc-carousel-next" aria-label="<?php esc_attr_e('Next', 'product-carousel'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function render_product($product) {
        if (!is_a($product, 'WC_Product')) {
            return '';
        }

        $product_fit = get_post_meta($product->get_id(), '_product_fit', true) ?: '';
        $fabric_type = get_post_meta($product->get_id(), '_fabric_type', true) ?: '100% COTTON';
        $product_rating = $product->get_average_rating();
        $product_tag = get_post_meta($product->get_id(), '_product_tag', true) ?: 'BEWAKOOF BIRTHDAY BASH';
        $product_categories = wc_get_product_category_list($product->get_id(), ', ', '<span class="brand-name">', '</span>');
        
        $matching_rule = $this->get_matching_discount_rule($product);
        
        ob_start();
        ?>
        <div <?php wc_product_class('product-item', $product); ?>>
            <div class="product-img-wrap">
                <?php if ($matching_rule) : ?>
                    <div class="discount-badge">
                        <?php echo esc_html($matching_rule->name); ?>
                    </div>
                <?php endif; ?>

                <a href="<?php echo esc_url($product->get_permalink()); ?>" class="product-link" aria-label="<?php echo esc_attr($product->get_name()); ?>">
                    <?php echo $product->get_image('woocommerce_thumbnail', [
                        'class' => 'product-img', 
                        'loading' => 'lazy',
                        'alt' => $product->get_name()
                    ]); ?>
                    
                    <?php if ($product_rating > 0) : ?>
                        <div class="rating-badge">
                            <div class="rating-inner">
                                <div class="star-wrapper">
                                    <div class="star-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12">
                                            <path fill="currentColor" d="M5.58 1.15a.5.5 0 0 1 .84 0l1.528 2.363a.5.5 0 0 0 .291.212l2.72.722a.5.5 0 0 1 .26.799L9.442 7.429a.5.5 0 0 0-.111.343l.153 2.81a.5.5 0 0 1-.68.493L6.18 10.063a.5.5 0 0 0-.36 0l-2.625 1.014a.5.5 0 0 1-.68-.494l.153-2.81a.5.5 0 0 0-.11-.343L.781 5.246a.5.5 0 0 1 .26-.799l2.719-.722a.5.5 0 0 0 .291-.212L5.58 1.149Z"/>
                                        </svg>
                                    </div>
                                    <span class="rating-value"><?php echo number_format($product_rating, 1); ?></span>
                                </div>
                                <span class="rating-tag"><?php echo esc_html($product_tag); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </a>
            </div>

            <div class="product-info">
                <div class="product-info-container">
                    <div class="brand-row">
                        <?php echo $product_categories; ?>
                        
                        <button class="wishlist-btn" aria-label="<?php esc_attr_e('Add to wishlist', 'product-carousel'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 18">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 16.561S1.5 11.753 1.5 5.915c0-1.033.354-2.033 1.002-2.83a4.412 4.412 0 0 1 2.551-1.548 4.381 4.381 0 0 1 2.944.437A4.449 4.449 0 0 1 10 4.197a4.449 4.449 0 0 1 2.002-2.223 4.381 4.381 0 0 1 2.945-.437 4.412 4.412 0 0 1 2.551 1.547 4.492 4.492 0 0 1 1.002 2.83c0 5.839-8.5 10.647-8.5 10.647Z"/>
                            </svg>
                        </button>
                    </div>

                    <h2 class="product-title">
                        <a href="<?php echo esc_url($product->get_permalink()); ?>" class="product-title-link">
                            <?php echo esc_html($product->get_name()); ?>
                        </a>
                    </h2>

                    <div class="price-container">
                        <?php
                        $regular_price = $product->get_regular_price();
                        $sale_price = $product->get_sale_price();
                        $current_price = $product->get_price();
                        ?>
                        <span class="current-price">₹<?php echo $current_price; ?></span>
                        <?php if ($regular_price && $regular_price > $current_price) : ?>
                            <span class="original-price">₹<?php echo $regular_price; ?></span>
                            <span class="discount"><?php echo round((($regular_price - $current_price) / $regular_price) * 100); ?>% off</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($fabric_type) : ?>
                        <div class="fabric-tag"><?php echo esc_html($fabric_type); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function get_cached_carousel($slug) {
        $transient_key = 'pc_carousel_' . md5($slug);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $carousel = PC_DB::get_carousel($slug);
        set_transient($transient_key, $carousel, HOUR_IN_SECONDS);
        
        return $carousel;
    }

    protected function get_matching_discount_rule($product) {
        if (!class_exists('CTD_DB')) return null;
        
        $rules = CTD_DB::get_all_rules();
        $product_id = $product->get_id();
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        
        foreach ($product_cats as $cat_id) {
            $ancestors = get_ancestors($cat_id, 'product_cat');
            $product_cats = array_merge($product_cats, $ancestors);
        }
        $product_cats = array_unique($product_cats);

        foreach ($rules as $rule) {
            $rule_categories = is_string($rule->categories) ? json_decode($rule->categories, true) : [];
            $excluded_products = is_string($rule->excluded_products) ? json_decode($rule->excluded_products, true) : [];
            
            if (in_array($product_id, $excluded_products)) continue;
            
            if (array_intersect($product_cats, $rule_categories)) {
                return $rule;
            }
        }
        
        return null;
    }
}
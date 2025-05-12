
<?php
/**
 * Template Name: WooCommerce Homepage
 */
get_header();
?>

<div class="woocommerce-homepage">
    <!-- Gender Tabs Container -->
    <div id="gender-tabs-container" class="home-gender-wrapper">
        <input type="hidden" id="gender-tabs-nonce" value="<?php echo wp_create_nonce('gender_tabs_nonce'); ?>">
        
        <!-- Men Tab -->
        <div class="comboMenu" style="padding: 0px;">
            <a href="<?php echo esc_url(home_url('/women')); ?>" 
               class="tab-btn pt-1 pb-1 women" 
               data-gender="women">
                Women
                <span class="ripple"></span>
            </a>
        </div>
        
        <!-- Women Tab -->
        <div class="comboMenu" style="padding: 0px;">
            <a href="<?php echo esc_url(home_url('/men')); ?>" 
               class="tab-btn pt-1 pb-1 men" 
               data-gender="men">
                Men
                <span class="ripple"></span>
            </a>
        </div>
        
        <!-- Kids Tab -->
        <div class="comboMenu" style="padding: 0px;">
            <a href="<?php echo esc_url(home_url('/kids')); ?>" 
               class="tab-btn pt-1 pb-1 kids" 
               data-gender="kids">
                kids
                <span class="ripple"></span>
            </a>
        </div>
    </div>
    
      <div id="woocommerce-gender-content" class="woocommerce-gender-content">
        <?php get_template_part('template-parts/content', 'men'); ?>
    </div>
</div>

<?php get_footer(); ?>

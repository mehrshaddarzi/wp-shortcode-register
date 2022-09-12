<div class="wc-register-shortcode <?php echo $attributes['class']; ?>">
    <?php
    if (isset($_GET['_form_notice'])) {
        ?>
        <div class="alert alert-danger" role="alert">
            <?php echo trim($_GET['_form_notice']); ?>
        </div>
        <?php
    }
    ?>

    <form action="<?php echo get_site_url(); ?>" method="GET">
        <?php wp_nonce_field('wc-register-shortcode', 'wc-register-shortcode'); ?>
        <input type="hidden" name="wc-products" value="<?php echo trim($attributes['products']); ?>">
        <input type="hidden" name="wc-redirect" value="<?php echo trim($attributes['redirect']); ?>">
        <input type="hidden" name="wc-page" value="<?php echo trim(WP_SHORTCODE_REGISTER::currentUrl()); ?>">

        <div class="wc-register-shortcode__fullname_title mb-1">
            <?php echo $attributes['fullname']; ?>
        </div>
        <div class="wc-register-shortcode__fullname_input mb-3">
            <input class="form-control p-2"
                   name="wc-fullname"
                   style="direction: rtl; text-align: right;"
                   required>
        </div>
        <div class="wc-register-shortcode__mobile_title mb-1">
            <?php echo $attributes['mobile']; ?>
        </div>
        <div class="wc-register-shortcode__mobile_input mb-3">
            <input class="form-control p-2"
                   name="wc-mobile"
                   placeholder="09xxxxxxxxx"
                   style="direction: ltr; text-align: left;"
                   required>
        </div>
        <div class="wc-register-shortcode__button_input">
            <input type="submit" class="btn btn-success" value="<?php echo $attributes['button']; ?>">
        </div>
    </form>
</div>
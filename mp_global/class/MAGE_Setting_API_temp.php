<?php
function callback_raw_html($args) {
    if (!empty($args['desc'])) {
        ?>
        <i class="info_text">
            <span class="fas fa-info-circle"></span>
            <?php echo esc_html($args['desc']); ?>
        </i>
        <?php
    }
    
    if (!empty($args['value'])) {
        echo $args['value'];
    }
}
?> 
<?php
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Checkout_Fields_Settings')) {
    class MPTBM_Checkout_Fields_Settings {
        public function __construct() {
            add_action('admin_menu', array($this, 'add_settings_page'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        public function add_settings_page() {
            add_submenu_page(
                'edit.php?post_type=mptbm_rent',
                __('Checkout Fields', 'ecab-taxi-booking-manager'),
                __('Checkout Fields', 'ecab-taxi-booking-manager'),
                'manage_options',
                'mptbm_checkout_fields',
                array($this, 'settings_page_html')
            );
        }

        public function register_settings() {
            register_setting('mptbm_checkout_fields_group', 'mptbm_checkout_fields_settings');
        }

        public function enqueue_scripts() {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style('mptbm-admin-checkout-fields', plugins_url('admin-checkout-fields.css', __FILE__));
        }

        public function settings_page_html() {
            if (!current_user_can('manage_options')) {
                return;
            }
            $is_pro = class_exists('MPTBM_Plugin_Pro');
            $default_fields = MPTBM_Function::get_default_checkout_fields();
            $settings = get_option('mptbm_checkout_fields_settings', array());
            $fields = isset($settings['fields']) ? $settings['fields'] : array();
            // Ensure all default fields are present
            foreach ($default_fields as $key => $field) {
                if (!isset($fields[$key])) {
                    $fields[$key] = array_merge($field, array('id' => $key, 'type' => $field['type'], 'default' => true));
                } else {
                    $fields[$key] = array_merge($field, $fields[$key], array('id' => $key, 'default' => true));
                }
            }
            // Sort fields by 'order' if set
            uasort($fields, function($a, $b) {
                return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            });
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('mptbm_checkout_fields_save', 'mptbm_checkout_fields_nonce')) {
                $new_fields = array();
                $order = 0;
                $delete_id = isset($_POST['delete_field']) ? sanitize_text_field($_POST['delete_field']) : '';
                $show_arr = isset($_POST['show']) ? $_POST['show'] : array();
                $required_arr = isset($_POST['required']) ? $_POST['required'] : array();
                $conditional_fields = $_POST['conditional_field'] ?? array();
                $conditional_operators = $_POST['conditional_operator'] ?? array();
                $conditional_values = $_POST['conditional_value'] ?? array();
                if (isset($_POST['field_id']) && is_array($_POST['field_id'])) {
                    foreach ($_POST['field_id'] as $i => $id) {
                        $is_default = isset($fields[$id]['default']) && $fields[$id]['default'];
                        // If deleting, skip this field
                        if ($delete_id && $delete_id === $id && !$is_default) {
                            continue;
                        }
                        $show = isset($show_arr[$i]) ? 1 : 0;
                        $required = isset($required_arr[$i]) ? 1 : 0;
                        $new_fields[$id] = array(
                            'id' => $id,
                            'type' => sanitize_text_field($_POST['type'][$i]),
                            'label' => sanitize_text_field($_POST['label'][$i]),
                            'placeholder' => sanitize_text_field($_POST['placeholder'][$i]),
                            'show' => $show,
                            'required' => $required,
                            'order' => $order++,
                            'default' => $is_default,
                            'conditional' => array(
                                'field' => sanitize_text_field($conditional_fields[$i] ?? ''),
                                'operator' => sanitize_text_field($conditional_operators[$i] ?? ''),
                                'value' => sanitize_text_field($conditional_values[$i] ?? ''),
                            ),
                        );
                        if ($is_pro && !$is_default) {
                            $new_fields[$id]['options'] = isset($_POST['options'][$i]) ? sanitize_text_field($_POST['options'][$i]) : '';
                        }
                    }
                }
                // Handle new custom field
                if ($is_pro && !empty($_POST['new_label']) && !$delete_id) {
                    $new_id = 'custom_' . time();
                    $new_fields[$new_id] = array(
                        'id' => $new_id,
                        'type' => sanitize_text_field($_POST['new_type']),
                        'label' => sanitize_text_field($_POST['new_label']),
                        'placeholder' => sanitize_text_field($_POST['new_placeholder']),
                        'show' => isset($_POST['new_show']) ? 1 : 0,
                        'required' => isset($_POST['new_required']) ? 1 : 0,
                        'order' => $order++,
                        'default' => false,
                        'options' => isset($_POST['new_options']) ? sanitize_text_field($_POST['new_options']) : '',
                        'conditional' => array(
                            'field' => '',
                            'operator' => '',
                            'value' => '',
                        ),
                    );
                }
                update_option('mptbm_checkout_fields_settings', array('fields' => $new_fields));
                $fields = $new_fields;
                echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'ecab-taxi-booking-manager') . '</p></div>';
            }
            // Render UI
            echo '<div class="wrap"><h1>' . esc_html__('Checkout Fields Settings', 'ecab-taxi-booking-manager') . '</h1>';
            echo '<form method="post">';
            wp_nonce_field('mptbm_checkout_fields_save', 'mptbm_checkout_fields_nonce');
            echo '<ul id="mptbm-fields-list" class="mptbm-sortable">';
            $field_index = 0;
            // Always sort fields by 'order' before rendering
            uasort($fields, function($a, $b) {
                return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            });
            foreach ($fields as $field) {
                echo '<li class="mptbm-field-row" data-id="' . esc_attr((string)($field['id'] ?? '')) . '">';
                echo '<input type="hidden" name="field_id[]" value="' . esc_attr((string)($field['id'] ?? '')) . '">';
                echo '<input type="hidden" name="type[]" value="' . esc_attr((string)($field['type'] ?? '')) . '">';
                echo '<span class="dashicons dashicons-move"></span> ';
                echo '<strong>' . esc_html((string)($field['label'] ?? '')) . '</strong> (' . esc_html((string)($field['type'] ?? '')) . ') ';
                echo '<label><input type="checkbox" name="show['.$field_index.']" value="1"' . checked(($field['show'] ?? 0), 1, false) . '> ' . esc_html__('Show', 'ecab-taxi-booking-manager') . '</label> ';
                echo '<label><input type="checkbox" name="required['.$field_index.']" value="1"' . checked(($field['required'] ?? 0), 1, false) . '> ' . esc_html__('Required', 'ecab-taxi-booking-manager') . '</label> ';
                echo '<input type="text" name="label[]" value="' . esc_attr((string)($field['label'] ?? '')) . '" placeholder="Label" style="width:180px;"> ';
                echo '<input type="text" name="placeholder[]" value="' . esc_attr((string)($field['placeholder'] ?? '')) . '" placeholder="Placeholder" style="width:180px;"> ';
                if ($is_pro && !($field['default'] ?? false) && in_array(($field['type'] ?? ''), array('select', 'checkbox', 'radio'))) {
                    echo '<input type="text" name="options[]" value="' . esc_attr((string)($field['options'] ?? '')) . '" placeholder="Options (comma separated)" style="width:180px;"> ';
                } else if ($is_pro && !($field['default'] ?? false)) {
                    echo '<input type="hidden" name="options[]" value="">';
                }
                // Conditional Logic UI
                $conditional = $field['conditional'] ?? array('field'=>'','operator'=>'','value'=>'');
                echo '<button type="button" class="button mptbm-conditional-btn" onclick="jQuery(this).next().toggle();return false;">' . esc_html__('Conditional Logic', 'ecab-taxi-booking-manager') . '</button>';
                echo '<div class="mptbm-conditional-logic" style="display:none;margin-top:8px;">';
                echo '<label>' . esc_html__('Show this field if', 'ecab-taxi-booking-manager') . ' ';
                echo '<select name="conditional_field[]">';
                echo '<option value="">' . esc_html__('Select Field', 'ecab-taxi-booking-manager') . '</option>';
                foreach ($fields as $parent) {
                    if ($parent['id'] !== $field['id']) {
                        echo '<option value="' . esc_attr((string)($parent['id'] ?? '')) . '"' . selected($conditional['field'] ?? '', $parent['id'], false) . '>' . esc_html((string)($parent['label'] ?? '')) . '</option>';
                    }
                }
                echo '</select> ';
                echo '<select name="conditional_operator[]">';
                $ops = array('equals'=>'=','not_equals'=>'≠','empty'=>'is empty','not_empty'=>'is not empty');
                foreach ($ops as $op_key=>$op_label) {
                    echo '<option value="' . esc_attr($op_key) . '"' . selected($conditional['operator'] ?? '', $op_key, false) . '>' . esc_html($op_label) . '</option>';
                }
                echo '</select> ';
                echo '<input type="text" name="conditional_value[]" value="' . esc_attr((string)($conditional['value'] ?? '')) . '" placeholder="' . esc_attr__('Value', 'ecab-taxi-booking-manager') . '" style="width:100px;"> ';
                echo '</label>';
                echo '</div>';
                if ($is_pro && !($field['default'] ?? false)) {
                    echo '<button type="submit" name="delete_field" value="' . esc_attr((string)($field['id'] ?? '')) . '" class="button button-small mptbm-delete-field">' . esc_html__('Delete', 'ecab-taxi-booking-manager') . '</button>';
                }
                echo '</li>';
                $field_index++;
            }
            echo '</ul>';
            echo '<script>
            jQuery(function($){
                $("#mptbm-fields-list").sortable({handle:".dashicons-move"});
                $(".mptbm-delete-field").on("click", function(e){
                    if(!confirm("Are you sure you want to delete this field?")){
                        e.preventDefault();
                        return false;
                    }
                });
                // Highlight new field row on add
                $("form").on("submit", function(){
                    $(".mptbm-field-row").removeClass("mptbm-new-row");
                    var lastRow = $(".mptbm-field-row").last();
                    if(lastRow.length) lastRow.addClass("mptbm-new-row");
                });
            });
            </script>';
            if ($is_pro) {
                // Add new field form
                echo '<h2>' . esc_html__('Add New Field', 'ecab-taxi-booking-manager') . '</h2>';
                echo '<div class="mptbm-add-field">';
                echo '<select name="new_type">';
                foreach (array('text','textarea','number','email','select','checkbox','radio','date','file') as $type) {
                    echo '<option value="' . esc_attr($type) . '">' . esc_html(ucfirst($type)) . '</option>';
                }
                echo '</select> ';
                echo '<input type="text" name="new_label" placeholder="' . esc_attr__('Label', 'ecab-taxi-booking-manager') . '" style="width:120px;"> ';
                echo '<input type="text" name="new_placeholder" placeholder="' . esc_attr__('Placeholder', 'ecab-taxi-booking-manager') . '" style="width:150px;"> ';
                echo '<input type="text" name="new_options" placeholder="' . esc_attr__('Options (comma separated)', 'ecab-taxi-booking-manager') . '" style="width:180px;"> ';
                echo '<label><input type="checkbox" name="new_show" checked> ' . esc_html__('Show', 'ecab-taxi-booking-manager') . '</label> ';
                echo '<label><input type="checkbox" name="new_required"> ' . esc_html__('Required', 'ecab-taxi-booking-manager') . '</label> ';
                echo '<button type="submit" class="button button-primary">' . esc_html__('Add Field', 'ecab-taxi-booking-manager') . '</button>';
                echo '</div>';
            }
            submit_button(__('Save Settings', 'ecab-taxi-booking-manager'));
            echo '</form></div>';
        }
    }
    new MPTBM_Checkout_Fields_Settings();
} 
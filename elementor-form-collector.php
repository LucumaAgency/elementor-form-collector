<?php
/**
 * Plugin Name: Elementor Form Collector
 * Plugin URI: https://example.com/
 * Description: Detecta todos los formularios de Elementor y muestra sus mensajes configurados
 * Version: 1.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: elementor-form-collector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EFC_VERSION', '1.0.0');
define('EFC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EFC_PLUGIN_URL', plugin_dir_url(__FILE__));

class ElementorFormCollector {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_efc_get_form_messages', array($this, 'ajax_get_form_messages'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Elementor Form Collector', 'elementor-form-collector'),
            __('Form Collector', 'elementor-form-collector'),
            'manage_options',
            'elementor-form-collector',
            array($this, 'render_admin_page'),
            'dashicons-forms',
            30
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_elementor-form-collector' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'efc-admin-style',
            EFC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EFC_VERSION
        );
        
        wp_enqueue_script(
            'efc-admin-script',
            EFC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            EFC_VERSION,
            true
        );
        
        wp_localize_script('efc-admin-script', 'efc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('efc_nonce')
        ));
    }
    
    public function get_all_elementor_forms() {
        global $wpdb;
        
        $forms = array();
        
        $posts = $wpdb->get_results(
            "SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data' 
            AND meta_value LIKE '%form_fields%'",
            ARRAY_A
        );
        
        foreach ($posts as $post) {
            $elementor_data = json_decode($post['meta_value'], true);
            
            if (!$elementor_data) {
                continue;
            }
            
            $post_forms = $this->extract_forms_from_elementor_data($elementor_data, $post['post_id']);
            $forms = array_merge($forms, $post_forms);
        }
        
        return $forms;
    }
    
    private function extract_forms_from_elementor_data($data, $post_id, $forms = array()) {
        foreach ($data as $element) {
            if (isset($element['elType']) && $element['elType'] === 'widget' && 
                isset($element['widgetType']) && $element['widgetType'] === 'form') {
                
                $form_data = array(
                    'id' => $element['id'],
                    'post_id' => $post_id,
                    'post_title' => get_the_title($post_id),
                    'post_url' => get_permalink($post_id),
                    'form_name' => isset($element['settings']['form_name']) ? $element['settings']['form_name'] : 'Sin nombre',
                    'fields' => isset($element['settings']['form_fields']) ? $element['settings']['form_fields'] : array(),
                    'messages' => $this->extract_form_messages($element['settings'])
                );
                
                $forms[] = $form_data;
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $forms = $this->extract_forms_from_elementor_data($element['elements'], $post_id, $forms);
            }
        }
        
        return $forms;
    }
    
    private function extract_form_messages($settings) {
        $messages = array(
            'success_message' => isset($settings['success_message']) ? $settings['success_message'] : 'El mensaje se envió correctamente.',
            'error_message' => isset($settings['error_message']) ? $settings['error_message'] : 'Se ha producido un error.',
            'required_field_message' => isset($settings['required_field_message']) ? $settings['required_field_message'] : 'Este campo es obligatorio.',
            'invalid_message' => isset($settings['invalid_message']) ? $settings['invalid_message'] : 'Hay un campo con información no válida.',
            'email_subject' => isset($settings['email_subject']) ? $settings['email_subject'] : '',
            'email_subject_2' => isset($settings['email_subject_2']) ? $settings['email_subject_2'] : '',
            'email_content' => isset($settings['email_content']) ? $settings['email_content'] : '',
            'email_content_2' => isset($settings['email_content_2']) ? $settings['email_content_2'] : '',
            'email_to' => isset($settings['email_to']) ? $settings['email_to'] : '',
            'email_from' => isset($settings['email_from']) ? $settings['email_from'] : '',
            'email_from_name' => isset($settings['email_from_name']) ? $settings['email_from_name'] : '',
            'email_reply_to' => isset($settings['email_reply_to']) ? $settings['email_reply_to'] : '',
            'redirect_to' => isset($settings['redirect_to']) ? $settings['redirect_to'] : '',
            'custom_messages' => isset($settings['custom_messages']) ? $settings['custom_messages'] : array()
        );
        
        return $messages;
    }
    
    public function ajax_get_form_messages() {
        check_ajax_referer('efc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $post_id = intval($_POST['post_id']);
        $form_id = sanitize_text_field($_POST['form_id']);
        
        global $wpdb;
        
        $elementor_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
            $post_id
        ));
        
        if (!$elementor_data) {
            wp_send_json_error('No se encontraron datos de Elementor');
        }
        
        $data = json_decode($elementor_data, true);
        $form_data = $this->find_form_by_id($data, $form_id);
        
        if ($form_data) {
            wp_send_json_success($form_data);
        } else {
            wp_send_json_error('Formulario no encontrado');
        }
    }
    
    private function find_form_by_id($data, $form_id) {
        foreach ($data as $element) {
            if (isset($element['id']) && $element['id'] === $form_id) {
                return array(
                    'fields' => isset($element['settings']['form_fields']) ? $element['settings']['form_fields'] : array(),
                    'messages' => $this->extract_form_messages($element['settings'])
                );
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $result = $this->find_form_by_id($element['elements'], $form_id);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return false;
    }
    
    public function render_admin_page() {
        $forms = $this->get_all_elementor_forms();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Elementor Form Collector', 'elementor-form-collector'); ?></h1>
            
            <?php if (empty($forms)): ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html__('No se encontraron formularios de Elementor.', 'elementor-form-collector'); ?></p>
                </div>
            <?php else: ?>
                <div class="efc-container">
                    <div class="efc-forms-list">
                        <h2><?php echo esc_html__('Formularios Encontrados', 'elementor-form-collector'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Nombre del Formulario', 'elementor-form-collector'); ?></th>
                                    <th><?php echo esc_html__('Página', 'elementor-form-collector'); ?></th>
                                    <th><?php echo esc_html__('Campos', 'elementor-form-collector'); ?></th>
                                    <th><?php echo esc_html__('Acciones', 'elementor-form-collector'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($forms as $form): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($form['form_name']); ?></strong>
                                            <br>
                                            <small>ID: <?php echo esc_html($form['id']); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url($form['post_url']); ?>" target="_blank">
                                                <?php echo esc_html($form['post_title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo count($form['fields']); ?> campos</td>
                                        <td>
                                            <button class="button button-primary efc-view-messages" 
                                                    data-post-id="<?php echo esc_attr($form['post_id']); ?>"
                                                    data-form-id="<?php echo esc_attr($form['id']); ?>"
                                                    data-form-name="<?php echo esc_attr($form['form_name']); ?>">
                                                <?php echo esc_html__('Ver Mensajes', 'elementor-form-collector'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="efc-messages-modal" class="efc-modal" style="display: none;">
                        <div class="efc-modal-content">
                            <span class="efc-close">&times;</span>
                            <h2 id="efc-modal-title"></h2>
                            <div id="efc-modal-body">
                                <div class="efc-loading">Cargando...</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

function efc_init() {
    ElementorFormCollector::get_instance();
}

add_action('plugins_loaded', 'efc_init');
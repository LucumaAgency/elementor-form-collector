jQuery(document).ready(function($) {
    
    $('.efc-view-messages').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var postId = button.data('post-id');
        var formId = button.data('form-id');
        var formName = button.data('form-name');
        var formType = button.data('form-type');
        
        var badgeClass = formType === 'Elementor' ? 'efc-modal-type-elementor' : 'efc-modal-type-royal-addons';
        var titleHtml = 'Mensajes del formulario: ' + formName + 
                       '<span class="efc-modal-type-badge ' + badgeClass + '">' + formType + '</span>';
        
        $('#efc-modal-title').html(titleHtml);
        $('#efc-modal-body').html('<div class="efc-loading">Cargando...</div>');
        $('#efc-messages-modal').fadeIn(300);
        
        $.ajax({
            url: efc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'efc_get_form_messages',
                post_id: postId,
                form_id: formId,
                nonce: efc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayFormMessages(response.data);
                } else {
                    $('#efc-modal-body').html(
                        '<div class="efc-error-message">Error: ' + response.data + '</div>'
                    );
                }
            },
            error: function() {
                $('#efc-modal-body').html(
                    '<div class="efc-error-message">Error al cargar los mensajes del formulario.</div>'
                );
            }
        });
    });
    
    $('.efc-close, #efc-messages-modal').on('click', function(e) {
        if (e.target === this) {
            $('#efc-messages-modal').fadeOut(300);
        }
    });
    
    $('.efc-modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('#efc-messages-modal').fadeOut(300);
        }
    });
    
    function displayFormMessages(data) {
        var html = '';
        
        // Mostrar tipo de formulario en el contenido también
        if (data.form_type) {
            var typeClass = data.form_type === 'Elementor' ? 'efc-type-elementor' : 'efc-type-royal-addons';
            html += '<div style="margin-bottom: 20px;">';
            html += '<span class="efc-form-type ' + typeClass + '">' + data.form_type + '</span>';
            html += '</div>';
        }
        
        html += '<div class="efc-message-group">';
        html += '<h3>Mensajes de Respuesta</h3>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Mensaje de éxito:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.success_message) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Mensaje de error:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.error_message) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Campo requerido:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.required_field_message) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Campo inválido:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.invalid_message) + '</span>';
        html += '</div>';
        
        if (data.messages.redirect_to) {
            html += '<div class="efc-message-item">';
            html += '<span class="efc-message-label">Redirección después del envío:</span>';
            html += '<span class="efc-message-value">' + escapeHtml(data.messages.redirect_to) + '</span>';
            html += '</div>';
        }
        
        html += '</div>';
        
        html += '<div class="efc-message-group">';
        html += '<h3>Configuración de Email</h3>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Enviar a:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_to) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">De (email):</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_from) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">De (nombre):</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_from_name) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Responder a:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_reply_to) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Asunto del email:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_subject) + '</span>';
        html += '</div>';
        
        html += '<div class="efc-message-item">';
        html += '<span class="efc-message-label">Contenido del email:</span>';
        html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_content) + '</span>';
        html += '</div>';
        
        if (data.messages.email_subject_2) {
            html += '<div class="efc-message-item">';
            html += '<span class="efc-message-label">Asunto del email 2:</span>';
            html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_subject_2) + '</span>';
            html += '</div>';
        }
        
        if (data.messages.email_content_2) {
            html += '<div class="efc-message-item">';
            html += '<span class="efc-message-label">Contenido del email 2:</span>';
            html += '<span class="efc-message-value">' + escapeHtml(data.messages.email_content_2) + '</span>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.fields && data.fields.length > 0) {
            html += '<div class="efc-fields-section">';
            html += '<h3>Campos del Formulario (' + data.fields.length + ')</h3>';
            
            data.fields.forEach(function(field, index) {
                html += '<div class="efc-field-item">';
                html += '<strong>Campo ' + (index + 1) + ': ' + escapeHtml(field.field_label || 'Sin etiqueta') + '</strong>';
                
                html += '<div class="efc-field-detail">';
                html += '<span class="efc-field-detail-label">Tipo:</span>';
                html += '<span class="efc-field-detail-value">' + escapeHtml(field.field_type || 'text') + '</span>';
                html += '</div>';
                
                html += '<div class="efc-field-detail">';
                html += '<span class="efc-field-detail-label">ID:</span>';
                html += '<span class="efc-field-detail-value">' + escapeHtml(field.custom_id || field._id || '') + '</span>';
                html += '</div>';
                
                if (field.placeholder) {
                    html += '<div class="efc-field-detail">';
                    html += '<span class="efc-field-detail-label">Placeholder:</span>';
                    html += '<span class="efc-field-detail-value">' + escapeHtml(field.placeholder) + '</span>';
                    html += '</div>';
                }
                
                if (field.required === 'true' || field.required === true) {
                    html += '<div class="efc-field-detail">';
                    html += '<span class="efc-field-detail-label">Requerido:</span>';
                    html += '<span class="efc-field-detail-value">Sí</span>';
                    html += '</div>';
                }
                
                if (field.field_options) {
                    html += '<div class="efc-field-detail">';
                    html += '<span class="efc-field-detail-label">Opciones:</span>';
                    html += '<span class="efc-field-detail-value">' + escapeHtml(field.field_options) + '</span>';
                    html += '</div>';
                }
                
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        if (data.messages.custom_messages && data.messages.custom_messages.length > 0) {
            html += '<div class="efc-message-group">';
            html += '<h3>Mensajes Personalizados</h3>';
            
            data.messages.custom_messages.forEach(function(msg) {
                html += '<div class="efc-message-item">';
                html += '<span class="efc-message-label">' + escapeHtml(msg.custom_message_key || 'Mensaje') + ':</span>';
                html += '<span class="efc-message-value">' + escapeHtml(msg.custom_message_value || '') + '</span>';
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        $('#efc-modal-body').html(html);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
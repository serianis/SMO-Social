/**
 * SMO Social - Branding Admin JavaScript
 * JavaScript functionality for the white-label branding settings page
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initBrandingAdmin();
    });

    /**
     * Main initialization function
     */
    function initBrandingAdmin() {
        initTabs();
        initColorPickers();
        initFormHandlers();
        initLogoUpload();
        initLicenseActivation();
        initPreviewFunctionality();
        initValidation();
    }

    /**
     * Initialize tab functionality
     */
    function initTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and content
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active').hide();
            
            // Add active class to clicked tab
            $(this).addClass('nav-tab-active');
            
            // Show corresponding content
            var target = $(this).attr('href');
            $(target).addClass('active').fadeIn(300);
            
            // Update URL hash for bookmarking
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        });

        // Check for hash in URL on load
        if (window.location.hash) {
            var target = $('.nav-tab[href="' + window.location.hash + '"]');
            if (target.length) {
                target.trigger('click');
            }
        }
    }

    /**
     * Initialize color picker functionality
     */
    function initColorPickers() {
        // Sync color picker with text input
        $('.color-picker').on('change', function() {
            var color = $(this).val();
            $(this).siblings('.small-text').val(color);
            updateColorPreview($(this), color);
        });
        
        $('.small-text').on('change', function() {
            var color = $(this).val();
            if (isValidColor(color)) {
                $(this).siblings('.color-picker').val(color);
                updateColorPreview($(this), color);
            } else {
                showNotice('Please enter a valid color code (e.g., #0073aa)', 'error');
                $(this).val($(this).siblings('.color-picker').val());
            }
        });

        // Add color previews
        $('.color-picker').each(function() {
            var color = $(this).val();
            $(this).after('<span class="color-preview" style="background-color: ' + color + ';"></span>');
        });
    }

    /**
     * Update color preview element
     */
    function updateColorPreview(element, color) {
        var preview = element.siblings('.color-preview');
        if (preview.length) {
            preview.css('background-color', color);
        }
    }

    /**
     * Initialize form handlers
     */
    function initFormHandlers() {
        $('#smo-branding-form').on('submit', function(e) {
            e.preventDefault();
            saveBrandingSettings();
        });

        // Auto-save functionality (optional)
        var autoSaveTimer;
        $('#smo-branding-form input, #smo-branding-form textarea, #smo-branding-form select').on('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Uncomment the next line if you want auto-save
                // saveBrandingSettings(true);
            }, 2000);
        });
    }

    /**
     * Initialize logo upload functionality
     */
    function initLogoUpload() {
        $('#logo_upload').on('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.match(/^image\//)) {
                    showNotice('Please select a valid image file', 'error');
                    $(this).val('');
                    return;
                }

                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showNotice('File size must be less than 2MB', 'error');
                    $(this).val('');
                    return;
                }

                // Preview the image
                var reader = new FileReader();
                reader.onload = function(e) {
                    showImagePreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    /**
     * Show image preview
     */
    function showImagePreview(src) {
        var preview = $('.current-logo');
        if (preview.length) {
            preview.find('img').attr('src', src);
        } else {
            $('.logo-upload-container').prepend(
                '<div class="current-logo"><img src="' + src + '" style="max-width: 200px; height: auto;"></div>'
            );
        }
    }

    /**
     * Initialize license activation
     */
    function initLicenseActivation() {
        $('#activate_license').on('click', function() {
            var licenseKey = $('#license_key').val().trim();
            
            if (!licenseKey) {
                showNotice('Please enter a license key', 'error');
                return;
            }

            activateLicense(licenseKey);
        });

        $('#license_key').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $('#activate_license').trigger('click');
            }
        });
    }

    /**
     * Initialize preview functionality
     */
    function initPreviewFunctionality() {
        $('#preview_branding').on('click', function() {
            previewBrandingChanges();
        });

        // Real-time preview for color changes
        $('.color-picker, .small-text').on('change', function() {
            debounce(previewColorChanges, 500)();
        });
    }

    /**
     * Initialize form validation
     */
    function initValidation() {
        // URL validation
        $('input[type="url"]').on('blur', function() {
            var url = $(this).val().trim();
            if (url && !isValidUrl(url)) {
                showNotice('Please enter a valid URL', 'error');
                $(this).focus();
            }
        });

        // Logo dimensions validation
        $('#logo_width, #logo_height').on('input', function() {
            var value = parseInt($(this).val());
            var min = parseInt($(this).attr('min'));
            var max = parseInt($(this).attr('max'));
            
            if (value < min || value > max) {
                $(this).addClass('error');
                showNotice('Value must be between ' + min + ' and ' + max, 'error');
            } else {
                $(this).removeClass('error');
            }
        });
    }

    /**
     * Save branding settings via AJAX
     */
    function saveBrandingSettings(isAutoSave) {
        var $form = $('#smo-branding-form');
        var $submitBtn = $('#submit');
        var originalText = $submitBtn.val();
        
        // Show loading state
        $form.addClass('loading');
        $submitBtn.val('Saving...').prop('disabled', true);

        // Collect form data
        var formData = new FormData($form[0]);
        formData.append('action', 'smo_save_branding');
        formData.append('nonce', smoBranding.nonce);

        // Add logo file if uploaded
        var logoFile = $('#logo_upload')[0].files[0];
        if (logoFile) {
            formData.append('logo', logoFile);
        }

        $.ajax({
            url: smoBranding.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $form.removeClass('loading');
                $submitBtn.val(originalText).prop('disabled', false);
                
                if (response.success) {
                    if (!isAutoSave) {
                        showNotice('Branding settings saved successfully!', 'success');
                    }
                } else {
                    showNotice('Error saving settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $form.removeClass('loading');
                $submitBtn.val(originalText).prop('disabled', false);
                showNotice('Network error: ' + error, 'error');
            }
        });
    }

    /**
     * Activate license
     */
    function activateLicense(licenseKey) {
        var $button = $('#activate_license');
        var originalText = $button.text();
        
        $button.text('Activating...').prop('disabled', true);

        $.ajax({
            url: smoBranding.ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_activate_license',
                nonce: smoBranding.nonce,
                license_key: licenseKey
            },
            success: function(response) {
                $button.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Refresh page to show updated license info
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('License activation failed: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                $button.text(originalText).prop('disabled', false);
                showNotice('Network error during license activation', 'error');
            }
        });
    }

    /**
     * Preview branding changes
     */
    function previewBrandingChanges() {
        var colors = {
            primary: $('#primary_color').val(),
            secondary: $('#secondary_color').val(),
            accent: $('#accent_color').val()
        };

        $.ajax({
            url: smoBranding.ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_preview_color_scheme',
                nonce: smoBranding.nonce,
                colors: colors
            },
            success: function(response) {
                // Open preview in new window or modal
                var previewWindow = window.open('', '_blank', 'width=800,height=600');
                previewWindow.document.write(response);
                previewWindow.document.close();
            }
        });
    }

    /**
     * Real-time color preview
     */
    function previewColorChanges() {
        var colors = {
            primary: $('#primary_color').val(),
            secondary: $('#secondary_color').val(),
            accent: $('#accent_color').val()
        };

        // Update CSS custom properties for real-time preview
        var style = $('#live-preview-styles');
        if (!style.length) {
            style = $('<style id="live-preview-styles"></style>').appendTo('head');
        }

        var css = `
            :root {
                --preview-primary-color: ${colors.primary};
                --preview-secondary-color: ${colors.secondary};
                --preview-accent-color: ${colors.accent};
            }
        `;
        style.text(css);
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        // Remove existing notices
        $('.smo-branding-notice').remove();
        
        var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        var notice = $('<div class="notice ' + noticeClass + ' smo-branding-notice is-dismissible"><p>' + message + '</p></div>');
        
        $('.smo-branding-settings').prepend(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);

        // Scroll to notice
        $('html, body').animate({
            scrollTop: notice.offset().top - 100
        }, 300);
    }

    /**
     * Utility functions
     */
    function isValidColor(color) {
        return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color);
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Export functions for external use
     */
    window.SMOBrandingAdmin = {
        showNotice: showNotice,
        saveBrandingSettings: saveBrandingSettings,
        activateLicense: activateLicense
    };

})(jQuery);
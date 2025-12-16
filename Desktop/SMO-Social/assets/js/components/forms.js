/**
 * SMO Social - Unified Forms System
 * Modern form interactions with floating labels, validation, character counters, and keyboard navigation
 *
 * @package SMO_Social
 * @version 1.0.0
 */

(function(window, document) {
    'use strict';

    /**
     * Form System Manager
     */
    class SMOFormSystem {
        constructor() {
            this.forms = new Map();
            this.charCounters = new Map();
            this.validationRules = new Map();
            this.init();
        }

        /**
         * Initialize the form system
         */
        init() {
            this.bindGlobalEvents();
            this.initializeExistingForms();
            this.observeNewForms();
        }

        /**
         * Bind global keyboard and form events
         */
        bindGlobalEvents() {
            // Escape key handling for modals and dropdowns
            document.addEventListener('keydown', this.handleGlobalKeydown.bind(this));

            // Form submission handling
            document.addEventListener('submit', this.handleFormSubmit.bind(this));

            // Input events for real-time validation and counters
            document.addEventListener('input', this.handleInput.bind(this));
            document.addEventListener('change', this.handleChange.bind(this));

            // Focus management
            document.addEventListener('focusin', this.handleFocusIn.bind(this));
            document.addEventListener('focusout', this.handleFocusOut.bind(this));
        }

        /**
         * Handle global keyboard shortcuts
         */
        handleGlobalKeydown(event) {
            // Escape key closes modals and dropdowns
            if (event.key === 'Escape') {
                this.handleEscapeKey(event);
            }

            // Tab order preservation
            if (event.key === 'Tab') {
                this.handleTabNavigation(event);
            }
        }

        /**
         * Handle escape key actions
         */
        handleEscapeKey(event) {
            // Close open modals
            const openModal = document.querySelector('.smo-modal[style*="block"], .smo-modal:not([style*="none"])');
            if (openModal) {
                this.closeModal(openModal);
                event.preventDefault();
                return;
            }

            // Close open dropdowns
            const openDropdown = document.querySelector('.smo-dropdown.open');
            if (openDropdown) {
                this.closeDropdown(openDropdown);
                event.preventDefault();
                return;
            }

            // Clear form validation errors on escape
            const activeElement = document.activeElement;
            if (activeElement && activeElement.closest('.smo-form-field.error')) {
                const formField = activeElement.closest('.smo-form-field');
                if (formField) {
                    this.clearValidationError(formField);
                }
            }
        }

        /**
         * Handle tab navigation for better UX
         */
        handleTabNavigation(event) {
            // Custom tab handling for complex components if needed
            const target = event.target;
            
            // Skip tab navigation on character counters and helper elements
            if (target.classList.contains('smo-character-counter') ||
                target.classList.contains('smo-form-help') ||
                target.classList.contains('smo-form-error')) {
                event.preventDefault();
                target.parentElement.querySelector('input, select, textarea, button')?.focus();
                return;
            }
        }

        /**
         * Handle form submissions
         */
        handleFormSubmit(event) {
            const form = event.target;
            
            // Only handle SMO forms
            if (!form.classList.contains('smo-form')) {
                return;
            }

            // Validate form before submission
            if (!this.validateForm(form)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            // Add loading state
            this.addFormLoadingState(form);
        }

        /**
         * Handle input events for real-time features
         */
        handleInput(event) {
            const target = event.target;
            
            // Character counting
            if (target.matches('textarea[data-maxlength], input[data-maxlength]')) {
                this.updateCharacterCounter(target);
            }

            // Real-time validation
            if (target.matches('.smo-input, .smo-select, .smo-textarea')) {
                this.validateField(target);
            }

            // Floating label logic
            this.updateFloatingLabel(target);
        }

        /**
         * Handle change events
         */
        handleChange(event) {
            const target = event.target;
            
            if (target.matches('.smo-select')) {
                this.updateSelectValidation(target);
            }

            if (target.matches('.smo-checkbox, .smo-radio')) {
                this.updateCheckboxValidation(target);
            }
        }

        /**
         * Handle focus in events
         */
        handleFocusIn(event) {
            const target = event.target;
            
            if (target.matches('.smo-input, .smo-select, .smo-textarea')) {
                this.addFieldFocusState(target);
            }
        }

        /**
         * Handle focus out events
         */
        handleFocusOut(event) {
            const target = event.target;
            
            if (target.matches('.smo-input, .smo-select, .smo-textarea')) {
                this.removeFieldFocusState(target);
            }
        }

        /**
         * Initialize existing forms on page load
         */
        initializeExistingForms() {
            const forms = document.querySelectorAll('.smo-form');
            forms.forEach(form => this.registerForm(form));
        }

        /**
         * Observe for new forms added to DOM
         */
        observeNewForms() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if the added node is a form
                            if (node.classList && node.classList.contains('smo-form')) {
                                this.registerForm(node);
                            }
                            
                            // Check for forms within the added node
                            const forms = node.querySelectorAll?.('.smo-form');
                            if (forms) {
                                forms.forEach(form => this.registerForm(form));
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        /**
         * Register a form with the system
         */
        registerForm(form) {
            if (this.forms.has(form)) {
                return;
            }

            const formData = {
                element: form,
                fields: new Map(),
                validationRules: new Map(),
                charCounters: new Map()
            };

            // Find all form fields
            const fields = form.querySelectorAll('.smo-form-field');
            fields.forEach(field => {
                const input = field.querySelector('.smo-input, .smo-select, .smo-textarea');
                if (input) {
                    formData.fields.set(input, field);
                    
                    // Setup character counter if needed
                    if (this.needsCharacterCounter(input)) {
                        this.setupCharacterCounter(input);
                    }

                    // Setup validation rules
                    this.setupValidationRules(input);
                }
            });

            this.forms.set(form, formData);
            this.enhanceFormAccessibility(form);
        }

        /**
         * Setup character counter for input
         */
        setupCharacterCounter(input) {
            const maxLength = parseInt(input.getAttribute('data-maxlength'));
            if (!maxLength || isNaN(maxLength)) {
                return;
            }

            const field = input.closest('.smo-form-field');
            if (!field) {
                return;
            }

            // Check if counter already exists
            let counter = field.querySelector('.smo-character-counter');
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'smo-character-counter';
                field.appendChild(counter);
            }

            this.charCounters.set(input, { counter, maxLength });
            this.updateCharacterCounter(input);
        }

        /**
         * Update character counter display
         */
        updateCharacterCounter(input) {
            const counterData = this.charCounters.get(input);
            if (!counterData) {
                return;
            }

            const { counter, maxLength } = counterData;
            const currentLength = input.value.length;
            const remaining = maxLength - currentLength;

            counter.textContent = `${currentLength}/${maxLength}`;

            // Update styling based on usage
            counter.classList.remove('warning', 'error');
            if (remaining <= 20) {
                counter.classList.add('error');
            } else if (remaining <= 50) {
                counter.classList.add('warning');
            }

            // Store counter data for validation
            input.setAttribute('data-char-count', currentLength);
            input.setAttribute('data-char-remaining', remaining);
        }

        /**
         * Setup validation rules for input
         */
        setupValidationRules(input) {
            const rules = [];

            // Required field
            if (input.hasAttribute('required')) {
                rules.push({
                    type: 'required',
                    message: 'This field is required'
                });
            }

            // Pattern validation
            const pattern = input.getAttribute('pattern');
            if (pattern) {
                rules.push({
                    type: 'pattern',
                    pattern: new RegExp(pattern),
                    message: 'Invalid format'
                });
            }

            // Email validation
            if (input.type === 'email') {
                rules.push({
                    type: 'email',
                    message: 'Please enter a valid email address'
                });
            }

            // URL validation
            if (input.type === 'url') {
                rules.push({
                    type: 'url',
                    message: 'Please enter a valid URL'
                });
            }

            // Min/Max length
            const minLength = input.getAttribute('minlength');
            if (minLength) {
                rules.push({
                    type: 'minlength',
                    value: parseInt(minLength),
                    message: `Minimum length is ${minLength} characters`
                });
            }

            const maxLength = input.getAttribute('maxlength');
            if (maxLength) {
                rules.push({
                    type: 'maxlength',
                    value: parseInt(maxLength),
                    message: `Maximum length is ${maxLength} characters`
                });
            }

            // Min/Max for numeric inputs
            if (input.type === 'number') {
                const min = input.getAttribute('min');
                if (min) {
                    rules.push({
                        type: 'min',
                        value: parseFloat(min),
                        message: `Minimum value is ${min}`
                    });
                }

                const max = input.getAttribute('max');
                if (max) {
                    rules.push({
                        type: 'max',
                        value: parseFloat(max),
                        message: `Maximum value is ${max}`
                    });
                }
            }

            if (rules.length > 0) {
                this.validationRules.set(input, rules);
            }
        }

        /**
         * Validate individual field
         */
        validateField(input) {
            const rules = this.validationRules.get(input);
            if (!rules) {
                return true;
            }

            const value = input.value.trim();
            const field = input.closest('.smo-form-field');
            
            // Clear previous validation state
            this.clearValidationError(field);

            // Check each rule
            for (const rule of rules) {
                const isValid = this.validateFieldRule(value, rule, input);
                if (!isValid) {
                    this.showValidationError(field, rule.message);
                    return false;
                }
            }

            // Mark as valid if no errors
            this.showValidationSuccess(field);
            return true;
        }

        /**
         * Validate individual rule
         */
        validateFieldRule(value, rule, input) {
            switch (rule.type) {
                case 'required':
                    return value.length > 0;

                case 'pattern':
                    return rule.pattern.test(value);

                case 'email':
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

                case 'url':
                    try {
                        new URL(value);
                        return true;
                    } catch {
                        return false;
                    }

                case 'minlength':
                    return value.length >= rule.value;

                case 'maxlength':
                    return value.length <= rule.value;

                case 'min':
                    return parseFloat(value) >= rule.value;

                case 'max':
                    return parseFloat(value) <= rule.value;

                default:
                    return true;
            }
        }

        /**
         * Show validation error
         */
        showValidationError(field, message) {
            field.classList.remove('success', 'warning');
            field.classList.add('error');

            let errorElement = field.querySelector('.smo-form-error');
            if (!errorElement) {
                errorElement = document.createElement('p');
                errorElement.className = 'smo-form-error';
                field.appendChild(errorElement);
            }

            errorElement.innerHTML = `<span class="icon">⚠️</span>${message}`;
            
            // Add ARIA attributes for accessibility
            const input = field.querySelector('.smo-input, .smo-select, .smo-textarea');
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', errorElement.id || this.generateId());
            }
        }

        /**
         * Show validation success
         */
        showValidationSuccess(field) {
            field.classList.remove('error', 'warning');
            field.classList.add('success');

            let successElement = field.querySelector('.smo-form-success');
            if (!successElement) {
                successElement = document.createElement('p');
                successElement.className = 'smo-form-success';
                field.appendChild(successElement);
            }

            successElement.innerHTML = `<span class="icon">✅</span>Looks good!`;
            
            // Add ARIA attributes
            const input = field.querySelector('.smo-input, .smo-select, .smo-textarea');
            if (input) {
                input.setAttribute('aria-invalid', 'false');
            }
        }

        /**
         * Clear validation error
         */
        clearValidationError(field) {
            field.classList.remove('error', 'success', 'warning');
            
            const input = field.querySelector('.smo-input, .smo-select, .smo-textarea');
            if (input) {
                input.setAttribute('aria-invalid', 'false');
                input.removeAttribute('aria-describedby');
            }

            // Remove error/success messages
            const messages = field.querySelectorAll('.smo-form-error, .smo-form-success');
            messages.forEach(msg => msg.remove());
        }

        /**
         * Update floating label state
         */
        updateFloatingLabel(input) {
            const field = input.closest('.smo-form-field');
            const label = field?.querySelector('.smo-form-label.floating');
            
            if (!label) {
                return;
            }

            if (input.value.trim().length > 0) {
                field.classList.add('has-value');
            } else {
                field.classList.remove('has-value');
            }
        }

        /**
         * Add focus state to field
         */
        addFieldFocusState(input) {
            const field = input.closest('.smo-form-field');
            if (field) {
                field.classList.add('focused');
            }
        }

        /**
         * Remove focus state from field
         */
        removeFieldFocusState(input) {
            const field = input.closest('.smo-form-field');
            if (field) {
                field.classList.remove('focused');
            }
        }

        /**
         * Update select field validation
         */
        updateSelectValidation(select) {
            const field = select.closest('.smo-form-field');
            this.validateField(select);
        }

        /**
         * Update checkbox validation
         */
        updateCheckboxValidation(checkbox) {
            const field = checkbox.closest('.smo-form-field');
            const required = checkbox.hasAttribute('required');
            
            if (required && !checkbox.checked) {
                this.showValidationError(field, 'This field is required');
            } else {
                this.clearValidationError(field);
            }
        }

        /**
         * Validate entire form
         */
        validateForm(form) {
            const formData = this.forms.get(form);
            if (!formData) {
                return true;
            }

            let isValid = true;
            const firstInvalidField = null;

            // Validate all fields
            for (const [input, field] of formData.fields) {
                if (!this.validateField(input)) {
                    isValid = false;
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                    }
                }
            }

            // Focus first invalid field
            if (!isValid && firstInvalidField) {
                firstInvalidField.focus();
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return isValid;
        }

        /**
         * Add form loading state
         */
        addFormLoadingState(form) {
            const submitButtons = form.querySelectorAll('input[type="submit"], button[type="submit"]');
            submitButtons.forEach(button => {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Processing...';
            });
        }

        /**
         * Remove form loading state
         */
        removeFormLoadingState(form) {
            const submitButtons = form.querySelectorAll('input[type="submit"], button[type="submit"]');
            submitButtons.forEach(button => {
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                }
            });
        }

        /**
         * Enhance form accessibility
         */
        enhanceFormAccessibility(form) {
            // Add aria-describedby for helper text
            const fields = form.querySelectorAll('.smo-form-field');
            fields.forEach(field => {
                const input = field.querySelector('.smo-input, .smo-select, .smo-textarea');
                const help = field.querySelector('.smo-form-help');
                
                if (input && help) {
                    help.id = help.id || this.generateId();
                    const describedBy = input.getAttribute('aria-describedby') || '';
                    const ids = describedBy.split(' ').filter(id => id);
                    if (!ids.includes(help.id)) {
                        ids.push(help.id);
                        input.setAttribute('aria-describedby', ids.join(' '));
                    }
                }
            });

            // Add required indicators
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                const label = form.querySelector(`label[for="${field.id}"]`) || 
                             field.closest('.smo-form-field')?.querySelector('.smo-form-label');
                if (label && !label.classList.contains('required')) {
                    label.classList.add('required');
                }
            });
        }

        /**
         * Close modal
         */
        closeModal(modal) {
            modal.style.display = 'none';
            
            // Return focus to trigger element if available
            const trigger = modal.dataset.trigger;
            if (trigger) {
                const triggerElement = document.getElementById(trigger);
                triggerElement?.focus();
            }
        }

        /**
         * Close dropdown
         */
        closeDropdown(dropdown) {
            dropdown.classList.remove('open');
        }

        /**
         * Check if input needs character counter
         */
        needsCharacterCounter(input) {
            return input.matches('textarea[data-maxlength], input[data-maxlength]');
        }

        /**
         * Generate unique ID
         */
        generateId() {
            return 'smo-' + Math.random().toString(36).substr(2, 9);
        }
    }

    /**
     * Initialize form system when DOM is ready
     */
    function initializeFormSystem() {
        if (window.SMOFormSystem) {
            window.SMOFormSystem = new SMOFormSystem();
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFormSystem);
    } else {
        initializeFormSystem();
    }

    // Export for global access
    window.SMOFormSystem = SMOFormSystem;

})(window, document);

/**
 * Legacy support for existing code
 */
(function($) {
    'use strict';

    /**
     * jQuery plugin for form enhancements
     */
    $.fn.smoFormEnhance = function(options) {
        return this.each(function() {
            const $form = $(this);
            
            // Add SMO form class if not present
            if (!$form.hasClass('smo-form')) {
                $form.addClass('smo-form');
            }

            // Enhance form fields
            $form.find('.smo-form-field').each(function() {
                const $field = $(this);
                const $input = $field.find('.smo-input, .smo-select, .smo-textarea');
                
                if ($input.length && !$field.hasClass('smo-enhanced')) {
                    $field.addClass('smo-enhanced');
                    
                    // Add floating label if needed
                    const $label = $field.find('.smo-form-label:not(.floating)');
                    if ($label.length && !$label.hasClass('floating')) {
                        $label.addClass('floating');
                    }
                }
            });
        });
    };

})(jQuery);
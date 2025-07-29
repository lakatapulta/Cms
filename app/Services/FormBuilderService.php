<?php

namespace FlexCMS\Services;

class FormBuilderService
{
    protected $forms = [];
    protected $submissions = [];

    /**
     * Create a new form
     */
    public function createForm($name, $config = [])
    {
        $form = [
            'id' => $this->generateId(),
            'name' => $name,
            'title' => $config['title'] ?? $name,
            'description' => $config['description'] ?? '',
            'fields' => $config['fields'] ?? [],
            'settings' => array_merge($this->getDefaultSettings(), $config['settings'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->forms[$form['id']] = $form;
        $this->saveForms();

        return $form;
    }

    /**
     * Get default form settings
     */
    protected function getDefaultSettings()
    {
        return [
            'submit_button_text' => 'Submit',
            'success_message' => 'Thank you! Your message has been sent.',
            'error_message' => 'There was an error submitting the form. Please try again.',
            'email_notifications' => true,
            'email_to' => config('mail.admin_email', 'admin@example.com'),
            'email_subject' => 'New Form Submission',
            'save_submissions' => true,
            'honeypot' => true,
            'recaptcha' => false,
            'recaptcha_site_key' => config('recaptcha.site_key', ''),
            'recaptcha_secret_key' => config('recaptcha.secret_key', ''),
            'redirect_url' => '',
            'css_classes' => 'flexcms-form',
            'required_fields_message' => 'Please fill in all required fields.',
            'spam_protection' => true,
            'rate_limiting' => true,
            'max_submissions_per_hour' => 10
        ];
    }

    /**
     * Add field to form
     */
    public function addField($formId, $field)
    {
        if (!isset($this->forms[$formId])) {
            throw new \Exception('Form not found');
        }

        $fieldData = array_merge($this->getDefaultFieldSettings(), $field);
        $fieldData['id'] = $this->generateId();

        $this->forms[$formId]['fields'][] = $fieldData;
        $this->forms[$formId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveForms();

        return $fieldData;
    }

    /**
     * Get default field settings
     */
    protected function getDefaultFieldSettings()
    {
        return [
            'type' => 'text',
            'name' => '',
            'label' => '',
            'placeholder' => '',
            'required' => false,
            'validation' => [],
            'options' => [],
            'css_class' => '',
            'help_text' => '',
            'default_value' => '',
            'order' => 0
        ];
    }

    /**
     * Generate form HTML
     */
    public function renderForm($formId, $options = [])
    {
        $form = $this->getForm($formId);
        if (!$form) {
            return '<p>Form not found.</p>';
        }

        $html = $this->generateFormHTML($form, $options);
        return $html;
    }

    /**
     * Generate complete form HTML
     */
    protected function generateFormHTML($form, $options = [])
    {
        $action = $options['action'] ?? '/forms/submit';
        $method = $options['method'] ?? 'POST';
        $cssClass = $form['settings']['css_classes'] ?? 'flexcms-form';

        $html = "<form id=\"form-{$form['id']}\" class=\"{$cssClass}\" action=\"{$action}\" method=\"{$method}\" novalidate>\n";
        
        // CSRF token
        $html .= $this->generateCSRFField();
        
        // Form ID
        $html .= "<input type=\"hidden\" name=\"form_id\" value=\"{$form['id']}\">\n";
        
        // Honeypot field (spam protection)
        if ($form['settings']['honeypot']) {
            $html .= $this->generateHoneypotField();
        }

        // Form title and description
        if (!empty($form['title'])) {
            $html .= "<div class=\"form-header\">\n";
            $html .= "<h3 class=\"form-title\">{$form['title']}</h3>\n";
            if (!empty($form['description'])) {
                $html .= "<p class=\"form-description\">{$form['description']}</p>\n";
            }
            $html .= "</div>\n";
        }

        // Sort fields by order
        $fields = $form['fields'];
        usort($fields, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        // Generate fields
        foreach ($fields as $field) {
            $html .= $this->generateFieldHTML($field);
        }

        // reCAPTCHA
        if ($form['settings']['recaptcha'] && $form['settings']['recaptcha_site_key']) {
            $html .= $this->generateRecaptchaField($form['settings']['recaptcha_site_key']);
        }

        // Submit button
        $submitText = $form['settings']['submit_button_text'] ?? 'Submit';
        $html .= "<div class=\"form-group\">\n";
        $html .= "<button type=\"submit\" class=\"btn btn-primary form-submit\">{$submitText}</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";

        // Add JavaScript
        $html .= $this->generateFormJavaScript($form);

        return $html;
    }

    /**
     * Generate field HTML
     */
    protected function generateFieldHTML($field)
    {
        $required = $field['required'] ? 'required' : '';
        $requiredMark = $field['required'] ? ' <span class="required">*</span>' : '';
        $cssClass = trim('form-control ' . ($field['css_class'] ?? ''));
        $helpText = $field['help_text'] ? "<small class=\"form-text text-muted\">{$field['help_text']}</small>" : '';

        $html = "<div class=\"form-group field-{$field['type']}\">\n";
        
        if ($field['type'] !== 'hidden') {
            $html .= "<label for=\"{$field['name']}\" class=\"form-label\">{$field['label']}{$requiredMark}</label>\n";
        }

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'number':
            case 'date':
            case 'time':
            case 'datetime-local':
                $html .= "<input type=\"{$field['type']}\" id=\"{$field['name']}\" name=\"{$field['name']}\" class=\"{$cssClass}\" placeholder=\"{$field['placeholder']}\" value=\"{$field['default_value']}\" {$required}>\n";
                break;

            case 'password':
                $html .= "<input type=\"password\" id=\"{$field['name']}\" name=\"{$field['name']}\" class=\"{$cssClass}\" placeholder=\"{$field['placeholder']}\" {$required}>\n";
                break;

            case 'textarea':
                $rows = $field['rows'] ?? 4;
                $html .= "<textarea id=\"{$field['name']}\" name=\"{$field['name']}\" class=\"{$cssClass}\" placeholder=\"{$field['placeholder']}\" rows=\"{$rows}\" {$required}>{$field['default_value']}</textarea>\n";
                break;

            case 'select':
                $html .= "<select id=\"{$field['name']}\" name=\"{$field['name']}\" class=\"{$cssClass}\" {$required}>\n";
                if ($field['placeholder']) {
                    $html .= "<option value=\"\">{$field['placeholder']}</option>\n";
                }
                foreach ($field['options'] as $option) {
                    $value = is_array($option) ? $option['value'] : $option;
                    $label = is_array($option) ? $option['label'] : $option;
                    $selected = $value === $field['default_value'] ? 'selected' : '';
                    $html .= "<option value=\"{$value}\" {$selected}>{$label}</option>\n";
                }
                $html .= "</select>\n";
                break;

            case 'radio':
                foreach ($field['options'] as $option) {
                    $value = is_array($option) ? $option['value'] : $option;
                    $label = is_array($option) ? $option['label'] : $option;
                    $checked = $value === $field['default_value'] ? 'checked' : '';
                    $html .= "<div class=\"form-check\">\n";
                    $html .= "<input type=\"radio\" id=\"{$field['name']}_{$value}\" name=\"{$field['name']}\" value=\"{$value}\" class=\"form-check-input\" {$checked} {$required}>\n";
                    $html .= "<label for=\"{$field['name']}_{$value}\" class=\"form-check-label\">{$label}</label>\n";
                    $html .= "</div>\n";
                }
                break;

            case 'checkbox':
                if (isset($field['options']) && !empty($field['options'])) {
                    // Multiple checkboxes
                    foreach ($field['options'] as $option) {
                        $value = is_array($option) ? $option['value'] : $option;
                        $label = is_array($option) ? $option['label'] : $option;
                        $checked = in_array($value, (array)$field['default_value']) ? 'checked' : '';
                        $html .= "<div class=\"form-check\">\n";
                        $html .= "<input type=\"checkbox\" id=\"{$field['name']}_{$value}\" name=\"{$field['name']}[]\" value=\"{$value}\" class=\"form-check-input\" {$checked}>\n";
                        $html .= "<label for=\"{$field['name']}_{$value}\" class=\"form-check-label\">{$label}</label>\n";
                        $html .= "</div>\n";
                    }
                } else {
                    // Single checkbox
                    $checked = $field['default_value'] ? 'checked' : '';
                    $html .= "<div class=\"form-check\">\n";
                    $html .= "<input type=\"checkbox\" id=\"{$field['name']}\" name=\"{$field['name']}\" value=\"1\" class=\"form-check-input\" {$checked} {$required}>\n";
                    $html .= "<label for=\"{$field['name']}\" class=\"form-check-label\">{$field['label']}</label>\n";
                    $html .= "</div>\n";
                }
                break;

            case 'file':
                $accept = isset($field['accept']) ? "accept=\"{$field['accept']}\"" : '';
                $multiple = isset($field['multiple']) && $field['multiple'] ? 'multiple' : '';
                $html .= "<input type=\"file\" id=\"{$field['name']}\" name=\"{$field['name']}\" class=\"{$cssClass}\" {$accept} {$multiple} {$required}>\n";
                break;

            case 'hidden':
                $html = "<input type=\"hidden\" id=\"{$field['name']}\" name=\"{$field['name']}\" value=\"{$field['default_value']}\">\n";
                return $html; // No wrapper div for hidden fields

            case 'html':
                $html .= $field['content'] ?? '';
                break;
        }

        $html .= $helpText;
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Generate CSRF field
     */
    protected function generateCSRFField()
    {
        return csrf_field() . "\n";
    }

    /**
     * Generate honeypot field
     */
    protected function generateHoneypotField()
    {
        $honeypotName = 'website_url_' . uniqid();
        return "<input type=\"text\" name=\"{$honeypotName}\" style=\"display:none !important;\" tabindex=\"-1\" autocomplete=\"off\">\n";
    }

    /**
     * Generate reCAPTCHA field
     */
    protected function generateRecaptchaField($siteKey)
    {
        return "<div class=\"form-group\">\n" .
               "<div class=\"g-recaptcha\" data-sitekey=\"{$siteKey}\"></div>\n" .
               "</div>\n" .
               "<script src=\"https://www.google.com/recaptcha/api.js\" async defer></script>\n";
    }

    /**
     * Generate form JavaScript
     */
    protected function generateFormJavaScript($form)
    {
        return "<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-{$form['id']}');
    if (!form) return;

    // Form validation
    form.addEventListener('submit', function(e) {
        if (!validateForm(form)) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('.form-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Sending...';
        }
    });

    function validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        // Clear previous errors
        form.querySelectorAll('.field-error').forEach(error => error.remove());
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required.');
                isValid = false;
            } else if (field.type === 'email' && !isValidEmail(field.value)) {
                showFieldError(field, 'Please enter a valid email address.');
                isValid = false;
            }
        });

        return isValid;
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-danger';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
});
</script>";
    }

    /**
     * Process form submission
     */
    public function processSubmission($formId, $data)
    {
        $form = $this->getForm($formId);
        if (!$form) {
            return ['success' => false, 'error' => 'Form not found'];
        }

        // Validate submission
        $validation = $this->validateSubmission($form, $data);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        // Check rate limiting
        if ($form['settings']['rate_limiting'] && !$this->checkRateLimit($formId)) {
            return ['success' => false, 'error' => 'Too many submissions. Please try again later.'];
        }

        // Process submission
        $submission = [
            'id' => $this->generateId(),
            'form_id' => $formId,
            'data' => $this->sanitizeSubmissionData($form, $data),
            'user_id' => user() ? user()->id : null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'submitted_at' => date('Y-m-d H:i:s'),
            'status' => 'new'
        ];

        // Save submission
        if ($form['settings']['save_submissions']) {
            $this->saveSubmission($submission);
        }

        // Send email notification
        if ($form['settings']['email_notifications']) {
            $this->sendEmailNotification($form, $submission);
        }

        // Track analytics event
        if (app()->has('analytics')) {
            app('analytics')->trackEvent('form_submission', 'forms', $form['name']);
        }

        return [
            'success' => true,
            'message' => $form['settings']['success_message'],
            'redirect_url' => $form['settings']['redirect_url'] ?? ''
        ];
    }

    /**
     * Validate form submission
     */
    protected function validateSubmission($form, $data)
    {
        $errors = [];

        // Check honeypot
        foreach ($data as $key => $value) {
            if (strpos($key, 'website_url_') === 0 && !empty($value)) {
                $errors['spam'] = 'Spam detected';
                break;
            }
        }

        // Validate reCAPTCHA
        if ($form['settings']['recaptcha'] && $form['settings']['recaptcha_secret_key']) {
            if (!$this->validateRecaptcha($data['g-recaptcha-response'] ?? '', $form['settings']['recaptcha_secret_key'])) {
                $errors['recaptcha'] = 'Please complete the reCAPTCHA verification';
            }
        }

        // Validate fields
        foreach ($form['fields'] as $field) {
            $fieldName = $field['name'];
            $fieldValue = $data[$fieldName] ?? '';

            // Required field validation
            if ($field['required'] && empty($fieldValue)) {
                $errors[$fieldName] = "{$field['label']} is required";
                continue;
            }

            // Type-specific validation
            if (!empty($fieldValue)) {
                switch ($field['type']) {
                    case 'email':
                        if (!filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                            $errors[$fieldName] = "Please enter a valid email address";
                        }
                        break;
                    
                    case 'url':
                        if (!filter_var($fieldValue, FILTER_VALIDATE_URL)) {
                            $errors[$fieldName] = "Please enter a valid URL";
                        }
                        break;
                    
                    case 'number':
                        if (!is_numeric($fieldValue)) {
                            $errors[$fieldName] = "Please enter a valid number";
                        }
                        break;
                }
            }

            // Custom validation rules
            if (isset($field['validation']) && !empty($field['validation'])) {
                foreach ($field['validation'] as $rule => $ruleValue) {
                    switch ($rule) {
                        case 'min_length':
                            if (strlen($fieldValue) < $ruleValue) {
                                $errors[$fieldName] = "{$field['label']} must be at least {$ruleValue} characters";
                            }
                            break;
                        
                        case 'max_length':
                            if (strlen($fieldValue) > $ruleValue) {
                                $errors[$fieldName] = "{$field['label']} must not exceed {$ruleValue} characters";
                            }
                            break;
                        
                        case 'pattern':
                            if (!preg_match($ruleValue, $fieldValue)) {
                                $errors[$fieldName] = "{$field['label']} format is invalid";
                            }
                            break;
                    }
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate reCAPTCHA
     */
    protected function validateRecaptcha($response, $secretKey)
    {
        if (empty($response)) {
            return false;
        }

        $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $response,
            'remoteip' => $this->getClientIP()
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($verifyURL, false, $context);
        $responseData = json_decode($result, true);

        return isset($responseData['success']) && $responseData['success'] === true;
    }

    /**
     * Check rate limiting
     */
    protected function checkRateLimit($formId)
    {
        $ip = $this->getClientIP();
        $hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // Count submissions from this IP in the last hour
        $recentSubmissions = array_filter($this->submissions, function($submission) use ($formId, $ip, $hourAgo) {
            return $submission['form_id'] === $formId &&
                   $submission['ip_address'] === $ip &&
                   $submission['submitted_at'] >= $hourAgo;
        });

        $maxSubmissions = $this->forms[$formId]['settings']['max_submissions_per_hour'] ?? 10;
        return count($recentSubmissions) < $maxSubmissions;
    }

    /**
     * Sanitize submission data
     */
    protected function sanitizeSubmissionData($form, $data)
    {
        $sanitized = [];
        
        foreach ($form['fields'] as $field) {
            $fieldName = $field['name'];
            $fieldValue = $data[$fieldName] ?? '';
            
            // Basic sanitization
            if (is_array($fieldValue)) {
                $sanitized[$fieldName] = array_map('htmlspecialchars', $fieldValue);
            } else {
                $sanitized[$fieldName] = htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $sanitized;
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification($form, $submission)
    {
        $emailTo = $form['settings']['email_to'];
        $emailSubject = $form['settings']['email_subject'] . ' - ' . $form['title'];
        
        $emailBody = "New form submission received:\n\n";
        $emailBody .= "Form: {$form['title']}\n";
        $emailBody .= "Submitted at: {$submission['submitted_at']}\n";
        $emailBody .= "IP Address: {$submission['ip_address']}\n\n";
        
        foreach ($submission['data'] as $fieldName => $fieldValue) {
            $field = $this->getFieldByName($form, $fieldName);
            $fieldLabel = $field ? $field['label'] : $fieldName;
            
            if (is_array($fieldValue)) {
                $fieldValue = implode(', ', $fieldValue);
            }
            
            $emailBody .= "{$fieldLabel}: {$fieldValue}\n";
        }
        
        // Send email (implement with your email service)
        $this->sendEmail($emailTo, $emailSubject, $emailBody);
        
        logger()->info('Form notification sent', [
            'form_id' => $form['id'],
            'submission_id' => $submission['id'],
            'email_to' => $emailTo
        ]);
    }

    /**
     * Get field by name
     */
    protected function getFieldByName($form, $fieldName)
    {
        foreach ($form['fields'] as $field) {
            if ($field['name'] === $fieldName) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Send email (placeholder - implement with your email service)
     */
    protected function sendEmail($to, $subject, $body)
    {
        // Implement with your email service (SMTP, SendGrid, etc.)
        $headers = "From: " . config('mail.from_email', 'noreply@example.com') . "\r\n";
        $headers .= "Reply-To: " . config('mail.from_email', 'noreply@example.com') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Save form submission
     */
    protected function saveSubmission($submission)
    {
        $this->submissions[] = $submission;
        $this->saveSubmissions();
    }

    /**
     * Get form by ID
     */
    public function getForm($formId)
    {
        return $this->forms[$formId] ?? null;
    }

    /**
     * List all forms
     */
    public function listForms()
    {
        return array_values($this->forms);
    }

    /**
     * Delete form
     */
    public function deleteForm($formId)
    {
        if (isset($this->forms[$formId])) {
            unset($this->forms[$formId]);
            $this->saveForms();
            return true;
        }
        return false;
    }

    /**
     * Get form submissions
     */
    public function getSubmissions($formId, $limit = 50)
    {
        $formSubmissions = array_filter($this->submissions, function($submission) use ($formId) {
            return $submission['form_id'] === $formId;
        });

        // Sort by submission date (newest first)
        usort($formSubmissions, function($a, $b) {
            return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
        });

        return array_slice($formSubmissions, 0, $limit);
    }

    /**
     * Generate unique ID
     */
    protected function generateId()
    {
        return uniqid('form_', true);
    }

    /**
     * Get client IP
     */
    protected function getClientIP()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Save forms to storage
     */
    protected function saveForms()
    {
        $formsFile = storage_path('forms.json');
        file_put_contents($formsFile, json_encode($this->forms, JSON_PRETTY_PRINT));
    }

    /**
     * Load forms from storage
     */
    protected function loadForms()
    {
        $formsFile = storage_path('forms.json');
        if (file_exists($formsFile)) {
            $this->forms = json_decode(file_get_contents($formsFile), true) ?: [];
        }
    }

    /**
     * Save submissions to storage
     */
    protected function saveSubmissions()
    {
        $submissionsFile = storage_path('form_submissions.json');
        file_put_contents($submissionsFile, json_encode($this->submissions, JSON_PRETTY_PRINT));
    }

    /**
     * Load submissions from storage
     */
    protected function loadSubmissions()
    {
        $submissionsFile = storage_path('form_submissions.json');
        if (file_exists($submissionsFile)) {
            $this->submissions = json_decode(file_get_contents($submissionsFile), true) ?: [];
        }
    }

    /**
     * Initialize service
     */
    public function __construct()
    {
        $this->loadForms();
        $this->loadSubmissions();
    }

    /**
     * Create default contact form
     */
    public function createDefaultContactForm()
    {
        $contactForm = [
            'title' => 'Contact Us',
            'description' => 'Get in touch with us using the form below.',
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'label' => 'Full Name',
                    'placeholder' => 'Enter your full name',
                    'required' => true,
                    'order' => 1
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'label' => 'Email Address',
                    'placeholder' => 'Enter your email address',
                    'required' => true,
                    'order' => 2
                ],
                [
                    'type' => 'tel',
                    'name' => 'phone',
                    'label' => 'Phone Number',
                    'placeholder' => 'Enter your phone number',
                    'required' => false,
                    'order' => 3
                ],
                [
                    'type' => 'select',
                    'name' => 'subject',
                    'label' => 'Subject',
                    'placeholder' => 'Select a subject',
                    'required' => true,
                    'options' => [
                        'General Inquiry',
                        'Support Request',
                        'Business Partnership',
                        'Technical Issue',
                        'Other'
                    ],
                    'order' => 4
                ],
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'label' => 'Message',
                    'placeholder' => 'Enter your message',
                    'required' => true,
                    'rows' => 5,
                    'order' => 5
                ]
            ],
            'settings' => [
                'submit_button_text' => 'Send Message',
                'success_message' => 'Thank you for your message! We will get back to you soon.',
                'email_subject' => 'New Contact Form Submission'
            ]
        ];

        return $this->createForm('contact', $contactForm);
    }
}
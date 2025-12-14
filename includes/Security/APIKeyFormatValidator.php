<?php
namespace SMO_Social\Security;

class APIKeyFormatValidator {
    private $validation_patterns = array();

    public function __construct() {
        $this->initialize_validation_patterns();
    }

    private function initialize_validation_patterns() {
        $this->validation_patterns = array(
            'openai' => array(
                'pattern' => '/^sk-[a-zA-Z0-9]{48,}$/',
                'example' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'OpenAI keys start with "sk-" followed by 48+ alphanumeric characters',
                'min_length' => 50,
                'max_length' => 100
            ),

            'huggingface' => array(
                'pattern' => '/^hf_[a-zA-Z0-9]{34,}$/',
                'example' => 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'HuggingFace keys start with "hf_" followed by 34+ alphanumeric characters',
                'min_length' => 37,
                'max_length' => 50
            ),

            'openrouter' => array(
                'pattern' => '/^sk-or-v1-[a-zA-Z0-9]{64}$/',
                'example' => 'sk-or-v1-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'OpenRouter keys start with "sk-or-v1-" followed by 64 alphanumeric characters',
                'min_length' => 73,
                'max_length' => 73
            ),

            'anthropic' => array(
                'pattern' => '/^sk-ant-api03-[a-zA-Z0-9_-]{95,}$/',
                'example' => 'sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Anthropic keys start with "sk-ant-api03-" followed by 95+ alphanumeric characters and underscores',
                'min_length' => 110,
                'max_length' => 150
            ),

            'replicate' => array(
                'pattern' => '/^r8_[a-zA-Z0-9]{40}$/',
                'example' => 'r8_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Replicate keys start with "r8_" followed by 40 alphanumeric characters',
                'min_length' => 43,
                'max_length' => 43
            ),

            'together' => array(
                'pattern' => '/^[a-zA-Z0-9]{64}$/',
                'example' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Together AI keys are 64 alphanumeric characters',
                'min_length' => 64,
                'max_length' => 64
            ),

            'cohere' => array(
                'pattern' => '/^[a-zA-Z0-9]{40}$/',
                'example' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Cohere keys are 40 alphanumeric characters',
                'min_length' => 40,
                'max_length' => 40
            ),

            'stability' => array(
                'pattern' => '/^sk-[a-zA-Z0-9]{48}$/',
                'example' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Stability AI keys start with "sk-" followed by 48 alphanumeric characters',
                'min_length' => 51,
                'max_length' => 51
            ),

            'groq' => array(
                'pattern' => '/^gsk_[a-zA-Z0-9]{50,}$/',
                'example' => 'gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Groq keys start with "gsk_" followed by 50+ alphanumeric characters',
                'min_length' => 54,
                'max_length' => 70
            ),

            'fireworks' => array(
                'pattern' => '/^fw_[a-zA-Z0-9]{40,}$/',
                'example' => 'fw_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'description' => 'Fireworks AI keys start with "fw_" followed by 40+ alphanumeric characters',
                'min_length' => 43,
                'max_length' => 60
            ),

            'custom-api' => array(
                'pattern' => '/^[a-zA-Z0-9_-]{16,}$/',
                'example' => 'xxxxxxxxxxxxxxxx',
                'description' => 'Custom API keys are 16+ alphanumeric characters and underscores',
                'min_length' => 16,
                'max_length' => 256
            ),

            'ollama' => array(
                'pattern' => '/^[a-zA-Z0-9_-]{0,}$/',
                'example' => '',
                'description' => 'Ollama typically doesn\'t require API keys for local instances',
                'min_length' => 0,
                'max_length' => 256
            ),

            'lm-studio' => array(
                'pattern' => '/^[a-zA-Z0-9_-]{0,}$/',
                'example' => '',
                'description' => 'LM Studio typically doesn\'t require API keys for local instances',
                'min_length' => 0,
                'max_length' => 256
            )
        );
    }

    public function validate_key_format($key, $provider) {
        if (empty($key)) {
            return array(
                'valid' => false,
                'error' => 'API key cannot be empty',
                'pattern' => null
            );
        }

        if (!isset($this->validation_patterns[$provider])) {
            return $this->validate_generic_key($key);
        }

        $pattern_data = $this->validation_patterns[$provider];

        if (strlen($key) < ($pattern_data['min_length'] ?? 0)) {
            return array(
                'valid' => false,
                'error' => 'API key is too short. Minimum length: ' . ($pattern_data['min_length'] ?? 'unknown'),
                'pattern' => $pattern_data['pattern'] ?? null
            );
        }

        if (!empty($pattern_data['max_length']) && strlen($key) > $pattern_data['max_length']) {
            return array(
                'valid' => false,
                'error' => 'API key is too long. Maximum length: ' . $pattern_data['max_length'],
                'pattern' => $pattern_data['pattern'] ?? null
            );
        }

        if (!preg_match($pattern_data['pattern'], $key)) {
            return array(
                'valid' => false,
                'error' => 'API key format is invalid. Expected format: ' . ($pattern_data['description'] ?? 'unknown'),
                'pattern' => $pattern_data['pattern'] ?? null,
                'example' => $pattern_data['example'] ?? null
            );
        }

        return array(
            'valid' => true,
            'message' => 'API key format is valid',
            'pattern' => $pattern_data['pattern'] ?? null
        );
    }

    private function validate_generic_key($key) {
        if (strlen($key) < 16) {
            return array(
                'valid' => false,
                'error' => 'API key is too short. Minimum length: 16 characters',
                'pattern' => null
            );
        }

        if (strlen($key) > 256) {
            return array(
                'valid' => false,
                'error' => 'API key is too long. Maximum length: 256 characters',
                'pattern' => null
            );
        }

        if (!preg_match('/[a-zA-Z0-9]/', $key)) {
            return array(
                'valid' => false,
                'error' => 'API key should contain alphanumeric characters',
                'pattern' => null
            );
        }

        return array(
            'valid' => true,
            'message' => 'API key format appears valid',
            'pattern' => null
        );
    }

    public function get_validation_pattern($provider) {
        return $this->validation_patterns[$provider] ?? null;
    }

    public function get_supported_providers() {
        return array_keys($this->validation_patterns);
    }
}
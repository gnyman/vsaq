<?php
// VSAQ Questionnaire Renderer

class VSAQRenderer {
    private $answers = [];

    public function setAnswers($answers) {
        $this->answers = is_array($answers) ? $answers : [];
    }

    public function renderQuestionnaire($template) {
        if (is_string($template)) {
            $template = json_decode($template, true);
        }

        if (!isset($template['questionnaire'])) {
            return '<p>Invalid questionnaire template</p>';
        }

        $html = '<div class="vsaq-questionnaire">';
        foreach ($template['questionnaire'] as $item) {
            $html .= $this->renderItem($item);
        }
        $html .= '</div>';

        return $html;
    }

    private function renderItem($item) {
        $type = $item['type'] ?? 'block';

        switch ($type) {
            case 'block':
                return $this->renderBlock($item);
            case 'line':
                return $this->renderLine($item);
            case 'box':
                return $this->renderBox($item);
            case 'check':
                return $this->renderCheck($item);
            case 'radio':
                return $this->renderRadio($item);
            case 'radiogroup':
                return $this->renderRadioGroup($item);
            case 'yesno':
                return $this->renderYesNo($item);
            case 'upload':
                return $this->renderUpload($item);
            case 'info':
                return $this->renderInfo($item);
            case 'lineitem':
                return $this->renderLineItem($item);
            default:
                return '<!-- Unknown type: ' . e($type) . ' -->';
        }
    }

    private function renderBlock($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $items = $item['items'] ?? [];
        $required = $item['required'] ?? false;

        $html = '<div class="vsaq-block" id="' . e($id) . '">';
        if ($text) {
            $html .= '<h3 class="vsaq-block-title">' . e($text);
            if ($required) $html .= ' <span class="required">*</span>';
            $html .= '</h3>';
        }
        $html .= '<div class="vsaq-block-content">';
        foreach ($items as $subItem) {
            $html .= $this->renderItem($subItem);
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function renderLine($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;
        $placeholder = $item['placeholder'] ?? '';
        $value = $this->answers[$id] ?? '';

        $html = '<div class="vsaq-item vsaq-line">';
        $html .= '<label for="' . e($id) . '">' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '<input type="text" id="' . e($id) . '" name="' . e($id) . '" ';
        $html .= 'value="' . e($value) . '" ';
        if ($placeholder) $html .= 'placeholder="' . e($placeholder) . '" ';
        if ($required) $html .= 'required ';
        $html .= 'class="vsaq-input">';
        $html .= '</div>';

        return $html;
    }

    private function renderBox($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;
        $placeholder = $item['placeholder'] ?? '';
        $value = $this->answers[$id] ?? '';

        $html = '<div class="vsaq-item vsaq-box">';
        $html .= '<label for="' . e($id) . '">' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '<textarea id="' . e($id) . '" name="' . e($id) . '" rows="4" ';
        if ($placeholder) $html .= 'placeholder="' . e($placeholder) . '" ';
        if ($required) $html .= 'required ';
        $html .= 'class="vsaq-textarea">' . e($value) . '</textarea>';
        $html .= '</div>';

        return $html;
    }

    private function renderCheck($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;
        $checked = ($this->answers[$id] ?? '') === 'yes';

        $html = '<div class="vsaq-item vsaq-check">';
        $html .= '<label>';
        $html .= '<input type="checkbox" id="' . e($id) . '" name="' . e($id) . '" ';
        $html .= 'value="yes" ';
        if ($checked) $html .= 'checked ';
        if ($required) $html .= 'required ';
        $html .= '>';
        $html .= ' ' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '</div>';

        return $html;
    }

    private function renderYesNo($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;
        $value = $this->answers[$id] ?? '';

        $html = '<div class="vsaq-item vsaq-yesno">';
        $html .= '<label>' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '<div class="vsaq-radio-group">';
        $html .= '<label><input type="radio" name="' . e($id) . '" value="yes" ';
        if ($value === 'yes') $html .= 'checked ';
        if ($required) $html .= 'required ';
        $html .= '> Yes</label>';
        $html .= '<label><input type="radio" name="' . e($id) . '" value="no" ';
        if ($value === 'no') $html .= 'checked ';
        if ($required) $html .= 'required ';
        $html .= '> No</label>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function renderRadioGroup($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;
        $choices = $item['choices'] ?? [];
        $value = $this->answers[$id] ?? '';

        $html = '<div class="vsaq-item vsaq-radiogroup">';
        $html .= '<label>' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '<div class="vsaq-radio-group">';
        foreach ($choices as $choice) {
            $choiceValue = is_array($choice) ? ($choice['value'] ?? '') : $choice;
            $choiceText = is_array($choice) ? ($choice['text'] ?? $choiceValue) : $choice;
            $html .= '<label><input type="radio" name="' . e($id) . '" value="' . e($choiceValue) . '" ';
            if ($value === $choiceValue) $html .= 'checked ';
            if ($required) $html .= 'required ';
            $html .= '> ' . e($choiceText) . '</label>';
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function renderRadio($item) {
        // Legacy radio item type
        return $this->renderRadioGroup($item);
    }

    private function renderUpload($item) {
        $id = $item['id'] ?? '';
        $text = $item['text'] ?? '';
        $required = $item['required'] ?? false;

        $html = '<div class="vsaq-item vsaq-upload">';
        $html .= '<label for="' . e($id) . '">' . e($text);
        if ($required) $html .= ' <span class="required">*</span>';
        $html .= '</label>';
        $html .= '<input type="file" id="' . e($id) . '" name="' . e($id) . '" ';
        if ($required) $html .= 'required ';
        $html .= '>';
        $html .= '</div>';

        return $html;
    }

    private function renderInfo($item) {
        $text = $item['text'] ?? '';

        $html = '<div class="vsaq-item vsaq-info">';
        $html .= '<p class="vsaq-info-text">' . nl2br(e($text)) . '</p>';
        $html .= '</div>';

        return $html;
    }

    private function renderLineItem($item) {
        // Similar to line but for table-like structures
        return $this->renderLine($item);
    }
}

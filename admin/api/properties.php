<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../classes/Element.php';

// Require login
requireLogin();

// Set JSON header
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $type = $_GET['type'] ?? '';
        $id = $_GET['id'] ?? '';
        
        if (empty($type)) {
            errorResponse('Element type required');
        }
        
        // Get element type definition
        $elementType = Element::getByName($type);
        if (!$elementType) {
            errorResponse('Element type not found');
        }
        
        // Get default properties for this element type
        $defaultProperties = parseJSON($elementType['default_properties']);
        $defaultStyles = parseJSON($elementType['default_styles']);
        
        // Combine properties and generate form fields
        $properties = [
            'type' => $type,
            'id' => $id,
            'properties' => $defaultProperties,
            'styles' => $defaultStyles,
            'form_fields' => generateFormFields($type, $defaultProperties, $defaultStyles)
        ];
        
        successResponse(['properties' => $properties], 'Properties loaded successfully');
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'validate';
        
        switch ($action) {
            case 'validate':
                $elementData = $input['element'] ?? null;
                
                if (!$elementData) {
                    errorResponse('Element data required');
                }
                
                $errors = Element::validateElementData($elementData);
                
                if (empty($errors)) {
                    successResponse([], 'Properties are valid');
                } else {
                    errorResponse(implode(', ', $errors));
                }
                break;
                
            case 'get_default':
                $type = $input['type'] ?? '';
                
                if (empty($type)) {
                    errorResponse('Element type required');
                }
                
                $elementType = Element::getByName($type);
                if (!$elementType) {
                    errorResponse('Element type not found');
                }
                
                $element = new Element($elementType['id']);
                $defaultData = $element->getDefaultData();
                
                successResponse(['element' => $defaultData], 'Default properties generated');
                break;
                
            default:
                errorResponse('Invalid action');
        }
        
    } else {
        errorResponse('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        errorResponse($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(), 500);
    } else {
        errorResponse('Internal server error', 500);
    }
}

/**
 * Generate form fields for element properties
 */
function generateFormFields($type, $properties, $styles) {
    $fields = [];
    
    // Basic info
    $fields[] = [
        'type' => 'text',
        'name' => 'id',
        'label' => 'Element ID',
        'value' => '',
        'readonly' => true,
        'section' => 'basic'
    ];
    
    // Type-specific fields
    switch ($type) {
        case 'text':
            $fields[] = [
                'type' => 'textarea',
                'name' => 'content',
                'label' => 'Text Content',
                'value' => $properties['content'] ?? '',
                'rows' => 3,
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'select',
                'name' => 'tag',
                'label' => 'HTML Tag',
                'value' => $properties['tag'] ?? 'p',
                'options' => [
                    'p' => 'Paragraph',
                    'span' => 'Span',
                    'div' => 'Div'
                ],
                'section' => 'content'
            ];
            break;
            
        case 'heading':
            $fields[] = [
                'type' => 'text',
                'name' => 'content',
                'label' => 'Heading Text',
                'value' => $properties['content'] ?? '',
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'select',
                'name' => 'tag',
                'label' => 'Heading Level',
                'value' => $properties['tag'] ?? 'h2',
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6'
                ],
                'section' => 'content'
            ];
            break;
            
        case 'image':
            $fields[] = [
                'type' => 'url',
                'name' => 'src',
                'label' => 'Image URL',
                'value' => $properties['src'] ?? '',
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'text',
                'name' => 'alt',
                'label' => 'Alt Text',
                'value' => $properties['alt'] ?? '',
                'section' => 'content'
            ];
            break;
            
        case 'button':
            $fields[] = [
                'type' => 'text',
                'name' => 'text',
                'label' => 'Button Text',
                'value' => $properties['text'] ?? '',
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'url',
                'name' => 'link',
                'label' => 'Link URL',
                'value' => $properties['link'] ?? '',
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'select',
                'name' => 'target',
                'label' => 'Link Target',
                'value' => $properties['target'] ?? '_self',
                'options' => [
                    '_self' => 'Same Window',
                    '_blank' => 'New Window'
                ],
                'section' => 'content'
            ];
            break;
            
        case 'video':
            $fields[] = [
                'type' => 'url',
                'name' => 'src',
                'label' => 'Video URL',
                'value' => $properties['src'] ?? '',
                'section' => 'content'
            ];
            
            $fields[] = [
                'type' => 'checkbox',
                'name' => 'controls',
                'label' => 'Show Controls',
                'value' => $properties['controls'] ?? true,
                'section' => 'content'
            ];
            break;
            
        case 'spacer':
            $fields[] = [
                'type' => 'text',
                'name' => 'height',
                'label' => 'Height',
                'value' => $properties['height'] ?? '50px',
                'section' => 'content'
            ];
            break;
    }
    
    // Style fields
    $styleFields = [
        [
            'type' => 'text',
            'name' => 'width',
            'label' => 'Width',
            'value' => $styles['width'] ?? '',
            'placeholder' => 'auto',
            'section' => 'dimensions'
        ],
        [
            'type' => 'text',
            'name' => 'height',
            'label' => 'Height',
            'value' => $styles['height'] ?? '',
            'placeholder' => 'auto',
            'section' => 'dimensions'
        ],
        [
            'type' => 'color',
            'name' => 'color',
            'label' => 'Text Color',
            'value' => $styles['color'] ?? '#000000',
            'section' => 'appearance'
        ],
        [
            'type' => 'color',
            'name' => 'backgroundColor',
            'label' => 'Background Color',
            'value' => $styles['backgroundColor'] ?? '#ffffff',
            'section' => 'appearance'
        ],
        [
            'type' => 'text',
            'name' => 'fontSize',
            'label' => 'Font Size',
            'value' => $styles['fontSize'] ?? '',
            'placeholder' => '16px',
            'section' => 'typography'
        ],
        [
            'type' => 'select',
            'name' => 'fontWeight',
            'label' => 'Font Weight',
            'value' => $styles['fontWeight'] ?? 'normal',
            'options' => [
                'normal' => 'Normal',
                'bold' => 'Bold',
                '100' => '100',
                '200' => '200',
                '300' => '300',
                '400' => '400',
                '500' => '500',
                '600' => '600',
                '700' => '700',
                '800' => '800',
                '900' => '900'
            ],
            'section' => 'typography'
        ],
        [
            'type' => 'select',
            'name' => 'textAlign',
            'label' => 'Text Align',
            'value' => $styles['textAlign'] ?? 'left',
            'options' => [
                'left' => 'Left',
                'center' => 'Center',
                'right' => 'Right',
                'justify' => 'Justify'
            ],
            'section' => 'typography'
        ],
        [
            'type' => 'text',
            'name' => 'margin',
            'label' => 'Margin',
            'value' => $styles['margin'] ?? '',
            'placeholder' => '0px',
            'section' => 'spacing'
        ],
        [
            'type' => 'text',
            'name' => 'padding',
            'label' => 'Padding',
            'value' => $styles['padding'] ?? '',
            'placeholder' => '0px',
            'section' => 'spacing'
        ],
        [
            'type' => 'text',
            'name' => 'borderRadius',
            'label' => 'Border Radius',
            'value' => $styles['borderRadius'] ?? '',
            'placeholder' => '0px',
            'section' => 'appearance'
        ],
        [
            'type' => 'text',
            'name' => 'border',
            'label' => 'Border',
            'value' => $styles['border'] ?? '',
            'placeholder' => 'none',
            'section' => 'appearance'
        ]
    ];
    
    $fields = array_merge($fields, $styleFields);
    
    return $fields;
}
?>

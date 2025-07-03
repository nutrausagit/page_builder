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

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $elements = Element::getAll();
                successResponse($elements, 'Elements loaded successfully');
            }
            break;
            
        case 'get_default':
            if ($method === 'GET') {
                $type = $_GET['type'] ?? '';
                if (empty($type)) {
                    errorResponse('Element type required');
                }
                
                $elementType = Element::getByName($type);
                if (!$elementType) {
                    errorResponse('Element type not found');
                }
                
                $element = new Element($elementType['id']);
                $defaultData = $element->getDefaultData();
                
                if (!$defaultData) {
                    errorResponse('Could not generate default element data');
                }
                
                successResponse(['element' => $defaultData], 'Default element data generated');
            }
            break;
            
        case 'create':
            if ($method === 'POST') {
                requirePermission('create_element');
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                $required = ['name', 'display_name', 'category'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        errorResponse("Field {$field} is required");
                    }
                }
                
                $elementId = Element::create($input);
                successResponse(['id' => $elementId], 'Element type created successfully');
            }
            break;
            
        case 'update':
            if ($method === 'POST') {
                requirePermission('edit_element');
                
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? null;
                
                if (!$id) {
                    errorResponse('Element ID required');
                }
                
                $element = new Element($id);
                if (!$element->getData()) {
                    errorResponse('Element not found');
                }
                
                unset($input['id']);
                $element->update($input);
                
                successResponse([], 'Element type updated successfully');
            }
            break;
            
        case 'delete':
            if ($method === 'POST') {
                requirePermission('delete_element');
                
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? null;
                
                if (!$id) {
                    errorResponse('Element ID required');
                }
                
                $element = new Element($id);
                if (!$element->getData()) {
                    errorResponse('Element not found');
                }
                
                $element->delete();
                successResponse([], 'Element type deleted successfully');
            }
            break;
            
        case 'validate':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $elementData = $input['element'] ?? null;
                
                if (!$elementData) {
                    errorResponse('Element data required');
                }
                
                $errors = Element::validateElementData($elementData);
                
                if (empty($errors)) {
                    successResponse([], 'Element data is valid');
                } else {
                    errorResponse(implode(', ', $errors));
                }
            }
            break;
            
        case 'render':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $elementData = $input['element'] ?? null;
                
                if (!$elementData) {
                    errorResponse('Element data required');
                }
                
                // Validate element data
                $errors = Element::validateElementData($elementData);
                if (!empty($errors)) {
                    errorResponse('Invalid element data: ' . implode(', ', $errors));
                }
                
                $html = Element::renderElement($elementData);
                successResponse(['html' => $html], 'Element rendered successfully');
            }
            break;
            
        case 'usage':
            if ($method === 'GET') {
                $id = $_GET['id'] ?? null;
                
                if (!$id) {
                    errorResponse('Element ID required');
                }
                
                $element = new Element($id);
                if (!$element->getData()) {
                    errorResponse('Element not found');
                }
                
                $usageCount = $element->getUsageCount();
                successResponse(['usage_count' => $usageCount], 'Usage count retrieved');
            }
            break;
            
        default:
            errorResponse('Invalid action', 400);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        errorResponse($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(), 500);
    } else {
        errorResponse('Internal server error', 500);
    }
}
?>

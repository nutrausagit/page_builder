/**
 * =====================================================
 * ADMIN PANEL JAVASCRIPT - Page Builder Pro
 * =====================================================
 */

class PageBuilder {
    constructor() {
        this.selectedElement = null;
        this.clipboard = null;
        this.history = [];
        this.historyIndex = -1;
        this.maxHistory = 50;
        
        this.init();
    }
    
    init() {
        this.initDragAndDrop();
        this.initElementSelection();
        this.initPropertyPanel();
        this.initToolbar();
        this.initKeyboardShortcuts();
        this.loadElementTypes();
        
        // Auto-save every 30 seconds
        setInterval(() => {
            this.autoSave();
        }, 30000);
    }
    
    // Element Types Management
    loadElementTypes() {
        fetch('/admin/api/elements.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderElementLibrary(data.elements);
                }
            })
            .catch(error => console.error('Error loading elements:', error));
    }
    
    renderElementLibrary(elements) {
        const library = document.querySelector('.element-library');
        if (!library) return;
        
        // Group elements by category
        const categories = {};
        elements.forEach(element => {
            if (!categories[element.category]) {
                categories[element.category] = [];
            }
            categories[element.category].push(element);
        });
        
        library.innerHTML = '';
        
        Object.keys(categories).forEach(categoryName => {
            const categoryDiv = document.createElement('div');
            categoryDiv.className = 'element-category';
            
            categoryDiv.innerHTML = `
                <div class="category-header">${this.capitalizeFirst(categoryName)}</div>
                <div class="element-list">
                    ${categories[categoryName].map(element => `
                        <div class="element-item" draggable="true" data-element-type="${element.name}">
                            <div class="element-icon">
                                <i class="fas ${element.icon || 'fa-square'}"></i>
                            </div>
                            <div class="element-info">
                                <div class="element-name">${element.display_name}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            library.appendChild(categoryDiv);
        });
    }
    
    // Drag and Drop
    initDragAndDrop() {
        document.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('element-item')) {
                e.dataTransfer.setData('text/plain', e.target.dataset.elementType);
                e.dataTransfer.effectAllowed = 'copy';
            }
        });
        
        document.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            
            const dropZone = this.findDropZone(e.target);
            if (dropZone) {
                this.highlightDropZone(dropZone);
            }
        });
        
        document.addEventListener('dragleave', (e) => {
            this.clearDropZoneHighlight();
        });
        
        document.addEventListener('drop', (e) => {
            e.preventDefault();
            this.clearDropZoneHighlight();
            
            const elementType = e.dataTransfer.getData('text/plain');
            const dropZone = this.findDropZone(e.target);
            
            if (elementType && dropZone) {
                this.addElement(elementType, dropZone, e);
            }
        });
    }
    
    findDropZone(target) {
        // Canvas content is the main drop zone
        const canvas = document.querySelector('.canvas-content');
        if (canvas && (canvas.contains(target) || canvas === target)) {
            return canvas;
        }
        
        // Elements with children can also be drop zones
        const container = target.closest('[data-element-id]');
        if (container && this.canHaveChildren(container)) {
            return container;
        }
        
        return null;
    }
    
    canHaveChildren(element) {
        const type = element.dataset.elementType;
        return ['container', 'row', 'column', 'form'].includes(type);
    }
    
    highlightDropZone(dropZone) {
        this.clearDropZoneHighlight();
        dropZone.classList.add('drag-over');
    }
    
    clearDropZoneHighlight() {
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    }
    
    addElement(elementType, container, event) {
        fetch('/admin/api/elements.php?action=get_default&type=' + elementType)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const elementData = data.element;
                    const elementHtml = this.renderElement(elementData);
                    
                    // Insert at cursor position or append
                    const rect = container.getBoundingClientRect();
                    const y = event.clientY - rect.top;
                    
                    const insertPosition = this.findInsertPosition(container, y);
                    if (insertPosition) {
                        insertPosition.insertAdjacentHTML('beforebegin', elementHtml);
                    } else {
                        container.insertAdjacentHTML('beforeend', elementHtml);
                    }
                    
                    this.saveToHistory();
                    this.showNotification('Element berhasil ditambahkan', 'success');
                }
            })
            .catch(error => {
                console.error('Error adding element:', error);
                this.showNotification('Gagal menambahkan element', 'error');
            });
    }
    
    findInsertPosition(container, y) {
        const children = Array.from(container.children);
        
        for (let child of children) {
            const rect = child.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            const childY = rect.top - containerRect.top;
            
            if (y < childY + rect.height / 2) {
                return child;
            }
        }
        
        return null;
    }
    
    renderElement(elementData) {
        const { type, id, properties = {}, styles = {} } = elementData;
        
        const styleStr = Object.entries(styles).map(([key, value]) => {
            const cssKey = key.replace(/([A-Z])/g, '-$1').toLowerCase();
            return `${cssKey}: ${value}`;
        }).join('; ');
        
        switch (type) {
            case 'text':
                return `<p data-element-id="${id}" data-element-type="${type}" style="${styleStr}" class="page-element">${this.escapeHtml(properties.content || 'Teks contoh')}</p>`;
                
            case 'heading':
                const tag = properties.tag || 'h2';
                return `<${tag} data-element-id="${id}" data-element-type="${type}" style="${styleStr}" class="page-element">${this.escapeHtml(properties.content || 'Heading')}</${tag}>`;
                
            case 'image':
                return `<img data-element-id="${id}" data-element-type="${type}" src="${properties.src || '/assets/images/placeholder.jpg'}" alt="${this.escapeHtml(properties.alt || '')}" style="${styleStr}" class="page-element" />`;
                
            case 'button':
                return `<a data-element-id="${id}" data-element-type="${type}" href="${properties.link || '#'}" target="${properties.target || '_self'}" style="${styleStr}" class="page-element btn">${this.escapeHtml(properties.text || 'Tombol')}</a>`;
                
            case 'container':
                return `<div data-element-id="${id}" data-element-type="${type}" style="${styleStr}" class="page-element element-container">
                    <div class="element-placeholder">Drop elements here</div>
                </div>`;
                
            case 'row':
                return `<div data-element-id="${id}" data-element-type="${type}" style="display: flex; gap: 20px; ${styleStr}" class="page-element element-row">
                    <div class="element-placeholder">Drop elements here</div>
                </div>`;
                
            case 'column':
                return `<div data-element-id="${id}" data-element-type="${type}" style="flex: 1; ${styleStr}" class="page-element element-column">
                    <div class="element-placeholder">Drop elements here</div>
                </div>`;
                
            case 'video':
                return `<video data-element-id="${id}" data-element-type="${type}" src="${properties.src || ''}" ${properties.controls ? 'controls' : ''} style="${styleStr}" class="page-element">
                    Your browser does not support the video tag.
                </video>`;
                
            case 'spacer':
                return `<div data-element-id="${id}" data-element-type="${type}" style="height: ${properties.height || '50px'}; ${styleStr}" class="page-element element-spacer"></div>`;
                
            default:
                return `<div data-element-id="${id}" data-element-type="${type}" style="${styleStr}" class="page-element">Unknown element: ${type}</div>`;
        }
    }
    
    // Element Selection
    initElementSelection() {
        document.addEventListener('click', (e) => {
            const element = e.target.closest('[data-element-id]');
            if (element) {
                e.preventDefault();
                e.stopPropagation();
                this.selectElement(element);
            } else {
                this.deselectElement();
            }
        });
        
        document.addEventListener('mouseover', (e) => {
            const element = e.target.closest('[data-element-id]');
            if (element && element !== this.selectedElement) {
                this.highlightElement(element);
            }
        });
        
        document.addEventListener('mouseout', (e) => {
            this.clearElementHighlight();
        });
    }
    
    selectElement(element) {
        this.deselectElement();
        this.selectedElement = element;
        element.classList.add('element-selected');
        this.loadElementProperties(element);
    }
    
    deselectElement() {
        if (this.selectedElement) {
            this.selectedElement.classList.remove('element-selected');
            this.selectedElement = null;
        }
        this.clearPropertyPanel();
    }
    
    highlightElement(element) {
        this.clearElementHighlight();
        element.classList.add('element-hover');
    }
    
    clearElementHighlight() {
        document.querySelectorAll('.element-hover').forEach(el => {
            el.classList.remove('element-hover');
        });
    }
    
    deleteSelectedElement() {
        if (this.selectedElement) {
            this.selectedElement.remove();
            this.selectedElement = null;
            this.clearPropertyPanel();
            this.saveToHistory();
            this.showNotification('Element berhasil dihapus', 'success');
        }
    }
    
    duplicateSelectedElement() {
        if (this.selectedElement) {
            const clone = this.selectedElement.cloneNode(true);
            clone.dataset.elementId = 'element-' + Date.now();
            this.selectedElement.insertAdjacentElement('afterend', clone);
            this.saveToHistory();
            this.showNotification('Element berhasil diduplikasi', 'success');
        }
    }
    
    // Property Panel
    initPropertyPanel() {
        // Property form submission
        document.addEventListener('submit', (e) => {
            if (e.target.matches('.property-form')) {
                e.preventDefault();
                this.updateElementProperties(e.target);
            }
        });
        
        // Real-time property updates
        document.addEventListener('input', (e) => {
            if (e.target.matches('.property-input')) {
                this.updateElementProperty(e.target);
            }
        });
    }
    
    loadElementProperties(element) {
        const type = element.dataset.elementType;
        const id = element.dataset.elementId;
        
        fetch(`/admin/api/properties.php?type=${type}&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderPropertyPanel(data.properties, element);
                }
            })
            .catch(error => console.error('Error loading properties:', error));
    }
    
    renderPropertyPanel(properties, element) {
        const panel = document.querySelector('.properties-panel .panel-content');
        if (!panel) return;
        
        const type = element.dataset.elementType;
        
        panel.innerHTML = `
            <form class="property-form">
                <input type="hidden" name="element_id" value="${element.dataset.elementId}">
                <input type="hidden" name="element_type" value="${type}">
                
                ${this.generatePropertyFields(type, properties, element)}
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100">Update Properties</button>
                </div>
                
                <div class="form-group">
                    <div class="row">
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" onclick="pageBuilder.duplicateSelectedElement()">
                                <i class="fas fa-copy"></i> Duplicate
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-danger w-100" onclick="pageBuilder.deleteSelectedElement()">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        `;
    }
    
    generatePropertyFields(type, properties, element) {
        let fields = '';
        
        // Common properties
        fields += `
            <div class="form-group">
                <label class="form-label">Element ID</label>
                <input type="text" class="form-control" name="id" value="${element.dataset.elementId}" readonly>
            </div>
        `;
        
        // Type-specific properties
        switch (type) {
            case 'text':
                fields += `
                    <div class="form-group">
                        <label class="form-label">Content</label>
                        <textarea class="form-control property-input" name="content" rows="3">${element.textContent}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tag</label>
                        <select class="form-control property-input" name="tag">
                            <option value="p" ${element.tagName.toLowerCase() === 'p' ? 'selected' : ''}>Paragraph</option>
                            <option value="span" ${element.tagName.toLowerCase() === 'span' ? 'selected' : ''}>Span</option>
                            <option value="div" ${element.tagName.toLowerCase() === 'div' ? 'selected' : ''}>Div</option>
                        </select>
                    </div>
                `;
                break;
                
            case 'heading':
                fields += `
                    <div class="form-group">
                        <label class="form-label">Content</label>
                        <input type="text" class="form-control property-input" name="content" value="${element.textContent}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Heading Level</label>
                        <select class="form-control property-input" name="tag">
                            <option value="h1" ${element.tagName.toLowerCase() === 'h1' ? 'selected' : ''}>H1</option>
                            <option value="h2" ${element.tagName.toLowerCase() === 'h2' ? 'selected' : ''}>H2</option>
                            <option value="h3" ${element.tagName.toLowerCase() === 'h3' ? 'selected' : ''}>H3</option>
                            <option value="h4" ${element.tagName.toLowerCase() === 'h4' ? 'selected' : ''}>H4</option>
                            <option value="h5" ${element.tagName.toLowerCase() === 'h5' ? 'selected' : ''}>H5</option>
                            <option value="h6" ${element.tagName.toLowerCase() === 'h6' ? 'selected' : ''}>H6</option>
                        </select>
                    </div>
                `;
                break;
                
            case 'image':
                fields += `
                    <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control property-input" name="src" value="${element.src}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alt Text</label>
                        <input type="text" class="form-control property-input" name="alt" value="${element.alt}">
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary w-100" onclick="pageBuilder.openAssetPicker('${element.dataset.elementId}')">
                            <i class="fas fa-folder-open"></i> Choose from Assets
                        </button>
                    </div>
                `;
                break;
                
            case 'button':
                fields += `
                    <div class="form-group">
                        <label class="form-label">Button Text</label>
                        <input type="text" class="form-control property-input" name="text" value="${element.textContent}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link URL</label>
                        <input type="url" class="form-control property-input" name="link" value="${element.href}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target</label>
                        <select class="form-control property-input" name="target">
                            <option value="_self" ${element.target === '_self' ? 'selected' : ''}>Same Window</option>
                            <option value="_blank" ${element.target === '_blank' ? 'selected' : ''}>New Window</option>
                        </select>
                    </div>
                `;
                break;
                
            case 'video':
                fields += `
                    <div class="form-group">
                        <label class="form-label">Video URL</label>
                        <input type="url" class="form-control property-input" name="src" value="${element.src}">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input property-input" name="controls" ${element.controls ? 'checked' : ''}>
                        <label class="form-check-label">Show Controls</label>
                    </div>
                `;
                break;
        }
        
        // Style properties
        fields += this.generateStyleFields(element);
        
        return fields;
    }
    
    generateStyleFields(element) {
        const computedStyle = window.getComputedStyle(element);
        
        return `
            <h5 class="mt-4 mb-3">Styling</h5>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Width</label>
                        <input type="text" class="form-control property-input" name="style[width]" value="${element.style.width || ''}" placeholder="auto">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Height</label>
                        <input type="text" class="form-control property-input" name="style[height]" value="${element.style.height || ''}" placeholder="auto">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <input type="color" class="form-control property-input" name="style[color]" value="${this.rgbToHex(computedStyle.color) || '#000000'}">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Background</label>
                        <input type="color" class="form-control property-input" name="style[backgroundColor]" value="${this.rgbToHex(computedStyle.backgroundColor) || '#ffffff'}">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Font Size</label>
                <input type="text" class="form-control property-input" name="style[fontSize]" value="${element.style.fontSize || ''}" placeholder="16px">
            </div>
            
            <div class="form-group">
                <label class="form-label">Text Align</label>
                <select class="form-control property-input" name="style[textAlign]">
                    <option value="left" ${computedStyle.textAlign === 'left' ? 'selected' : ''}>Left</option>
                    <option value="center" ${computedStyle.textAlign === 'center' ? 'selected' : ''}>Center</option>
                    <option value="right" ${computedStyle.textAlign === 'right' ? 'selected' : ''}>Right</option>
                    <option value="justify" ${computedStyle.textAlign === 'justify' ? 'selected' : ''}>Justify</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Margin</label>
                <input type="text" class="form-control property-input" name="style[margin]" value="${element.style.margin || ''}" placeholder="0px">
            </div>
            
            <div class="form-group">
                <label class="form-label">Padding</label>
                <input type="text" class="form-control property-input" name="style[padding]" value="${element.style.padding || ''}" placeholder="0px">
            </div>
        `;
    }
    
    updateElementProperty(input) {
        if (!this.selectedElement) return;
        
        const name = input.name;
        const value = input.type === 'checkbox' ? input.checked : input.value;
        
        if (name.startsWith('style[')) {
            // Style property
            const styleProp = name.match(/style\[(.+)\]/)[1];
            this.selectedElement.style[styleProp] = value;
        } else {
            // Regular property
            switch (name) {
                case 'content':
                    this.selectedElement.textContent = value;
                    break;
                case 'src':
                    this.selectedElement.src = value;
                    break;
                case 'alt':
                    this.selectedElement.alt = value;
                    break;
                case 'link':
                    this.selectedElement.href = value;
                    break;
                case 'target':
                    this.selectedElement.target = value;
                    break;
                case 'text':
                    this.selectedElement.textContent = value;
                    break;
                case 'controls':
                    this.selectedElement.controls = value;
                    break;
            }
        }
    }
    
    updateElementProperties(form) {
        const formData = new FormData(form);
        
        // Apply all changes
        for (let [name, value] of formData.entries()) {
            const input = form.querySelector(`[name="${name}"]`);
            if (input) {
                this.updateElementProperty(input);
            }
        }
        
        this.saveToHistory();
        this.showNotification('Properties updated successfully', 'success');
    }
    
    clearPropertyPanel() {
        const panel = document.querySelector('.properties-panel .panel-content');
        if (panel) {
            panel.innerHTML = '<p class="text-center">Select an element to edit properties</p>';
        }
    }
    
    // Toolbar
    initToolbar() {
        // Save button
        const saveBtn = document.querySelector('[data-action="save"]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.save());
        }
        
        // Preview button
        const previewBtn = document.querySelector('[data-action="preview"]');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this.preview());
        }
        
        // Undo button
        const undoBtn = document.querySelector('[data-action="undo"]');
        if (undoBtn) {
            undoBtn.addEventListener('click', () => this.undo());
        }
        
        // Redo button
        const redoBtn = document.querySelector('[data-action="redo"]');
        if (redoBtn) {
            redoBtn.addEventListener('click', () => this.redo());
        }
        
        // Clear button
        const clearBtn = document.querySelector('[data-action="clear"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clear());
        }
    }
    
    // Keyboard Shortcuts
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 's':
                        e.preventDefault();
                        this.save();
                        break;
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.redo();
                        } else {
                            this.undo();
                        }
                        break;
                    case 'c':
                        if (this.selectedElement) {
                            e.preventDefault();
                            this.copyElement();
                        }
                        break;
                    case 'v':
                        if (this.clipboard) {
                            e.preventDefault();
                            this.pasteElement();
                        }
                        break;
                    case 'd':
                        if (this.selectedElement) {
                            e.preventDefault();
                            this.duplicateSelectedElement();
                        }
                        break;
                }
            } else if (e.key === 'Delete' && this.selectedElement) {
                e.preventDefault();
                this.deleteSelectedElement();
            }
        });
    }
    
    // History Management
    saveToHistory() {
        const content = this.getPageContent();
        
        // Remove future history if we're not at the end
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }
        
        this.history.push(JSON.stringify(content));
        
        // Limit history size
        if (this.history.length > this.maxHistory) {
            this.history.shift();
        } else {
            this.historyIndex++;
        }
        
        this.updateUndoRedoButtons();
    }
    
    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            const content = JSON.parse(this.history[this.historyIndex]);
            this.setPageContent(content);
            this.updateUndoRedoButtons();
            this.showNotification('Undo successful', 'success');
        }
    }
    
    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            const content = JSON.parse(this.history[this.historyIndex]);
            this.setPageContent(content);
            this.updateUndoRedoButtons();
            this.showNotification('Redo successful', 'success');
        }
    }
    
    updateUndoRedoButtons() {
        const undoBtn = document.querySelector('[data-action="undo"]');
        const redoBtn = document.querySelector('[data-action="redo"]');
        
        if (undoBtn) {
            undoBtn.disabled = this.historyIndex <= 0;
        }
        
        if (redoBtn) {
            redoBtn.disabled = this.historyIndex >= this.history.length - 1;
        }
    }
    
    // Content Management
    getPageContent() {
        const canvas = document.querySelector('.canvas-content');
        if (!canvas) return [];
        
        return Array.from(canvas.children).map(element => {
            return this.elementToObject(element);
        });
    }
    
    elementToObject(element) {
        const obj = {
            type: element.dataset.elementType,
            id: element.dataset.elementId,
            properties: this.getElementProperties(element),
            styles: this.getElementStyles(element),
            children: []
        };
        
        // Get children for container elements
        if (this.canHaveChildren(element)) {
            obj.children = Array.from(element.children)
                .filter(child => child.dataset.elementId)
                .map(child => this.elementToObject(child));
        }
        
        return obj;
    }
    
    getElementProperties(element) {
        const type = element.dataset.elementType;
        const properties = {};
        
        switch (type) {
            case 'text':
            case 'heading':
                properties.content = element.textContent;
                properties.tag = element.tagName.toLowerCase();
                break;
            case 'image':
                properties.src = element.src;
                properties.alt = element.alt;
                break;
            case 'button':
                properties.text = element.textContent;
                properties.link = element.href;
                properties.target = element.target;
                break;
            case 'video':
                properties.src = element.src;
                properties.controls = element.controls;
                break;
        }
        
        return properties;
    }
    
    getElementStyles(element) {
        const styles = {};
        const style = element.style;
        
        for (let i = 0; i < style.length; i++) {
            const property = style[i];
            const value = style.getPropertyValue(property);
            if (value) {
                // Convert kebab-case to camelCase
                const camelCase = property.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
                styles[camelCase] = value;
            }
        }
        
        return styles;
    }
    
    setPageContent(content) {
        const canvas = document.querySelector('.canvas-content');
        if (!canvas) return;
        
        canvas.innerHTML = '';
        
        content.forEach(elementData => {
            const html = this.renderElement(elementData);
            canvas.insertAdjacentHTML('beforeend', html);
        });
        
        this.deselectElement();
    }
    
    // Actions
    save() {
        const pageId = this.getPageId();
        const content = this.getPageContent();
        
        const data = {
            action: 'save',
            page_id: pageId,
            content: content
        };
        
        this.showLoading('Saving...');
        
        fetch('/admin/api/pages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoading();
            if (data.success) {
                this.showNotification('Page saved successfully', 'success');
            } else {
                this.showNotification('Error saving page: ' + data.error, 'error');
            }
        })
        .catch(error => {
            this.hideLoading();
            console.error('Error:', error);
            this.showNotification('Error saving page', 'error');
        });
    }
    
    autoSave() {
        const pageId = this.getPageId();
        if (!pageId) return;
        
        const content = this.getPageContent();
        
        fetch('/admin/api/pages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'autosave',
                page_id: pageId,
                content: content
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Auto-saved at', new Date().toLocaleTimeString());
            }
        })
        .catch(error => console.error('Auto-save error:', error));
    }
    
    preview() {
        const pageId = this.getPageId();
        const previewUrl = `/page.php?id=${pageId}&preview=1`;
        window.open(previewUrl, '_blank');
    }
    
    clear() {
        if (confirm('Are you sure you want to clear all content? This cannot be undone.')) {
            const canvas = document.querySelector('.canvas-content');
            if (canvas) {
                canvas.innerHTML = '';
                this.deselectElement();
                this.saveToHistory();
                this.showNotification('Content cleared', 'success');
            }
        }
    }
    
    copyElement() {
        if (this.selectedElement) {
            this.clipboard = this.elementToObject(this.selectedElement);
            this.showNotification('Element copied to clipboard', 'success');
        }
    }
    
    pasteElement() {
        if (this.clipboard) {
            const canvas = document.querySelector('.canvas-content');
            if (canvas) {
                // Generate new ID for pasted element
                this.clipboard.id = 'element-' + Date.now();
                const html = this.renderElement(this.clipboard);
                canvas.insertAdjacentHTML('beforeend', html);
                this.saveToHistory();
                this.showNotification('Element pasted', 'success');
            }
        }
    }
    
    // Asset Picker
    openAssetPicker(elementId) {
        // This would open a modal with asset library
        // For now, we'll show a simple prompt
        const url = prompt('Enter image URL:');
        if (url) {
            const element = document.querySelector(`[data-element-id="${elementId}"]`);
            if (element) {
                element.src = url;
                this.saveToHistory();
            }
        }
    }
    
    // Utility Functions
    getPageId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || urlParams.get('page_id');
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    rgbToHex(rgb) {
        if (!rgb || rgb === 'rgba(0, 0, 0, 0)') return '#ffffff';
        
        const match = rgb.match(/\d+/g);
        if (!match) return '#000000';
        
        const r = parseInt(match[0]);
        const g = parseInt(match[1]);
        const b = parseInt(match[2]);
        
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    
    showNotification(message, type = 'info') {
        // Simple notification system
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    
    showLoading(message = 'Loading...') {
        let loader = document.querySelector('.page-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'page-loader';
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                color: white;
                font-size: 18px;
            `;
            document.body.appendChild(loader);
        }
        
        loader.innerHTML = `
            <div style="text-align: center;">
                <div class="loading"></div>
                <div style="margin-top: 1rem;">${message}</div>
            </div>
        `;
        loader.style.display = 'flex';
    }
    
    hideLoading() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pageBuilder = new PageBuilder();
});

// Animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .page-element {
        min-height: 20px;
        position: relative;
    }
    
    .element-placeholder {
        padding: 2rem;
        border: 2px dashed #ccc;
        color: #999;
        text-align: center;
        border-radius: 6px;
        margin: 1rem 0;
    }
    
    .element-container:not(:empty) .element-placeholder,
    .element-row:not(:empty) .element-placeholder,
    .element-column:not(:empty) .element-placeholder {
        display: none;
    }
`;
document.head.appendChild(style);

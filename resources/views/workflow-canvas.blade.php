<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>n8n Clone - Workflow Canvas</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            overflow: hidden;
        }

        .header {
            height: 50px;
            background-color: #ffffff;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 600;
        }

        .toolbar {
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 15px;
        }

        .tool-button {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: #ffffff;
            color: #374151;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .tool-button:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .tool-button.active {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }

        .main-content {
            display: flex;
            height: calc(100vh - 110px);
        }

        .sidebar {
            width: 300px;
            background-color: #ffffff;
            border-right: 1px solid #e1e5e9;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #e1e5e9;
            font-weight: 600;
            color: #1a1a1a;
        }

        .node-palette {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .node-category {
            margin-bottom: 20px;
        }

        .category-title {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .node-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: grab;
            margin-bottom: 4px;
            transition: background-color 0.2s;
        }

        .node-item:hover {
            background-color: #f3f4f6;
        }

        .node-icon {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #ffffff;
        }

        .node-label {
            font-size: 14px;
            color: #374151;
            flex: 1;
        }

        .canvas-container {
            flex: 1;
            position: relative;
            background-color: #fafafa;
        }

        .canvas {
            width: 100%;
            height: 100%;
        }

        .properties-panel {
            width: 300px;
            background-color: #ffffff;
            border-left: 1px solid #e1e5e9;
            display: flex;
            flex-direction: column;
        }

        .properties-header {
            padding: 16px;
            border-bottom: 1px solid #e1e5e9;
            font-weight: 600;
            color: #1a1a1a;
        }

        .properties-content {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
        }

        .property-group {
            margin-bottom: 20px;
        }

        .property-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .property-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }

        .property-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .node {
            border-radius: 8px;
            padding: 12px;
            min-width: 200px;
            cursor: move;
            user-select: none;
        }

        .node-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .node-title {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            flex: 1;
        }

        .node-input, .node-output {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            position: absolute;
        }

        .node-input {
            left: -6px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #10b981;
        }

        .node-output {
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #ef4444;
        }

        .connection-line {
            stroke: #6b7280;
            stroke-width: 2;
            fill: none;
        }

        .context-menu {
            position: absolute;
            background-color: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 150px;
        }

        .context-menu-item {
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            color: #374151;
            transition: background-color 0.2s;
        }

        .context-menu-item:hover {
            background-color: #f3f4f6;
        }

        .context-menu-item.danger {
            color: #ef4444;
        }

        /* Node type colors */
        .node.trigger { background-color: #fef3c7; border: 1px solid #f59e0b; }
        .node.action { background-color: #dbeafe; border: 1px solid #3b82f6; }
        .node.transform { background-color: #f0fdf4; border: 1px solid #10b981; }
        .node.logic { background-color: #fef3c7; border: 1px solid #f59e0b; }

        .node-icon.trigger { background-color: #f59e0b; }
        .node-icon.action { background-color: #3b82f6; }
        .node-icon.transform { background-color: #10b981; }
        .node-icon.logic { background-color: #f59e0b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>n8n Clone - Workflow Canvas</h1>
    </div>

    <div class="toolbar">
        <button class="tool-button" id="saveBtn">Save</button>
        <button class="tool-button" id="loadBtn">Load</button>
        <button class="tool-button" id="executeBtn">Execute</button>
        <button class="tool-button" id="clearBtn">Clear</button>
        <div style="margin-left: auto;">
            <span id="status">Ready</span>
        </div>
    </div>

    <div class="main-content">
        <div class="sidebar">
            <div class="sidebar-header">Node Palette</div>
            <div class="node-palette" id="nodePalette">
                <!-- Node categories will be populated by JavaScript -->
            </div>
        </div>

        <div class="canvas-container">
            <canvas id="workflowCanvas" class="canvas"></canvas>
        </div>

        <div class="properties-panel">
            <div class="properties-header">Properties</div>
            <div class="properties-content" id="propertiesPanel">
                <p>Select a node to edit its properties</p>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu" style="display: none;">
        <div class="context-menu-item" id="deleteNode">Delete Node</div>
        <div class="context-menu-item" id="duplicateNode">Duplicate Node</div>
    </div>

    <script>
        class WorkflowCanvas {
            constructor() {
                this.canvas = null;
                this.nodes = [];
                this.connections = [];
                this.selectedNode = null;
                this.draggedNode = null;
                this.connectionMode = false;
                this.connectionStart = null;
                this.tempLine = null;

                this.nodeTypes = {
                    trigger: [
                        { id: 'webhookTrigger', name: 'Webhook', icon: 'W' },
                        { id: 'scheduleTrigger', name: 'Schedule', icon: 'S' }
                    ],
                    action: [
                        { id: 'httpRequest', name: 'HTTP Request', icon: 'H' },
                        { id: 'email', name: 'Send Email', icon: 'E' },
                        { id: 'databaseQuery', name: 'Database Query', icon: 'D' }
                    ],
                    transform: [
                        { id: 'dataTransformation', name: 'Data Transform', icon: 'T' }
                    ],
                    logic: [
                        { id: 'switch', name: 'Switch', icon: 'S' },
                        { id: 'loop', name: 'Loop', icon: 'L' }
                    ]
                };

                this.init();
            }

            init() {
                this.canvas = new fabric.Canvas('workflowCanvas', {
                    backgroundColor: '#fafafa',
                    selection: true
                });

                this.setupEventListeners();
                this.renderNodePalette();
                this.setupToolbar();
                this.updateStatus('Canvas initialized');
            }

            setupEventListeners() {
                // Canvas events
                this.canvas.on('mouse:down', (options) => this.handleMouseDown(options));
                this.canvas.on('mouse:move', (options) => this.handleMouseMove(options));
                this.canvas.on('mouse:up', (options) => this.handleMouseUp(options));

                // Context menu
                document.addEventListener('contextmenu', (e) => this.handleContextMenu(e));
                document.addEventListener('click', () => this.hideContextMenu());

                // Keyboard events
                document.addEventListener('keydown', (e) => this.handleKeyDown(e));
            }

            renderNodePalette() {
                const palette = document.getElementById('nodePalette');

                Object.keys(this.nodeTypes).forEach(category => {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'node-category';

                    const title = document.createElement('div');
                    title.className = 'category-title';
                    title.textContent = category.charAt(0).toUpperCase() + category.slice(1);
                    categoryDiv.appendChild(title);

                    this.nodeTypes[category].forEach(nodeType => {
                        const nodeItem = document.createElement('div');
                        nodeItem.className = 'node-item';
                        nodeItem.draggable = true;
                        nodeItem.dataset.nodeType = nodeType.id;
                        nodeItem.dataset.category = category;

                        const icon = document.createElement('div');
                        icon.className = `node-icon ${category}`;
                        icon.textContent = nodeType.icon;

                        const label = document.createElement('div');
                        label.className = 'node-label';
                        label.textContent = nodeType.name;

                        nodeItem.appendChild(icon);
                        nodeItem.appendChild(label);

                        nodeItem.addEventListener('dragstart', (e) => this.handleDragStart(e, nodeType, category));

                        categoryDiv.appendChild(nodeItem);
                    });

                    palette.appendChild(categoryDiv);
                });
            }

            setupToolbar() {
                document.getElementById('saveBtn').addEventListener('click', () => this.saveWorkflow());
                document.getElementById('loadBtn').addEventListener('click', () => this.loadWorkflow());
                document.getElementById('executeBtn').addEventListener('click', () => this.executeWorkflow());
                document.getElementById('clearBtn').addEventListener('click', () => this.clearCanvas());
            }

            handleDragStart(e, nodeType, category) {
                e.dataTransfer.setData('nodeType', nodeType.id);
                e.dataTransfer.setData('category', category);
                e.dataTransfer.effectAllowed = 'copy';
            }

            handleMouseDown(options) {
                const pointer = this.canvas.getPointer(options.e);

                // Check if we're over a node input/output
                const target = options.target;
                if (target && target.nodeData) {
                    if (options.e.offsetX < target.left + 10) {
                        // Clicked on input
                        this.startConnection(target, 'input');
                    } else if (options.e.offsetX > target.left + target.width - 10) {
                        // Clicked on output
                        this.startConnection(target, 'output');
                    } else {
                        this.selectNode(target);
                    }
                } else {
                    this.clearSelection();
                }
            }

            handleMouseMove(options) {
                if (this.connectionMode && this.connectionStart) {
                    this.updateTempConnection(options);
                }
            }

            handleMouseUp(options) {
                if (this.connectionMode && this.connectionStart) {
                    this.finishConnection(options);
                }
            }

            handleContextMenu(e) {
                e.preventDefault();
                const contextMenu = document.getElementById('contextMenu');

                if (this.selectedNode) {
                    contextMenu.style.left = e.pageX + 'px';
                    contextMenu.style.top = e.pageY + 'px';
                    contextMenu.style.display = 'block';

                    document.getElementById('deleteNode').onclick = () => this.deleteNode();
                    document.getElementById('duplicateNode').onclick = () => this.duplicateNode();
                }
            }

            hideContextMenu() {
                document.getElementById('contextMenu').style.display = 'none';
            }

            handleKeyDown(e) {
                if (e.key === 'Delete' && this.selectedNode) {
                    this.deleteNode();
                }
            }

            addNode(nodeType, category, x, y) {
                const nodeData = {
                    id: 'node_' + Date.now(),
                    type: nodeType.id,
                    name: nodeType.name,
                    category: category,
                    position: { x, y },
                    properties: this.getDefaultProperties(nodeType.id)
                };

                const node = new fabric.Rect({
                    left: x,
                    top: y,
                    width: 200,
                    height: 80,
                    fill: this.getCategoryColor(category),
                    stroke: this.getCategoryBorderColor(category),
                    strokeWidth: 1,
                    rx: 8,
                    ry: 8,
                    selectable: true
                });

                // Add node data
                node.nodeData = nodeData;

                // Add text
                const text = new fabric.Text(nodeType.name, {
                    left: x + 40,
                    top: y + 25,
                    fontSize: 14,
                    fill: '#1a1a1a',
                    fontWeight: 'bold'
                });

                // Add input circle
                const inputCircle = new fabric.Circle({
                    left: x - 6,
                    top: y + 35,
                    radius: 6,
                    fill: '#10b981',
                    selectable: false
                });

                // Add output circle
                const outputCircle = new fabric.Circle({
                    left: x + 200 - 6,
                    top: y + 35,
                    radius: 6,
                    fill: '#ef4444',
                    selectable: false
                });

                const group = new fabric.Group([node, text, inputCircle, outputCircle], {
                    left: x,
                    top: y,
                    selectable: true,
                    hasControls: false
                });

                group.nodeData = nodeData;

                this.canvas.add(group);
                this.nodes.push(group);

                this.updateStatus(`Added ${nodeType.name} node`);
                return group;
            }

            getCategoryColor(category) {
                const colors = {
                    trigger: '#fef3c7',
                    action: '#dbeafe',
                    transform: '#f0fdf4',
                    logic: '#fef3c7'
                };
                return colors[category] || '#ffffff';
            }

            getCategoryBorderColor(category) {
                const colors = {
                    trigger: '#f59e0b',
                    action: '#3b82f6',
                    transform: '#10b981',
                    logic: '#f59e0b'
                };
                return colors[category] || '#d1d5db';
            }

            getDefaultProperties(nodeType) {
                const defaults = {
                    webhookTrigger: { path: '/webhook', method: 'POST' },
                    scheduleTrigger: { scheduleType: 'interval', interval: 60 },
                    httpRequest: { method: 'GET', url: '' },
                    email: { to: '', subject: '', body: '' },
                    databaseQuery: { query: '', connection: 'default' },
                    dataTransformation: { operation: 'set', targetPath: '' },
                    switch: { mode: 'single', conditions: [] },
                    loop: { loopType: 'array', maxIterations: 100 }
                };
                return defaults[nodeType] || {};
            }

            selectNode(node) {
                if (this.selectedNode) {
                    this.selectedNode.set('strokeWidth', 1);
                }

                this.selectedNode = node;
                node.set('strokeWidth', 3);
                node.set('stroke', '#3b82f6');

                this.canvas.renderAll();
                this.showNodeProperties(node);
            }

            clearSelection() {
                if (this.selectedNode) {
                    this.selectedNode.set('strokeWidth', 1);
                    this.selectedNode.set('stroke', this.getCategoryBorderColor(this.selectedNode.nodeData.category));
                    this.selectedNode = null;
                }

                this.canvas.renderAll();
                this.clearPropertiesPanel();
            }

            showNodeProperties(node) {
                const panel = document.getElementById('propertiesPanel');
                const nodeData = node.nodeData;

                panel.innerHTML = `
                    <div class="property-group">
                        <label class="property-label">Node Name</label>
                        <input type="text" class="property-input" value="${nodeData.name}" id="nodeName">
                    </div>
                    <div class="property-group">
                        <label class="property-label">Node Type</label>
                        <input type="text" class="property-input" value="${nodeData.type}" readonly>
                    </div>
                `;

                // Add property inputs based on node type
                Object.keys(nodeData.properties).forEach(key => {
                    const value = nodeData.properties[key];
                    const inputType = typeof value === 'boolean' ? 'checkbox' :
                                    typeof value === 'number' ? 'number' : 'text';

                    const checked = value === true ? 'checked' : '';
                    const inputValue = typeof value === 'boolean' ? '' : value;

                    panel.innerHTML += `
                        <div class="property-group">
                            <label class="property-label">${key}</label>
                            <input type="${inputType}" class="property-input" value="${inputValue}" ${checked} id="prop_${key}">
                        </div>
                    `;
                });

                // Add save button
                panel.innerHTML += `
                    <button class="tool-button" id="saveProperties" style="width: 100%; margin-top: 20px;">Save Properties</button>
                `;

                document.getElementById('saveProperties').addEventListener('click', () => this.saveNodeProperties());
            }

            saveNodeProperties() {
                if (!this.selectedNode) return;

                const nodeData = this.selectedNode.nodeData;

                // Update name
                const newName = document.getElementById('nodeName').value;
                nodeData.name = newName;

                // Update properties
                Object.keys(nodeData.properties).forEach(key => {
                    const element = document.getElementById(`prop_${key}`);
                    if (element) {
                        if (element.type === 'checkbox') {
                            nodeData.properties[key] = element.checked;
                        } else if (element.type === 'number') {
                            nodeData.properties[key] = parseFloat(element.value) || 0;
                        } else {
                            nodeData.properties[key] = element.value;
                        }
                    }
                });

                this.updateStatus('Properties saved');
            }

            clearPropertiesPanel() {
                document.getElementById('propertiesPanel').innerHTML = '<p>Select a node to edit its properties</p>';
            }

            startConnection(node, type) {
                this.connectionMode = true;
                this.connectionStart = { node, type };

                this.updateStatus(`Connecting from ${type}`);
            }

            updateTempConnection(options) {
                // Implementation for temporary connection line
            }

            finishConnection(options) {
                // Implementation for finishing connection
                this.connectionMode = false;
                this.connectionStart = null;
                this.updateStatus('Connection mode ended');
            }

            deleteNode() {
                if (!this.selectedNode) return;

                this.canvas.remove(this.selectedNode);
                this.nodes = this.nodes.filter(node => node !== this.selectedNode);
                this.selectedNode = null;
                this.clearPropertiesPanel();

                this.updateStatus('Node deleted');
            }

            duplicateNode() {
                if (!this.selectedNode) return;

                const nodeData = { ...this.selectedNode.nodeData };
                nodeData.id = 'node_' + Date.now();
                nodeData.position.x += 50;
                nodeData.position.y += 50;

                const newNode = this.addNode(
                    { id: nodeData.type, name: nodeData.name },
                    nodeData.category,
                    nodeData.position.x,
                    nodeData.position.y
                );

                this.selectNode(newNode);
                this.updateStatus('Node duplicated');
            }

            saveWorkflow() {
                const workflowData = {
                    nodes: this.nodes.map(node => ({
                        id: node.nodeData.id,
                        type: node.nodeData.type,
                        name: node.nodeData.name,
                        position: node.nodeData.position,
                        properties: node.nodeData.properties
                    })),
                    connections: this.connections
                };

                localStorage.setItem('workflow', JSON.stringify(workflowData));
                this.updateStatus('Workflow saved to local storage');
            }

            loadWorkflow() {
                const workflowData = localStorage.getItem('workflow');
                if (!workflowData) {
                    this.updateStatus('No saved workflow found');
                    return;
                }

                this.clearCanvas();

                const data = JSON.parse(workflowData);
                data.nodes.forEach(nodeData => {
                    const nodeType = { id: nodeData.type, name: nodeData.name };
                    this.addNode(nodeType, 'action', nodeData.position.x, nodeData.position.y);
                });

                this.updateStatus('Workflow loaded from local storage');
            }

            executeWorkflow() {
                this.updateStatus('Workflow execution started (not implemented yet)');
            }

            clearCanvas() {
                this.canvas.clear();
                this.canvas.backgroundColor = '#fafafa';
                this.nodes = [];
                this.connections = [];
                this.selectedNode = null;
                this.clearPropertiesPanel();
                this.updateStatus('Canvas cleared');
            }

            updateStatus(message) {
                document.getElementById('status').textContent = message;
            }
        }

        // Initialize the workflow canvas when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            new WorkflowCanvas();
        });

        // Handle drag and drop from palette
        document.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        document.addEventListener('drop', (e) => {
            e.preventDefault();
            const nodeType = e.dataTransfer.getData('nodeType');
            const category = e.dataTransfer.getData('category');

            if (nodeType && category) {
                const canvas = document.getElementById('workflowCanvas');
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                // This would need to be connected to the WorkflowCanvas instance
                console.log(`Dropped ${nodeType} at (${x}, ${y})`);
            }
        });
    </script>
</body>
</html>

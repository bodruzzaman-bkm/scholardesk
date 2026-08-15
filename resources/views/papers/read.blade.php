<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Reading: {{ Str::limit($paper->title, 50) }}
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('papers.show', $paper->id) }}" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                    &larr; Back to Details
                </a>
            </div>
        </div>
    </x-slot>

    <!-- PDF.js Library & Default CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf_viewer.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    </script>

    <!-- SVG Overlay for Connecting Lines -->
    <svg id="connections-svg" class="fixed top-0 left-0 w-full h-full pointer-events-none z-50"></svg>

    <div class="py-6 h-[calc(100vh-80px)] flex flex-col">
        <div class="max-w-screen-2xl mx-auto sm:px-6 lg:px-8 w-full flex-grow flex space-x-4 h-full relative">
            
            <!-- Left Side: PDF Viewer -->
            <div class="w-2/3 bg-white dark:bg-gray-800 rounded-lg shadow-sm flex flex-col overflow-hidden">
                <!-- Toolbar -->
                <div class="bg-gray-100 dark:bg-gray-700 p-3 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center z-10 flex-wrap gap-2">
                    
                    <!-- Pagination Controls -->
                    <div class="flex items-center space-x-2">
                        <button id="prev-page" class="px-3 py-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200">&larr; Prev</button>
                        <span class="text-sm font-medium dark:text-white">Page <span id="page-num">1</span> of <span id="page-count">--</span></span>
                        <button id="next-page" class="px-3 py-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200">Next &rarr;</button>
                    </div>

                    <!-- Zoom Controls -->
                    <div class="flex items-center space-x-2 border-l border-r border-gray-300 dark:border-gray-600 px-3">
                        <button id="zoom-out" class="px-3 py-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200" title="Zoom Out">-</button>
                        <span id="zoom-level" class="text-sm font-medium dark:text-white w-12 text-center">150%</span>
                        <button id="zoom-in" class="px-3 py-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200" title="Zoom In">+</button>
                        <button id="zoom-fit" class="px-3 py-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200 text-sm font-medium" title="Fit to Width">Fit</button>
                    </div>

                    <!-- Color Picker -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium dark:text-white">Highlight Color:</label>
                        <input type="color" id="highlight-color" value="#FFEB3B" class="h-8 w-8 rounded cursor-pointer border-0 shadow-sm">
                    </div>
                </div>

                <!-- Canvas & Text Layer Container -->
                <div id="pdf-scroll-container" class="flex-grow overflow-auto bg-gray-200 dark:bg-gray-900 flex justify-center p-4 relative">
                    <div id="pdf-page-wrapper" style="position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: auto;">
                        <canvas id="pdf-canvas" class="bg-white" style="display: block;"></canvas>
                        <!-- Text layer for selection -->
                        <div id="text-layer" class="textLayer"></div>
                        <!-- Container for Permanent Highlights overlay -->
                        <div id="highlights-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Margin Notes -->
            <div class="w-1/3 bg-white dark:bg-gray-800 rounded-lg shadow-sm flex flex-col h-full overflow-hidden relative" id="sidebar-container">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-indigo-50 dark:bg-indigo-900 z-10">
                    <h3 class="text-lg font-bold text-indigo-800 dark:text-indigo-200">Margin Notes & Highlights</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">Select text on the PDF to create a new highlight.</p>
                </div>
                
                <!-- Highlights List -->
                <div id="highlights-list" class="p-4 overflow-y-auto flex-grow space-y-4 relative z-0">
                    <p class="text-sm text-gray-500 text-center py-4" id="loading-text">Loading highlights...</p>
                </div>
            </div>

        </div>
    </div>

    <style>
        .textLayer { position: absolute; left: 0; top: 0; right: 0; bottom: 0; overflow: hidden; line-height: 1.0; }
        .textLayer > span { color: transparent; position: absolute; white-space: pre; cursor: text; transform-origin: 0% 0%; }
        .textLayer ::selection { background: rgba(0, 100, 255, 0.3); }
        .pdf-highlight { position: absolute; mix-blend-mode: multiply; opacity: 0.4; }
        
        @media (prefers-color-scheme: dark) {
            .pdf-highlight { mix-blend-mode: normal; opacity: 0.3; }
        }
    </style>

    <script>
        const url = "{{ asset('storage/' . $paper->file_path) }}";
        const paperId = {{ $paper->id }};
        const csrfToken = "{{ csrf_token() }}";

        let pdfDoc = null, pageNum = 1, pageIsRendering = false, pageNumIsPending = null;
        let currentHighlights = [];
        let viewportScale = 1.5; // Default Zoom Level

        const canvas = document.getElementById('pdf-canvas'),
              ctx = canvas.getContext('2d'),
              textLayerDiv = document.getElementById('text-layer'),
              pageWrapper = document.getElementById('pdf-page-wrapper'),
              highlightsOverlay = document.getElementById('highlights-overlay'),
              svgCanvas = document.getElementById('connections-svg'),
              scrollContainer = document.getElementById('pdf-scroll-container');

        // ==== ZOOM LOGIC ====
        const zoomLevelDisplay = document.getElementById('zoom-level');

        function updateZoomDisplay() {
            zoomLevelDisplay.textContent = Math.round(viewportScale * 100) + '%';
        }

        document.getElementById('zoom-in').addEventListener('click', () => {
            if (viewportScale >= 3.0) return; // Max zoom 300%
            viewportScale += 0.25;
            updateZoomDisplay();
            queueRenderPage(pageNum);
        });

        document.getElementById('zoom-out').addEventListener('click', () => {
            if (viewportScale <= 0.5) return; // Min zoom 50%
            viewportScale -= 0.25;
            updateZoomDisplay();
            queueRenderPage(pageNum);
        });

        document.getElementById('zoom-fit').addEventListener('click', () => {
            pdfDoc.getPage(pageNum).then(page => {
                const unscaledViewport = page.getViewport({ scale: 1.0 });
                // Calculate width based on container width minus some padding (40px)
                const containerWidth = scrollContainer.clientWidth - 40;
                viewportScale = containerWidth / unscaledViewport.width;
                
                // Cap the limits just in case
                if (viewportScale > 3.0) viewportScale = 3.0;
                if (viewportScale < 0.5) viewportScale = 0.5;
                
                updateZoomDisplay();
                queueRenderPage(pageNum);
            });
        });
        // ====================

        // Render Page
        const renderPage = num => {
            pageIsRendering = true;
            pdfDoc.getPage(num).then(page => {
                const viewport = page.getViewport({ scale: viewportScale });
                
                pageWrapper.style.width = `${viewport.width}px`;
                pageWrapper.style.height = `${viewport.height}px`;
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderCtx = { canvasContext: ctx, viewport: viewport };

                page.render(renderCtx).promise.then(() => {
                    return page.getTextContent();
                }).then(textContent => {
                    textLayerDiv.innerHTML = ''; 
                    textLayerDiv.style.setProperty('--scale-factor', viewport.scale);

                    pdfjsLib.renderTextLayer({
                        textContent: textContent,
                        container: textLayerDiv,
                        viewport: viewport,
                        textDivs: []
                    });

                    pageIsRendering = false;
                    renderPdfHighlights(); // Render color boxes on PDF
                    drawConnections(); // Draw lines
                    
                    if (pageNumIsPending !== null) {
                        renderPage(pageNumIsPending);
                        pageNumIsPending = null;
                    }
                });
            });
            document.getElementById('page-num').textContent = num;
        };

        const queueRenderPage = num => {
            if (pageIsRendering) pageNumIsPending = num;
            else renderPage(num);
        };

        document.getElementById('prev-page').addEventListener('click', () => {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        });

        document.getElementById('next-page').addEventListener('click', () => {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        });

        // Load PDF
        pdfjsLib.getDocument(url).promise.then(pdfDoc_ => {
            pdfDoc = pdfDoc_;
            document.getElementById('page-count').textContent = pdfDoc.numPages;
            fetchHighlights(); // Fetch data first
        });

        // === HIGHLIGHTS & NOTES LOGIC ===
        
        function fetchHighlights() {
            fetch(`/papers/${paperId}/highlights`)
                .then(res => res.json())
                .then(data => {
                    currentHighlights = data;
                    renderSidebarHighlights();
                    renderPage(pageNum); 
                });
        }

        // 1. Render Sidebar Cards
        function renderSidebarHighlights() {
            const list = document.getElementById('highlights-list');
            list.innerHTML = '';

            if(currentHighlights.length === 0) {
                list.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No notes yet.</p>';
                return;
            }

            currentHighlights.forEach(hl => {
                const item = document.createElement('div');
                item.id = `note-card-${hl.id}`;
                item.className = 'p-3 rounded border dark:border-gray-600 bg-white dark:bg-gray-700 shadow-sm relative transition hover:shadow-md cursor-pointer';
                item.innerHTML = `
                    <div class="w-full h-2 rounded-t absolute top-0 left-0" style="background-color: ${hl.color}"></div>
                    <p class="text-sm italic text-gray-600 dark:text-gray-300 mt-2 border-l-2 pl-2">"${hl.text}"</p>
                    ${hl.note ? `<p class="text-sm font-medium text-gray-800 dark:text-gray-100 mt-2 bg-gray-50 dark:bg-gray-800 p-2 rounded">📝 ${hl.note}</p>` : ''}
                    <div class="mt-2 flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">Page ${hl.position.page}</span>
                        <button onclick="deleteHighlight(event, ${hl.id})" class="text-red-500 hover:underline px-2 py-1 bg-red-50 dark:bg-red-900 rounded">Delete</button>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    if (pageNum !== hl.position.page) {
                        pageNum = hl.position.page;
                        queueRenderPage(pageNum);
                    }
                });
                list.appendChild(item);
            });
            drawConnections();
        }

        // 2. Render Color Boxes on the PDF Page
        function renderPdfHighlights() {
            highlightsOverlay.innerHTML = '';
            const highlightsOnThisPage = currentHighlights.filter(hl => hl.position.page === pageNum);

            highlightsOnThisPage.forEach(hl => {
                if (hl.position.rects && hl.position.rects.length > 0) {
                    hl.position.rects.forEach((rect, index) => {
                        const hlDiv = document.createElement('div');
                        hlDiv.className = 'pdf-highlight';
                        if (index === 0) hlDiv.id = `pdf-hl-${hl.id}`; 
                        
                        hlDiv.style.backgroundColor = hl.color;
                        // Multiply stored original coordinates by current viewportScale
                        hlDiv.style.left = (rect.left * viewportScale) + 'px';
                        hlDiv.style.top = (rect.top * viewportScale) + 'px';
                        hlDiv.style.width = (rect.width * viewportScale) + 'px';
                        hlDiv.style.height = (rect.height * viewportScale) + 'px';
                        
                        highlightsOverlay.appendChild(hlDiv);
                    });
                }
            });
        }

        // 3. Draw SVG Connecting Lines
        function drawConnections() {
            svgCanvas.innerHTML = ''; 
            const highlightsOnThisPage = currentHighlights.filter(hl => hl.position.page === pageNum);

            highlightsOnThisPage.forEach(hl => {
                const pdfElement = document.getElementById(`pdf-hl-${hl.id}`);
                const noteElement = document.getElementById(`note-card-${hl.id}`);

                if (pdfElement && noteElement) {
                    const pdfRect = pdfElement.getBoundingClientRect();
                    const noteRect = noteElement.getBoundingClientRect();

                    if (pdfRect.width === 0 || noteRect.width === 0) return;

                    const startX = pdfRect.right;
                    const startY = pdfRect.top + (pdfRect.height / 2);
                    const endX = noteRect.left;
                    const endY = noteRect.top + 20; 

                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    const controlX1 = startX + 50;
                    const controlX2 = endX - 50;
                    
                    const d = `M ${startX} ${startY} C ${controlX1} ${startY}, ${controlX2} ${endY}, ${endX} ${endY}`;
                    
                    path.setAttribute('d', d);
                    path.setAttribute('stroke', hl.color);
                    path.setAttribute('stroke-width', '2');
                    path.setAttribute('fill', 'none');
                    path.setAttribute('opacity', '0.6');

                    svgCanvas.appendChild(path);
                }
            });
        }

        scrollContainer.addEventListener('scroll', drawConnections);
        document.getElementById('highlights-list').addEventListener('scroll', drawConnections);
        window.addEventListener('resize', drawConnections);

        // Capture Selected Text and exact Coordinates
        document.getElementById('text-layer').addEventListener('mouseup', () => {
            setTimeout(() => {
                const selection = window.getSelection();
                const selectedText = selection.toString().trim();
                
                if (selectedText.length > 0) {
                    const range = selection.getRangeAt(0);
                    const rects = Array.from(range.getClientRects());
                    const wrapperRect = pageWrapper.getBoundingClientRect();

                    // Normalize rects by dividing by CURRENT viewportScale to save original scale (1.0)
                    const normalizedRects = rects.map(r => ({
                        left: (r.left - wrapperRect.left) / viewportScale,
                        top: (r.top - wrapperRect.top) / viewportScale,
                        width: r.width / viewportScale,
                        height: r.height / viewportScale
                    }));

                    const note = prompt('Add a margin note for this highlight (optional):');
                    if (note !== null) {
                        saveHighlight(selectedText, note, normalizedRects);
                    } else {
                        selection.removeAllRanges();
                    }
                }
            }, 100);
        });

        // Save Highlight with exact coordinates
        function saveHighlight(text, note, rects) {
            const color = document.getElementById('highlight-color').value;
            const position = { page: pageNum, rects: rects }; 

            fetch(`/papers/${paperId}/highlights`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ color, position, text, note })
            })
            .then(res => res.json())
            .then(() => {
                window.getSelection().removeAllRanges();
                fetchHighlights(); 
            });
        }

        function deleteHighlight(event, id) {
            event.stopPropagation(); // Prevent card click event
            if(confirm('Delete this note?')) {
                fetch(`/highlights/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                }).then(() => fetchHighlights());
            }
        }
    </script>
</x-app-layout>
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Reading: {{ Str::limit($paper->title, 50) }}
            </h2>
            <div class="flex items-center gap-3">
                {{-- Marking a paper read belongs where you finish reading it. --}}
                <x-reading-status :paper="$paper" show-label />

                <a href="{{ route('papers.show', $paper->id) }}" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition whitespace-nowrap">
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

            {{-- Right rail: highlights and the AI assistant, as tabs so both
                 fit beside the page without shrinking the PDF. The highlights
                 pane is only hidden, never removed, because the SVG connector
                 lines measure its cards' positions. --}}
            <div class="w-1/3 bg-white dark:bg-gray-800 rounded-lg shadow-sm flex flex-col h-full overflow-hidden relative"
                 id="sidebar-container"
                 x-data="{ tab: 'highlights' }">

                <div class="border-b border-gray-200 dark:border-gray-700 bg-indigo-50 dark:bg-indigo-900 z-10">
                    <div class="flex">
                        <button type="button" @click="tab = 'highlights'"
                                :class="tab === 'highlights'
                                    ? 'border-indigo-600 text-indigo-800 dark:text-indigo-200'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                class="flex-1 px-4 py-3 text-sm font-semibold border-b-2 transition">
                            Highlights
                        </button>
                        <button type="button" @click="tab = 'ai'"
                                :class="tab === 'ai'
                                    ? 'border-purple-600 text-purple-800 dark:text-purple-200'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                class="flex-1 px-4 py-3 text-sm font-semibold border-b-2 transition">
                            <x-icon name="sparkles" class="w-4 h-4 inline-block -mt-0.5" /> AI assistant
                        </button>
                    </div>
                </div>

                {{-- Highlights pane --}}
                <div x-show="tab === 'highlights'" class="flex flex-col flex-grow overflow-hidden">
                    <p class="px-4 pt-3 text-xs text-gray-600 dark:text-gray-300">
                        Select text on the PDF to create a new highlight.
                    </p>
                    <div id="highlights-list" class="p-4 overflow-y-auto flex-grow space-y-4 relative z-0">
                        <p class="text-sm text-gray-500 text-center py-4" id="loading-text">Loading highlights...</p>
                    </div>
                </div>

                {{-- AI pane: summary and Q&A over this paper, without leaving
                     the reader. --}}
                <div x-show="tab === 'ai'" x-cloak class="overflow-y-auto flex-grow p-4 space-y-4">
                    @if (! $paper->isIndexed())
                        <div class="text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-md p-3">
                            @if ($paper->hasNoExtractableText())
                                This PDF has no extractable text (most likely a scan), so the assistant has nothing
                                to read. You can still highlight and take notes.
                            @else
                                This paper is not indexed yet, so the assistant cannot read it.
                                <form method="POST" action="{{ route('papers.reindex', $paper) }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="underline font-medium">Index it now</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <x-ai.summary :paper="$paper" :configured="$aiConfigured" />

                        <x-ai.chat
                            scope="paper"
                            :model="$paper"
                            :configured="$aiConfigured"
                            :history="$chatHistory"
                            title="Ask about this paper"
                        />
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Panel shown after selecting text, replacing the old blocking prompt().
         It shows the passage, takes an optional margin note, and can be
         dismissed with Escape without losing the selection. --}}
    <div id="capture-panel"
         class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] w-[min(92vw,560px)]
                bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-600 p-4"
         role="dialog" aria-label="New highlight">
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">New highlight</p>

        <blockquote id="capture-quote"
                    class="text-sm italic text-gray-700 dark:text-gray-300 border-l-4 border-amber-300 dark:border-amber-600 pl-3 py-1 mb-3 max-h-24 overflow-y-auto"></blockquote>

        <label for="capture-note" class="sr-only">Margin note</label>
        <textarea id="capture-note" rows="2" maxlength="5000"
                  placeholder="Add a margin note (optional)"
                  class="block w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>

        <div class="mt-3 flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                Colour
                <input type="color" id="capture-color" value="#FFEB3B"
                       class="h-8 w-10 rounded cursor-pointer border-0 bg-transparent">
            </label>

            <div class="flex gap-2">
                <button type="button" id="capture-cancel"
                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:underline">
                    Cancel
                </button>
                <button type="button" id="capture-save"
                        class="px-4 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Save highlight
                </button>
            </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-400">Ctrl/&#8984;+Enter to save &middot; Esc to cancel</p>
    </div>

    {{-- Failures used to be swallowed silently; they now surface here. --}}
    <div id="reader-error" role="alert"
         class="hidden fixed top-20 left-1/2 -translate-x-1/2 z-[70] px-4 py-2 rounded-md shadow-lg
                bg-red-600 text-white text-sm max-w-[90vw]"></div>

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
        
        async function fetchHighlights() {
            const data = await readerFetch(`/papers/${paperId}/highlights`);

            if (data === null) return; // error already surfaced

            currentHighlights = Array.isArray(data) ? data : [];
            renderSidebarHighlights();
            renderPage(pageNum);
        }

        // 1. Render Sidebar Cards
        //
        // Built with DOM APIs and textContent rather than an innerHTML
        // template: the quoted passage comes from the PDF and the margin note
        // is typed by the user, so interpolating either into HTML would let
        // markup in a note execute as script.
        function renderSidebarHighlights() {
            const list = document.getElementById('highlights-list');
            list.innerHTML = '';

            if (currentHighlights.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'text-sm text-gray-500 dark:text-gray-400 text-center py-6';
                empty.textContent = 'No highlights yet. Select text on the PDF to create one.';
                list.appendChild(empty);
                drawConnections();
                return;
            }

            currentHighlights.forEach(hl => {
                const item = document.createElement('div');
                item.id = `note-card-${hl.id}`;
                item.className = 'p-3 pt-4 rounded border dark:border-gray-600 bg-white dark:bg-gray-700 shadow-sm relative transition hover:shadow-md';

                const stripe = document.createElement('div');
                stripe.className = 'w-full h-2 rounded-t absolute top-0 left-0';
                stripe.style.backgroundColor = hl.color;
                item.appendChild(stripe);

                const quote = document.createElement('p');
                quote.className = 'text-sm italic text-gray-600 dark:text-gray-300 mt-1 border-l-2 pl-2 cursor-pointer';
                quote.textContent = `"${hl.text ?? ''}"`;
                quote.title = 'Jump to this highlight';
                quote.addEventListener('click', () => goToHighlight(hl));
                item.appendChild(quote);

                // The margin note, shown as text until the user chooses to edit.
                const noteBox = document.createElement('div');
                noteBox.className = 'mt-2';

                const noteText = document.createElement('p');
                noteText.className = hl.note
                    ? 'text-sm text-gray-800 dark:text-gray-100 bg-gray-50 dark:bg-gray-800 p-2 rounded whitespace-pre-line'
                    : 'text-sm text-gray-400 dark:text-gray-500 italic';
                noteText.textContent = hl.note || 'No margin note.';
                noteBox.appendChild(noteText);
                item.appendChild(noteBox);

                const actions = document.createElement('div');
                actions.className = 'mt-2 flex justify-between items-center text-xs';

                const page = document.createElement('span');
                page.className = 'text-gray-500 font-medium';
                page.textContent = `Page ${hl.position.page}`;
                actions.appendChild(page);

                const buttons = document.createElement('div');
                buttons.className = 'flex gap-2';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'text-indigo-600 dark:text-indigo-400 hover:underline px-2 py-1 rounded';
                editBtn.textContent = hl.note ? 'Edit note' : 'Add note';
                editBtn.addEventListener('click', () => openNoteEditor(item, noteBox, hl));
                buttons.appendChild(editBtn);

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'text-red-500 hover:underline px-2 py-1 bg-red-50 dark:bg-red-900/40 rounded';
                delBtn.textContent = 'Delete';
                delBtn.addEventListener('click', (e) => deleteHighlight(e, hl.id));
                buttons.appendChild(delBtn);

                actions.appendChild(buttons);
                item.appendChild(actions);

                list.appendChild(item);
            });
            drawConnections();
        }

        function goToHighlight(hl) {
            if (pageNum !== hl.position.page) {
                pageNum = hl.position.page;
                queueRenderPage(pageNum);
            }
        }

        // Inline editor for a margin note, replacing the old blocking prompt().
        function openNoteEditor(card, noteBox, hl) {
            if (card.querySelector('textarea')) return; // already open

            noteBox.innerHTML = '';

            const textarea = document.createElement('textarea');
            textarea.rows = 3;
            textarea.maxLength = 5000;
            textarea.value = hl.note || '';
            textarea.placeholder = 'Write a margin note...';
            textarea.className = 'block w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500';
            noteBox.appendChild(textarea);

            const row = document.createElement('div');
            row.className = 'mt-2 flex gap-2 justify-end';

            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'text-xs px-2 py-1 text-gray-600 dark:text-gray-300 hover:underline';
            cancel.textContent = 'Cancel';
            cancel.addEventListener('click', renderSidebarHighlights);
            row.appendChild(cancel);

            const save = document.createElement('button');
            save.type = 'button';
            save.className = 'text-xs px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-700';
            save.textContent = 'Save note';
            save.addEventListener('click', async () => {
                save.disabled = true;
                save.textContent = 'Saving...';
                const ok = await updateHighlight(hl.id, { note: textarea.value });
                if (ok) fetchHighlights();
                else { save.disabled = false; save.textContent = 'Save note'; }
            });
            row.appendChild(save);

            noteBox.appendChild(row);
            textarea.focus();
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

        // ---- Error reporting -------------------------------------------
        // Every request below reports failure. Previously a rejected save was
        // swallowed by `.then(res => res.json())`, so an invalid highlight
        // looked exactly like nothing happening.
        function showReaderError(message) {
            const banner = document.getElementById('reader-error');
            banner.textContent = message;
            banner.classList.remove('hidden');
            clearTimeout(showReaderError._t);
            showReaderError._t = setTimeout(() => banner.classList.add('hidden'), 6000);
        }

        async function readerFetch(url, options = {}) {
            try {
                const res = await fetch(url, {
                    ...options,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                        ...(options.headers || {}),
                    },
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    // 422 carries field-level messages; surface the first one.
                    const detail = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || data.error);
                    showReaderError(detail || `Request failed (${res.status}).`);
                    return null;
                }

                return res.status === 204 ? {} : await res.json();
            } catch (e) {
                showReaderError('Could not reach the server. Your highlight was not saved.');
                return null;
            }
        }

        // ---- Capture selected text and its coordinates -------------------
        document.getElementById('text-layer').addEventListener('mouseup', () => {
            setTimeout(() => {
                const selection = window.getSelection();
                const selectedText = selection.toString().trim();

                if (selectedText.length === 0) return;

                const range = selection.getRangeAt(0);
                const rects = Array.from(range.getClientRects()).filter(r => r.width > 0 && r.height > 0);
                if (rects.length === 0) return;

                const wrapperRect = pageWrapper.getBoundingClientRect();

                // Store unscaled (divide by the zoom in force at capture time)
                // so the highlight lands correctly at any later zoom level.
                const normalizedRects = rects.map(r => ({
                    left: (r.left - wrapperRect.left) / viewportScale,
                    top: (r.top - wrapperRect.top) / viewportScale,
                    width: r.width / viewportScale,
                    height: r.height / viewportScale,
                }));

                openCapturePanel(selectedText, normalizedRects);
            }, 100);
        });

        // A small inline panel instead of a blocking prompt(): it shows the
        // passage being highlighted, allows a colour choice, and can be
        // dismissed without losing the selection.
        function openCapturePanel(text, rects) {
            const panel = document.getElementById('capture-panel');
            const quote = document.getElementById('capture-quote');
            const noteInput = document.getElementById('capture-note');
            const colorInput = document.getElementById('capture-color');

            quote.textContent = text.length > 220 ? text.slice(0, 220) + '…' : text;
            noteInput.value = '';
            colorInput.value = document.getElementById('highlight-color').value;
            panel.classList.remove('hidden');
            noteInput.focus();

            const save = async () => {
                cleanup();
                await saveHighlight(text, noteInput.value, rects, colorInput.value);
            };
            const cancel = () => {
                cleanup();
                window.getSelection().removeAllRanges();
            };
            const onKey = (e) => {
                if (e.key === 'Escape') cancel();
                // Ctrl/Cmd+Enter saves, matching the usual "submit" chord.
                if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) save();
            };

            function cleanup() {
                panel.classList.add('hidden');
                document.getElementById('capture-save').removeEventListener('click', save);
                document.getElementById('capture-cancel').removeEventListener('click', cancel);
                document.removeEventListener('keydown', onKey);
            }

            document.getElementById('capture-save').addEventListener('click', save);
            document.getElementById('capture-cancel').addEventListener('click', cancel);
            document.addEventListener('keydown', onKey);
        }

        // Save Highlight with exact coordinates
        async function saveHighlight(text, note, rects, color) {
            const position = { page: pageNum, rects: rects };

            const created = await readerFetch(`/papers/${paperId}/highlights`, {
                method: 'POST',
                body: JSON.stringify({
                    color: color || document.getElementById('highlight-color').value,
                    position,
                    text,
                    note,
                }),
            });

            window.getSelection().removeAllRanges();
            if (created) fetchHighlights();
        }

        /** Patch a highlight's note or colour. Returns true on success. */
        async function updateHighlight(id, payload) {
            const updated = await readerFetch(`/highlights/${id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });

            return updated !== null;
        }

        async function deleteHighlight(event, id) {
            event.stopPropagation(); // Prevent card click event
            if (!confirm('Delete this highlight and its margin note?')) return;

            const done = await readerFetch(`/highlights/${id}`, { method: 'DELETE' });
            if (done) fetchHighlights();
        }
    </script>
</x-app-layout>
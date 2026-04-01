/**
 * HTML Outline Tool for DevPilot
 */

document.addEventListener('DOMContentLoaded', function() {
    // Debug setup
    const debug = false; // Set to false for production
    function log(msg) {
        if (debug) console.log('HTML Outline: ' + msg);
    }

    log('Initializing...');

    // Get the necessary DOM elements
    const triggerButton = document.getElementById('html-outline-trigger');
    const outlineContainer = document.getElementById('html-outline-container');
    const outlineClose = document.getElementById('html-outline-close');
    const outlineResult = document.getElementById('html-outline-result');
    
    // Check if elements exist
    if (!triggerButton || !outlineContainer || !outlineResult) return;
    
    // Basic button click handler
    triggerButton.addEventListener('click', function(e) {
        log('Button clicked');
        e.preventDefault();
        e.stopPropagation();
        
        // Simple open/close
        if (outlineContainer.classList.contains('active')) {
            outlineContainer.classList.remove('active');
        } else {
            outlineContainer.classList.add('active');
            generateOutline();
        }
    });
    
    // Close button handler
    if (outlineClose) {
        outlineClose.addEventListener('click', function(e) {
            log('Close button clicked');
            e.preventDefault();
            outlineContainer.classList.remove('active');
        });
    }
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && outlineContainer.classList.contains('active')) {
            log('ESC pressed, closing panel');
            outlineContainer.classList.remove('active');
        }
    });
    
    // Function to generate the HTML outline
    function generateOutline() {
        log('Generating outline...');
        outlineResult.innerHTML = '<div class="loading">Analyzing page structure...</div>';
        
        // Wrap in setTimeout to ensure UI updates before heavy processing
        setTimeout(function() {
            try {
                log('Analyzing document...');
                const outline = analyzeDocumentOutline();
                log('Analysis complete, rendering results');
                displayOutlineResults(outline);
            } catch (error) {
                log('ERROR: ' + error.message);
                outlineResult.innerHTML = `<div class="error">Error analyzing outline: ${error.message}</div>`;
                console.error('HTML Outline Error:', error);
            }
        }, 10);
    }
    
    // Function to analyze the document outline
    function analyzeDocumentOutline() {
        // Store outline data
        const outline = {
            sections: [],
            headings: [],
            issues: []
        };
        
        // Find all sectioning elements
        const sectioningElements = document.querySelectorAll('article, aside, nav, section, main, header, footer');
        outline.sections = Array.from(sectioningElements).map(elem => {
            return {
                tagName: elem.tagName.toLowerCase(),
                id: elem.id || null,
                className: elem.className || null,
                headings: Array.from(elem.querySelectorAll('h1, h2, h3, h4, h5, h6')).map(h => ({
                    level: parseInt(h.tagName[1]),
                    text: h.textContent.trim(),
                    element: h
                }))
            };
        });
        
        // Find all headings in order
        const allHeadings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
        let previousLevel = 0;
        
        outline.headings = Array.from(allHeadings).map(h => {
            const level = parseInt(h.tagName[1]);
            const heading = {
                level: level,
                text: h.textContent.trim(),
                element: h,
                hasSkippedLevel: level > previousLevel + 1
            };
            
            if (heading.hasSkippedLevel) {
                outline.issues.push({
                    type: 'skipped_level',
                    message: `Heading level skipped from H${previousLevel} to H${level}`,
                    element: h
                });
            }
            
            previousLevel = level;
            return heading;
        });
        
        // Check for common issues
        if (document.querySelectorAll('h1').length === 0) {
            outline.issues.push({
                type: 'no_h1',
                message: 'Page does not contain an H1 heading',
                element: document.body
            });
        }
        
        if (document.querySelectorAll('h1').length > 1) {
            outline.issues.push({
                type: 'multiple_h1',
                message: 'Page contains multiple H1 headings',
                element: document.querySelectorAll('h1')
            });
        }
        
        return outline;
    }
    
    // Function to display the outline results
    function displayOutlineResults(outline) {
        let html = '';
        
        // Display any issues found
        if (outline.issues.length > 0) {
            html += '<div class="outline-issues">';
            html += '<h3>Potential Issues</h3>';
            html += '<ul class="issues-list">';
            outline.issues.forEach(issue => {
                html += `<li class="issue-item"><span class="issue-type">${issue.type}</span>: ${issue.message}</li>`;
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Display heading hierarchy
        html += '<div class="outline-hierarchy">';
        html += '<h3>Heading Hierarchy</h3>';
        html += '<ul class="heading-list">';
        outline.headings.forEach(heading => {
            let className = heading.hasSkippedLevel ? 'heading-item error' : 'heading-item';
            // Convert indentation from px to rem (20px = 1.25rem)
            html += `<li class="${className}" style="margin-left: ${(heading.level-1) * 1.25}rem">
                <span class="heading-level">H${heading.level}</span>
                <span class="heading-text">${escapeHtml(heading.text)}</span>
            </li>`;
        });
        html += '</ul>';
        html += '</div>';
        
        // Display sections analysis
        html += '<div class="outline-sections">';
        html += '<h3>Document Sections</h3>';
        
        if (outline.sections.length === 0) {
            html += '<p>No HTML5 sectioning elements found</p>';
        } else {
            html += '<ul class="section-list">';
            outline.sections.forEach(section => {
                html += `<li class="section-item">
                    <span class="section-tag">&lt;${section.tagName}&gt;</span>
                    ${section.id ? `<span class="section-id">#${section.id}</span>` : ''}
                    ${section.className ? `<span class="section-class">.${section.className.replace(/\s+/g, '.')}</span>` : ''}
                    
                    ${section.headings.length > 0 ? 
                        `<ul class="section-headings">
                            ${section.headings.map(h => `<li>H${h.level}: ${escapeHtml(h.text)}</li>`).join('')}
                        </ul>` : 
                        '<p class="warning">No headings in this section</p>'}
                </li>`;
            });
            html += '</ul>';
        }
        html += '</div>';
        
        // Display accessibility recommendations
        html += '<div class="outline-recommendations">';
        html += '<h3>Recommendations</h3>';
        html += '<ul>';
        html += '<li>Each page should have exactly one H1 heading</li>';
        html += '<li>Heading levels should not skip (e.g., from H2 to H4)</li>';
        html += '<li>Each section should contain at least one heading</li>';
        html += '<li>Headings should describe the content that follows them</li>';
        html += '</ul>';
        html += '</div>';
        
        // Add keyboard shortcut info at the bottom - remove mention of clicking outside
        html += '<div class="outline-shortcuts">';
        html += '<h3>Keyboard Shortcuts</h3>';
        html += '<ul>';
        html += '<li><kbd>Esc</kbd> - Close HTML outline panel</li>';
        html += '</ul>';
        html += '</div>';
        
        outlineResult.innerHTML = html;
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    log('Setup complete');
});
